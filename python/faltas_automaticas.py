"""Extrai faltas da resposta SIGAA e reconcilia eventos automáticos no EduCuidar.

- Insere faltas novas do período consultado.
- Remove faltas automáticas que sumiram no SIGAA (só tipo automático).
- Não altera registros manuais do professor.
"""

from __future__ import annotations

import json
import re
import subprocess
from datetime import datetime
from pathlib import Path
from typing import Any

import pymysql

from paths import (
    ARQUIVO_CONFIG_PHP,
    DIR_PYTHON,
    JSON_LISTA_FALTAS,
    JSON_RESPOSTA_ALUNOS,
    ROOT,
)

TIPO_EVENTO_AUTO = "Falta (registro automático)"
# Tipos manuais: se existir na data, não cria registro automático naquele dia.
TIPOS_FALTA_PROFESSOR = (
    "Ausência da aula",
    "Ausência na aula estando no campus",
    "Falta (registro do professor)",
)

PREFIXO_OBS = "[AUTO]"


def carregar_config_mysql(config_path: Path | None = None) -> dict:
    caminho = config_path or ARQUIVO_CONFIG_PHP
    texto = caminho.read_text(encoding="utf-8")
    valores = {}
    for chave in ("host", "db_name", "username", "password"):
        match = re.search(rf"'{chave}'\s*=>\s*'([^']*)'", texto)
        if not match:
            raise ValueError(f"Não foi possível ler '{chave}' em {caminho}")
        valores[chave] = match.group(1)
    return {
        "host": valores["host"],
        "database": valores["db_name"],
        "user": valores["username"],
        "password": valores["password"],
        "charset": "utf8mb4",
        "cursorclass": pymysql.cursors.DictCursor,
        "autocommit": False,
    }


def normalizar_cpf(valor) -> str:
    return re.sub(r"\D", "", str(valor or ""))


def parsear_data_api(texto: str) -> str | None:
    """Converte DD/MM/AAAA (API) para AAAA-MM-DD."""
    texto = str(texto or "").strip()
    if not texto:
        return None
    for fmt in ("%d/%m/%Y", "%d-%m-%Y", "%Y-%m-%d"):
        try:
            return datetime.strptime(texto, fmt).date().isoformat()
        except ValueError:
            continue
    return None


def montar_observacao(codigo: str, disciplina: str) -> str:
    codigo = (codigo or "").strip()
    disciplina = (disciplina or "").strip()
    if codigo and disciplina:
        return f"{PREFIXO_OBS} {codigo} - {disciplina}"
    if codigo:
        return f"{PREFIXO_OBS} {codigo}"
    if disciplina:
        return f"{PREFIXO_OBS} {disciplina}"
    return PREFIXO_OBS


def extrair_faltas_da_resposta(alunos: list[dict[str, Any]]) -> list[dict[str, Any]]:
    """Monta lista plana: aluno/disciplina/data de falta."""
    faltas: list[dict[str, Any]] = []
    vistos: set[tuple] = set()

    for aluno in alunos:
        if aluno.get("status") != 200:
            continue

        login = normalizar_cpf(aluno.get("login"))
        nome = str(aluno.get("nome") or "")
        matricula = str(aluno.get("matricula") or "")
        aluno_id = aluno.get("aluno_id")
        dados = aluno.get("dados")

        if not isinstance(dados, dict):
            continue

        for perfil in dados.values():
            if not isinstance(perfil, dict):
                continue
            for curso in perfil.get("cursos", []) or []:
                if not isinstance(curso, dict):
                    continue
                frequencias = curso.get("frequencias")
                if not isinstance(frequencias, dict):
                    continue
                disciplinas = frequencias.get("disciplinas")
                if not isinstance(disciplinas, dict):
                    continue

                for codigo_chave, disciplina in disciplinas.items():
                    if not isinstance(disciplina, dict):
                        continue
                    codigo = str(
                        disciplina.get("cod_disciplina") or codigo_chave or ""
                    ).strip()
                    nome_disc = str(disciplina.get("nome") or "").strip()
                    ausencias = disciplina.get("ausencias")
                    if not isinstance(ausencias, list):
                        continue

                    for dia in ausencias:
                        data_iso = parsear_data_api(str(dia))
                        if not data_iso:
                            continue
                        chave = (login, codigo, data_iso)
                        if chave in vistos:
                            continue
                        vistos.add(chave)
                        faltas.append(
                            {
                                "aluno_id": aluno_id,
                                "login": login,
                                "nome": nome,
                                "matricula": matricula,
                                "curso": curso.get("nome_curso") or "",
                                "codigo_disciplina": codigo,
                                "disciplina": nome_disc,
                                "data": data_iso,
                                "data_original": str(dia),
                                "observacoes": montar_observacao(codigo, nome_disc),
                            }
                        )

    faltas.sort(
        key=lambda f: (f.get("nome") or "", f.get("data") or "", f.get("disciplina") or "")
    )
    return faltas


def salvar_lista_faltas(faltas: list[dict[str, Any]], caminho: Path | None = None) -> Path:
    destino = caminho or JSON_LISTA_FALTAS
    destino.write_text(
        json.dumps(faltas, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )
    return destino


def garantir_tipo_evento_auto(conn) -> int:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT id FROM tipos_eventos WHERE nome = %s LIMIT 1",
            (TIPO_EVENTO_AUTO,),
        )
        row = cur.fetchone()
        if row:
            return int(row["id"])

        cur.execute(
            """
            INSERT INTO tipos_eventos (nome, cor, gera_prontuario, ativo)
            VALUES (%s, 'danger', 0, 0)
            """,
            (TIPO_EVENTO_AUTO,),
        )
        return int(cur.lastrowid)


def obter_usuario_sistema(conn, user_id_env: str | None = None) -> int:
    if user_id_env:
        try:
            return int(user_id_env)
        except ValueError:
            pass

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT u.id
            FROM users u
            INNER JOIN user_user_types uut ON uut.user_id = u.id
            INNER JOIN user_types ut ON ut.id = uut.user_type_id
            WHERE ut.nivel = 'administrador'
            ORDER BY u.id ASC
            LIMIT 1
            """
        )
        row = cur.fetchone()
        if row:
            return int(row["id"])

        cur.execute("SELECT id FROM users ORDER BY id ASC LIMIT 1")
        row = cur.fetchone()
        if not row:
            raise RuntimeError("Nenhum usuário encontrado para registrado_por.")
        return int(row["id"])


def mapear_alunos_por_cpf(conn) -> dict[str, dict[str, Any]]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id, cpf, nome, numero_matricula
            FROM alunos
            WHERE COALESCE(desistente, 0) = 0
              AND cpf IS NOT NULL
              AND TRIM(cpf) <> ''
            """
        )
        rows = cur.fetchall()

    mapa = {}
    for row in rows:
        cpf = normalizar_cpf(row.get("cpf"))
        if len(cpf) == 11:
            mapa[cpf] = row
    return mapa


def ids_tipos_falta_professor(conn) -> list[int]:
    with conn.cursor() as cur:
        placeholders = ",".join(["%s"] * len(TIPOS_FALTA_PROFESSOR))
        cur.execute(
            f"SELECT id FROM tipos_eventos WHERE nome IN ({placeholders})",
            TIPOS_FALTA_PROFESSOR,
        )
        return [int(r["id"]) for r in cur.fetchall()]


def ja_existe_falta_professor(conn, aluno_id: int, data: str, tipos_ids: list[int]) -> bool:
    """Há registro manual de falta do professor para o aluno na data."""
    if not tipos_ids:
        return False
    placeholders = ",".join(["%s"] * len(tipos_ids))
    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT id FROM eventos
            WHERE aluno_id = %s
              AND data_evento = %s
              AND tipo_evento_id IN ({placeholders})
            LIMIT 1
            """,
            [aluno_id, data, *tipos_ids],
        )
        return cur.fetchone() is not None


def ja_existe_falta_automatica_disciplina(
    conn, aluno_id: int, data: str, tipo_auto_id: int, codigo_disciplina: str
) -> bool:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id FROM eventos
            WHERE aluno_id = %s
              AND data_evento = %s
              AND tipo_evento_id = %s
              AND observacoes LIKE %s
            LIMIT 1
            """,
            (
                aluno_id,
                data,
                tipo_auto_id,
                f"%{PREFIXO_OBS} {codigo_disciplina} -%",
            ),
        )
        return cur.fetchone() is not None


def obter_turma_aluno(conn, aluno_id: int) -> int | None:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT valor FROM configuracoes WHERE chave = 'ano_corrente' LIMIT 1"
        )
        row = cur.fetchone()
        ano = int(row["valor"]) if row and str(row["valor"]).isdigit() else datetime.now().year

        cur.execute(
            """
            SELECT t.id
            FROM aluno_turmas at
            INNER JOIN turmas t ON t.id = at.turma_id
            WHERE at.aluno_id = %s AND t.ano_civil = %s
            ORDER BY t.ano_curso ASC
            LIMIT 1
            """,
            (aluno_id, ano),
        )
        row = cur.fetchone()
        if row:
            return int(row["id"])

        cur.execute(
            """
            SELECT t.id
            FROM aluno_turmas at
            INNER JOIN turmas t ON t.id = at.turma_id
            WHERE at.aluno_id = %s
            ORDER BY t.ano_civil DESC, t.ano_curso ASC
            LIMIT 1
            """,
            (aluno_id,),
        )
        row = cur.fetchone()
        return int(row["id"]) if row else None


def inserir_evento_falta(
    conn,
    *,
    aluno_id: int,
    turma_id: int | None,
    tipo_evento_id: int,
    data_evento: str,
    observacoes: str,
    registrado_por: int,
) -> int:
    with conn.cursor() as cur:
        cur.execute(
            """
            INSERT INTO eventos
                (aluno_id, turma_id, tipo_evento_id, data_evento, hora_evento,
                 observacoes, prontuario, registrado_por)
            VALUES (%s, %s, %s, %s, NULL, %s, NULL, %s)
            """,
            (
                aluno_id,
                turma_id,
                tipo_evento_id,
                data_evento,
                observacoes,
                registrado_por,
            ),
        )
        return int(cur.lastrowid)


def codigo_de_observacao_auto(observacoes: str | None) -> str | None:
    """Extrai o código da disciplina de '[AUTO] COD - Disciplina'."""
    texto = str(observacoes or "").strip()
    match = re.match(r"^\[AUTO\]\s*(\S+)\s*-", texto)
    if match:
        return match.group(1).strip()
    # Fallback: só código sem disciplina
    match = re.match(r"^\[AUTO\]\s*(\S+)\s*$", texto)
    if match:
        return match.group(1).strip()
    return None


def chave_falta_auto(aluno_id: int, data: str, codigo: str) -> tuple[int, str, str]:
    return (int(aluno_id), str(data), str(codigo or "").strip())


def obter_periodo_frequencia_iso(conn) -> tuple[str | None, str | None]:
    """Lê datas da consulta SIGAA (DD-MM-AAAA ou AAAA-MM-DD) → ISO."""
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT chave, valor FROM configuracoes
            WHERE chave IN (
                'api_sigaa_frequencia_data_inicial',
                'api_sigaa_frequencia_data_final'
            )
            """
        )
        rows = {r["chave"]: r["valor"] for r in cur.fetchall()}
    ini = parsear_data_api(rows.get("api_sigaa_frequencia_data_inicial") or "")
    fim = parsear_data_api(rows.get("api_sigaa_frequencia_data_final") or "")
    return ini, fim


def resolver_aluno_id(
    falta_ou_aluno: dict[str, Any],
    alunos_cpf: dict[str, dict[str, Any]],
) -> int | None:
    if falta_ou_aluno.get("aluno_id"):
        try:
            return int(falta_ou_aluno["aluno_id"])
        except (TypeError, ValueError):
            pass
    login = normalizar_cpf(falta_ou_aluno.get("login"))
    row = alunos_cpf.get(login)
    return int(row["id"]) if row else None


def alunos_consultados_ok(
    respostas: list[dict[str, Any]],
    alunos_cpf: dict[str, dict[str, Any]],
) -> set[int]:
    """Alunos com consulta SIGAA bem-sucedida (status 200) e vínculo no BD."""
    ids: set[int] = set()
    for item in respostas:
        if item.get("status") != 200:
            continue
        aluno_id = resolver_aluno_id(item, alunos_cpf)
        if aluno_id:
            ids.add(aluno_id)
    return ids


def listar_eventos_auto_periodo(
    conn,
    *,
    tipo_auto_id: int,
    aluno_ids: set[int],
    data_inicial: str,
    data_final: str,
) -> list[dict[str, Any]]:
    if not aluno_ids:
        return []
    ids = sorted(aluno_ids)
    placeholders = ",".join(["%s"] * len(ids))
    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT id, aluno_id, data_evento, observacoes
            FROM eventos
            WHERE tipo_evento_id = %s
              AND aluno_id IN ({placeholders})
              AND data_evento >= %s
              AND data_evento <= %s
            """,
            [tipo_auto_id, *ids, data_inicial, data_final],
        )
        return list(cur.fetchall())


def apagar_eventos_auto_por_ids(conn, evento_ids: list[int]) -> int:
    if not evento_ids:
        return 0
    placeholders = ",".join(["%s"] * len(evento_ids))
    with conn.cursor() as cur:
        cur.execute(
            f"DELETE FROM eventos WHERE id IN ({placeholders})",
            evento_ids,
        )
        return int(cur.rowcount)


def importar_faltas(
    faltas: list[dict[str, Any]] | None = None,
    *,
    arquivo_resposta: Path | None = None,
    dry_run: bool = False,
    user_id_env: str | None = None,
    gerar_lista_faltas: bool = False,
) -> dict[str, Any]:
    """
    Reconcilia faltas automáticas com o SIGAA:
    - Insere faltas novas (disciplina/data) que ainda não existem.
    - Remove eventos automáticos do período consultado que sumiram no SIGAA.
    - Não altera faltas lançadas pelo professor.
    - Só reprocessa alertas dos alunos com inserção ou remoção.
    """
    respostas_brutas: list[dict[str, Any]] | None = None
    reconciliar = False

    if faltas is None:
        caminho = arquivo_resposta or JSON_RESPOSTA_ALUNOS
        alunos = json.loads(caminho.read_text(encoding="utf-8"))
        if not isinstance(alunos, list):
            raise ValueError("resposta_alunos.json deve ser uma lista")
        respostas_brutas = alunos
        faltas = extrair_faltas_da_resposta(alunos)
        reconciliar = True
    elif faltas and isinstance(faltas[0], dict) and "status" in faltas[0]:
        # Lista de respostas da API (consulta_alunos)
        respostas_brutas = faltas
        faltas = extrair_faltas_da_resposta(faltas)
        reconciliar = True

    lista_path = None
    if gerar_lista_faltas:
        lista_path = salvar_lista_faltas(faltas)

    resumo = {
        "lista_arquivo": str(lista_path) if lista_path else None,
        "total_faltas_extraidas": len(faltas),
        "inseridos": 0,
        "removidos": 0,
        "pulados_sem_aluno": 0,
        "pulados_professor": 0,
        "pulados_duplicado": 0,
        "erros": 0,
        "alunos_afetados": [],
        "dry_run": dry_run,
        "reconciliado": False,
    }

    conn = pymysql.connect(**carregar_config_mysql())
    alunos_afetados: set[int] = set()
    try:
        tipo_auto_id = garantir_tipo_evento_auto(conn)
        registrado_por = obter_usuario_sistema(conn, user_id_env)
        tipos_professor = ids_tipos_falta_professor(conn)
        alunos_cpf = mapear_alunos_por_cpf(conn)
        turmas_cache: dict[int, int | None] = {}
        cache_professor: dict[tuple[int, str], bool] = {}

        # Chaves presentes no SIGAA nesta leitura
        chaves_sigaa: set[tuple[int, str, str]] = set()
        faltas_validas: list[tuple[int, dict[str, Any]]] = []

        for falta in faltas:
            aluno_id = resolver_aluno_id(falta, alunos_cpf)
            if not aluno_id:
                resumo["pulados_sem_aluno"] += 1
                continue
            data = falta["data"]
            codigo = str(falta.get("codigo_disciplina") or "").strip()
            chaves_sigaa.add(chave_falta_auto(aluno_id, data, codigo))
            faltas_validas.append((aluno_id, falta))

        # 1) Inserir novas
        for aluno_id, falta in faltas_validas:
            data = falta["data"]
            codigo = str(falta.get("codigo_disciplina") or "").strip()

            chave_prof = (aluno_id, data)
            if chave_prof not in cache_professor:
                cache_professor[chave_prof] = ja_existe_falta_professor(
                    conn, aluno_id, data, tipos_professor
                )
            if cache_professor[chave_prof]:
                resumo["pulados_professor"] += 1
                continue

            if ja_existe_falta_automatica_disciplina(
                conn, aluno_id, data, tipo_auto_id, codigo
            ):
                resumo["pulados_duplicado"] += 1
                continue

            if aluno_id not in turmas_cache:
                turmas_cache[aluno_id] = obter_turma_aluno(conn, aluno_id)
            turma_id = turmas_cache[aluno_id]

            if dry_run:
                resumo["inseridos"] += 1
                alunos_afetados.add(aluno_id)
                continue

            try:
                inserir_evento_falta(
                    conn,
                    aluno_id=aluno_id,
                    turma_id=turma_id,
                    tipo_evento_id=tipo_auto_id,
                    data_evento=data,
                    observacoes=falta.get("observacoes")
                    or montar_observacao(codigo, falta.get("disciplina") or ""),
                    registrado_por=registrado_por,
                )
                resumo["inseridos"] += 1
                alunos_afetados.add(aluno_id)
            except Exception:
                resumo["erros"] += 1

        # 2) Remover automáticas do período que sumiram no SIGAA
        if reconciliar and respostas_brutas is not None:
            consultados = alunos_consultados_ok(respostas_brutas, alunos_cpf)
            data_ini, data_fim = obter_periodo_frequencia_iso(conn)
            if not data_ini or not data_fim:
                # Fallback: extremos das faltas lidas (ou só hoje)
                datas = [f["data"] for f in faltas if f.get("data")]
                if datas:
                    data_ini = min(datas)
                    data_fim = max(datas)

            if consultados and data_ini and data_fim:
                resumo["reconciliado"] = True
                existentes = listar_eventos_auto_periodo(
                    conn,
                    tipo_auto_id=tipo_auto_id,
                    aluno_ids=consultados,
                    data_inicial=data_ini,
                    data_final=data_fim,
                )
                ids_apagar: list[int] = []
                for ev in existentes:
                    codigo = codigo_de_observacao_auto(ev.get("observacoes"))
                    if not codigo:
                        # Observação fora do padrão: não remove automaticamente
                        continue
                    data_ev = ev["data_evento"]
                    if hasattr(data_ev, "isoformat"):
                        data_ev = data_ev.isoformat()
                    else:
                        data_ev = str(data_ev)[:10]
                    chave = chave_falta_auto(int(ev["aluno_id"]), data_ev, codigo)
                    if chave not in chaves_sigaa:
                        ids_apagar.append(int(ev["id"]))
                        alunos_afetados.add(int(ev["aluno_id"]))

                if dry_run:
                    resumo["removidos"] = len(ids_apagar)
                elif ids_apagar:
                    resumo["removidos"] = apagar_eventos_auto_por_ids(conn, ids_apagar)

        if not dry_run:
            conn.commit()
        else:
            conn.rollback()

        resumo["alunos_afetados"] = sorted(alunos_afetados)
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()

    return resumo


def processar_alertas_alunos(aluno_ids: list[int]) -> None:
    if not aluno_ids:
        return
    script = DIR_PYTHON / "processar_alertas_cli.php"
    if not script.is_file():
        return
    cmd = ["php", str(script), *[str(i) for i in aluno_ids]]
    try:
        subprocess.run(cmd, cwd=str(ROOT), check=False, capture_output=True, text=True)
    except FileNotFoundError:
        pass

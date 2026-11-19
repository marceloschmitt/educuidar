#!/usr/bin/env python3
"""Consulta matriculados SIGAA e grava a grade de aulas + docentes no EduCuidar.

Para cada turma cadastrada no EduCuidar, usa **um aluno** representante
(com número de matrícula) e consulta a API **por matrícula**
(`GET /matriculados?matricula=…&periodo_letivo=…`). A mesma coleta alimenta
a tela de horários e os alertas.

Uso:
    python3 importar_grade.py
    python3 importar_grade.py --salvar-json
    python3 importar_grade.py --arquivo resposta_matriculas_grade.json
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import datetime
from pathlib import Path
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import Request, urlopen

try:
    import pymysql
except ImportError:
    print("Instale o pacote pymysql: pip install pymysql", file=sys.stderr)
    sys.exit(1)

from api_auth import (
    DEFAULTS,
    USER_AGENT,
    carregar_config_api,
    carregar_config_mysql,
    obter_access_token,
    ssl_context,
)
from paths import DIR_PYTHON, garantir_diretorios
from turno_turma import extrair_datas_detalhadas

TIMEOUT_SEGUNDOS = 120
TENTATIVAS = 3
CONCORRENCIA = 10
CHAVE_GRADE_EM = "api_sigaa_grade_gerada_em"
CHAVE_GRADE_RESUMO = "api_sigaa_grade_resumo"
JSON_GRADE = DIR_PYTHON / "resposta_matriculas_grade.json"


def montar_url_matricula(base_url: str, matricula: str, periodo_letivo: str) -> str:
    params = {
        "matricula": matricula,
        "matriculado": "sim",
        "periodo_letivo": periodo_letivo,
    }
    return f"{base_url.rstrip('/')}/api/v1/sig/sigaa/matriculados?{urlencode(params)}"


def registros_da_resposta(dados: Any) -> list[dict[str, Any]]:
    if isinstance(dados, dict) and isinstance(dados.get("data"), list):
        return [r for r in dados["data"] if isinstance(r, dict)]
    if isinstance(dados, dict):
        return [r for r in dados.values() if isinstance(r, dict)]
    if isinstance(dados, list):
        return [r for r in dados if isinstance(r, dict)]
    raise ValueError("Formato inesperado da resposta de matriculados.")


def _matricula_registro(registro: dict[str, Any]) -> str:
    for chave in ("Matricula", "matricula", "numero_matricula", "mat"):
        valor = registro.get(chave)
        if valor is None:
            continue
        texto = str(valor).strip()
        if texto:
            return texto
    return ""


def _tem_turno_turma(registro: dict[str, Any]) -> bool:
    disciplinas = registro.get("disciplinas")
    if not isinstance(disciplinas, list):
        return False
    return any(
        isinstance(d, dict) and str(d.get("turno_turma") or "").strip()
        for d in disciplinas
    )


def registro_da_consulta(
    dados: Any, matricula_esperada: str = ""
) -> dict[str, Any] | None:
    """Extrai o registro do aluno (com disciplinas) de uma resposta por matrícula."""
    registros = registros_da_resposta(dados)
    if not registros:
        return None

    mat_esp = str(matricula_esperada or "").strip()
    mat_esp_dig = re.sub(r"\D", "", mat_esp)

    candidatos = [
        r
        for r in registros
        if isinstance(r.get("disciplinas"), list) and r.get("disciplinas")
    ]
    if not candidatos:
        candidatos = list(registros)

    if mat_esp:
        for registro in candidatos:
            mat = _matricula_registro(registro)
            if mat == mat_esp or (
                mat_esp_dig and re.sub(r"\D", "", mat) == mat_esp_dig
            ):
                return registro
        # Resposta costuma vir indexada pela matrícula
        if isinstance(dados, dict):
            direto = dados.get(mat_esp) or (
                dados.get(mat_esp_dig) if mat_esp_dig else None
            )
            if isinstance(direto, dict):
                return direto

    # Preferir quem tem turno_turma
    com_turno = [r for r in candidatos if _tem_turno_turma(r)]
    if len(com_turno) == 1:
        return com_turno[0]
    if len(candidatos) == 1:
        return candidatos[0]
    if com_turno:
        return com_turno[0]
    return candidatos[0] if candidatos else None


def indice_por_matricula(registros: list[dict[str, Any]]) -> dict[str, dict[str, Any]]:
    """Índice para JSON antigo (dump do campus)."""
    indice: dict[str, dict[str, Any]] = {}
    for registro in registros:
        disciplinas = registro.get("disciplinas")
        if not isinstance(disciplinas, list) or not disciplinas:
            continue
        mat = _matricula_registro(registro)
        if mat == "":
            continue
        tem_turno = _tem_turno_turma(registro)
        atual = indice.get(mat)
        if atual is None or (tem_turno and not _tem_turno_turma(atual)):
            indice[mat] = registro
    return indice


def consultar_uma_matricula(
    *,
    env: dict[str, str],
    token: str,
    base_url: str,
    matricula: str,
    periodo_letivo: str,
) -> dict[str, Any]:
    url = montar_url_matricula(base_url, matricula, periodo_letivo)
    ultimo_erro = ""
    for tentativa in range(1, TENTATIVAS + 1):
        request = Request(
            url,
            headers={
                "Accept": "application/json",
                "Authorization": f"Bearer {token}",
                "User-Agent": USER_AGENT,
            },
            method="GET",
        )
        try:
            with urlopen(
                request, timeout=TIMEOUT_SEGUNDOS, context=ssl_context(env)
            ) as response:
                body = response.read().decode("utf-8")
                status = response.getcode()
            if status == 200:
                dados = json.loads(body)
                registro = registro_da_consulta(dados, matricula)
                return {
                    "status": 200,
                    "tentativas": tentativa,
                    "dados": dados,
                    "registro": registro,
                }
            ultimo_erro = body
            if status < 500:
                return {
                    "status": status,
                    "tentativas": tentativa,
                    "erro": body,
                    "registro": None,
                }
        except HTTPError as error:
            corpo = error.read().decode("utf-8", errors="replace")
            ultimo_erro = corpo
            if error.code < 500:
                return {
                    "status": error.code,
                    "tentativas": tentativa,
                    "erro": corpo,
                    "registro": None,
                }
        except URLError as error:
            ultimo_erro = str(error.reason)
        except TimeoutError:
            ultimo_erro = "tempo limite excedido"
        except json.JSONDecodeError as error:
            return {
                "status": 200,
                "tentativas": tentativa,
                "erro": f"JSON inválido: {error}",
                "registro": None,
            }
    return {
        "status": None,
        "tentativas": TENTATIVAS,
        "erro": ultimo_erro or "falha desconhecida",
        "registro": None,
    }


def consultar_representantes(
    env: dict[str, str],
    representantes: list[dict[str, Any]],
    periodo_letivo: str,
    concorrencia: int = CONCORRENCIA,
) -> list[dict[str, Any]]:
    base_url = env.get("API_BASE_URL") or DEFAULTS["api_sigaa_base_url"]
    token = obter_access_token(env)
    resultados: list[dict[str, Any] | None] = [None] * len(representantes)
    workers = max(1, int(concorrencia or CONCORRENCIA))

    def trabalho(idx: int, rep: dict[str, Any]) -> tuple[int, dict[str, Any]]:
        mat = str(rep["numero_matricula"]).strip()
        consulta = consultar_uma_matricula(
            env=env,
            token=token,
            base_url=base_url,
            matricula=mat,
            periodo_letivo=periodo_letivo,
        )
        return idx, {
            "turma_id": int(rep["turma_id"]),
            "curso_nome": rep.get("curso_nome"),
            "ano_curso": rep.get("ano_curso"),
            "aluno_id": rep.get("aluno_id"),
            "aluno_nome": rep.get("aluno_nome"),
            "matricula": mat,
            **consulta,
        }

    with ThreadPoolExecutor(max_workers=workers) as pool:
        futures = [
            pool.submit(trabalho, i, rep) for i, rep in enumerate(representantes)
        ]
        for future in as_completed(futures):
            idx, item = future.result()
            resultados[idx] = item

    return [r for r in resultados if r is not None]


def _coluna_existe(cur, tabela: str, coluna: str) -> bool:
    cur.execute(
        """
        SELECT COUNT(*) AS n
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = %s
          AND COLUMN_NAME = %s
        """,
        (tabela, coluna),
    )
    row = cur.fetchone()
    return bool(row and int(row["n"] if isinstance(row, dict) else row[0]) > 0)


def garantir_tabelas(conn) -> None:
    with conn.cursor() as cur:
        cur.execute(
            """
            CREATE TABLE IF NOT EXISTS disciplina_aulas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                turma_id INT NULL,
                codigo_disciplina VARCHAR(50) NOT NULL,
                disciplina_nome VARCHAR(255) NULL,
                curso_nome VARCHAR(255) NULL,
                id_turma_sigaa INT NOT NULL DEFAULT 0,
                data_aula DATE NOT NULL,
                periodos INT NOT NULL DEFAULT 1,
                turno CHAR(1) NULL,
                periodos_nums VARCHAR(64) NULL,
                periodo_letivo VARCHAR(10) NULL,
                UNIQUE KEY uq_disciplina_aula_turma (
                    turma_id, codigo_disciplina, id_turma_sigaa, data_aula
                ),
                INDEX idx_data_aula (data_aula),
                INDEX idx_codigo_data (codigo_disciplina, data_aula),
                INDEX idx_turma_id (turma_id),
                INDEX idx_periodo_letivo (periodo_letivo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """
        )
        for coluna, definicao in (
            ("turno", "CHAR(1) NULL"),
            ("periodos_nums", "VARCHAR(64) NULL"),
            ("turma_id", "INT NULL"),
        ):
            if not _coluna_existe(cur, "disciplina_aulas", coluna):
                cur.execute(
                    f"ALTER TABLE disciplina_aulas ADD COLUMN {coluna} {definicao}"
                )
        try:
            cur.execute(
                "ALTER TABLE disciplina_aulas ADD INDEX idx_turma_id (turma_id)"
            )
        except Exception:
            pass
        cur.execute(
            """
            CREATE TABLE IF NOT EXISTS disciplina_docentes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                turma_id INT NULL,
                codigo_disciplina VARCHAR(50) NOT NULL,
                disciplina_nome VARCHAR(255) NULL,
                curso_nome VARCHAR(255) NULL,
                id_turma_sigaa INT NOT NULL DEFAULT 0,
                docente_nome VARCHAR(255) NOT NULL,
                docente_email VARCHAR(255) NULL,
                docente_cpf VARCHAR(14) NULL,
                user_id INT NULL,
                periodo_letivo VARCHAR(10) NULL,
                UNIQUE KEY uq_disciplina_docente_turma (
                    turma_id, codigo_disciplina, id_turma_sigaa,
                    docente_nome, periodo_letivo
                ),
                INDEX idx_codigo_turma (codigo_disciplina, id_turma_sigaa),
                INDEX idx_turma_id (turma_id),
                INDEX idx_user_id (user_id),
                INDEX idx_periodo_letivo (periodo_letivo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """
        )
        if not _coluna_existe(cur, "disciplina_docentes", "turma_id"):
            cur.execute("ALTER TABLE disciplina_docentes ADD COLUMN turma_id INT NULL")
            try:
                cur.execute(
                    "ALTER TABLE disciplina_docentes ADD INDEX idx_turma_id (turma_id)"
                )
            except Exception:
                pass
    conn.commit()


def _periodos_nums_str(nums: list[int]) -> str:
    return ",".join(str(n) for n in sorted(set(int(x) for x in nums if int(x) > 0)))


def turmas_com_representante(conn, ano_civil: int) -> list[dict[str, Any]]:
    """Uma linha por turma EduCuidar com um aluno (matrícula) representante."""
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT t.id AS turma_id,
                   t.ano_curso,
                   t.ano_civil,
                   c.nome AS curso_nome,
                   a.id AS aluno_id,
                   a.nome AS aluno_nome,
                   a.numero_matricula
            FROM turmas t
            INNER JOIN cursos c ON c.id = t.curso_id
            INNER JOIN aluno_turmas at ON at.turma_id = t.id
            INNER JOIN alunos a ON a.id = at.aluno_id
            WHERE t.ano_civil = %s
              AND COALESCE(a.desistente, 0) = 0
              AND a.numero_matricula IS NOT NULL
              AND TRIM(a.numero_matricula) <> ''
            ORDER BY c.nome ASC, t.ano_curso ASC, a.nome ASC
            """,
            (ano_civil,),
        )
        rows = list(cur.fetchall())
    vistos: set[int] = set()
    resultado: list[dict[str, Any]] = []
    for row in rows:
        tid = int(row["turma_id"])
        if tid in vistos:
            continue
        vistos.add(tid)
        resultado.append(row)
    return resultado


def extrair_aulas_do_registro(
    registro: dict[str, Any], turma_id: int
) -> list[dict[str, Any]]:
    vistos: dict[tuple, dict[str, Any]] = {}
    nome_curso = str(registro.get("curso") or "").strip()
    for item in registro.get("disciplinas") or []:
        if not isinstance(item, dict):
            continue
        codigo = str(item.get("cod_disciplina") or "").strip()
        if codigo == "":
            continue
        try:
            id_turma = int(item.get("id_turma") or 0)
        except (TypeError, ValueError):
            id_turma = 0
        nome_disc = str(item.get("disciplina") or "").strip() or codigo
        detalhado = extrair_datas_detalhadas(
            item.get("turno_turma"), incluir_sabado=True
        )
        if not detalhado:
            continue
        for data_aula, info in detalhado.items():
            chave = (turma_id, codigo, id_turma, data_aula.isoformat())
            nums = list(info.get("periodos_nums") or [])
            turno = str(info.get("turno") or "").upper()[:1]
            atual = vistos.get(chave)
            if atual is None:
                vistos[chave] = {
                    "turma_id": turma_id,
                    "codigo_disciplina": codigo,
                    "disciplina_nome": nome_disc,
                    "curso_nome": nome_curso,
                    "id_turma_sigaa": id_turma,
                    "data_aula": data_aula.isoformat(),
                    "periodos": int(info.get("periodos") or max(len(nums), 1)),
                    "turno": turno or None,
                    "periodos_nums": _periodos_nums_str(nums),
                }
            else:
                antigos = [
                    int(x)
                    for x in str(atual.get("periodos_nums") or "").split(",")
                    if x.strip().isdigit()
                ]
                unidos = sorted(set(antigos) | set(nums))
                atual["periodos_nums"] = _periodos_nums_str(unidos)
                atual["periodos"] = max(len(unidos), 1)
                if not atual.get("turno") and turno:
                    atual["turno"] = turno
    return list(vistos.values())


def extrair_docentes_do_registro(
    registro: dict[str, Any], turma_id: int
) -> list[dict[str, Any]]:
    vistos: dict[tuple, dict[str, Any]] = {}
    nome_curso = str(registro.get("curso") or "").strip()
    for item in registro.get("disciplinas") or []:
        if not isinstance(item, dict):
            continue
        codigo = str(item.get("cod_disciplina") or "").strip()
        if codigo == "":
            continue
        try:
            id_turma = int(item.get("id_turma") or 0)
        except (TypeError, ValueError):
            id_turma = 0
        nome_disc = str(item.get("disciplina") or "").strip() or codigo
        for docente in item.get("docentes") or []:
            if not isinstance(docente, dict):
                continue
            nome = str(docente.get("docente") or "").strip()
            if nome == "":
                continue
            email = str(docente.get("email_docente") or "").strip().lower() or None
            cpf = re.sub(r"\D", "", str(docente.get("cpf_docente") or "")) or None
            chave = (turma_id, codigo, id_turma, nome.lower())
            if chave in vistos:
                continue
            vistos[chave] = {
                "turma_id": turma_id,
                "codigo_disciplina": codigo,
                "disciplina_nome": nome_disc,
                "curso_nome": nome_curso,
                "id_turma_sigaa": id_turma,
                "docente_nome": nome,
                "docente_email": email,
                "docente_cpf": cpf,
            }
    return list(vistos.values())


def _mapear_users(conn) -> tuple[dict[str, int], dict[str, int]]:
    por_email: dict[str, int] = {}
    por_nome: dict[str, int] = {}
    with conn.cursor() as cur:
        cur.execute("SELECT id, email, full_name FROM users")
        for row in cur.fetchall():
            uid = int(row["id"])
            email = str(row.get("email") or "").strip().lower()
            if email:
                por_email[email] = uid
            nome = str(row.get("full_name") or "").strip().lower()
            if nome:
                por_nome[nome] = uid
    return por_email, por_nome


def _ano_corrente(conn) -> int:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT valor FROM configuracoes WHERE chave = 'ano_corrente' LIMIT 1"
        )
        row = cur.fetchone()
    if row and str(row.get("valor") or "").isdigit():
        return int(row["valor"])
    return datetime.now().year


def gravar(
    conn,
    aulas: list[dict[str, Any]],
    docentes: list[dict[str, Any]],
    periodo_letivo: str,
    turmas_ok: int,
    turmas_sem_match: int,
) -> dict[str, int]:
    por_email, por_nome = _mapear_users(conn)
    with conn.cursor() as cur:
        cur.execute(
            "DELETE FROM disciplina_aulas WHERE periodo_letivo = %s OR periodo_letivo IS NULL",
            (periodo_letivo,),
        )
        cur.execute(
            "DELETE FROM disciplina_docentes WHERE periodo_letivo = %s OR periodo_letivo IS NULL",
            (periodo_letivo,),
        )
        if aulas:
            cur.executemany(
                """
                INSERT INTO disciplina_aulas
                    (turma_id, codigo_disciplina, disciplina_nome, curso_nome, id_turma_sigaa,
                     data_aula, periodos, turno, periodos_nums, periodo_letivo)
                VALUES
                    (%(turma_id)s, %(codigo_disciplina)s, %(disciplina_nome)s, %(curso_nome)s,
                     %(id_turma_sigaa)s, %(data_aula)s, %(periodos)s, %(turno)s,
                     %(periodos_nums)s, %(periodo_letivo)s)
                """,
                [{**a, "periodo_letivo": periodo_letivo} for a in aulas],
            )

        docentes_gravados = 0
        vinculados = 0
        if docentes:
            linhas = []
            for d in docentes:
                user_id = None
                email = (d.get("docente_email") or "").strip().lower()
                if email and email in por_email:
                    user_id = por_email[email]
                elif d["docente_nome"].strip().lower() in por_nome:
                    user_id = por_nome[d["docente_nome"].strip().lower()]
                if user_id:
                    vinculados += 1
                linhas.append({**d, "user_id": user_id, "periodo_letivo": periodo_letivo})
            cur.executemany(
                """
                INSERT INTO disciplina_docentes
                    (turma_id, codigo_disciplina, disciplina_nome, curso_nome, id_turma_sigaa,
                     docente_nome, docente_email, docente_cpf, user_id, periodo_letivo)
                VALUES
                    (%(turma_id)s, %(codigo_disciplina)s, %(disciplina_nome)s, %(curso_nome)s,
                     %(id_turma_sigaa)s, %(docente_nome)s, %(docente_email)s,
                     %(docente_cpf)s, %(user_id)s, %(periodo_letivo)s)
                """,
                linhas,
            )
            docentes_gravados = len(linhas)

        agora = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        disciplinas = len({(a["turma_id"], a["codigo_disciplina"]) for a in aulas})
        dias = len({a["data_aula"] for a in aulas})
        resumo = (
            f"{turmas_ok} turma(s) EduCuidar, {disciplinas} disciplina(s), "
            f"{dias} dia(s), {len(aulas)} encontro(s), "
            f"{docentes_gravados} docente(s) ({vinculados} com usuário)"
        )
        if turmas_sem_match:
            resumo += f"; {turmas_sem_match} turma(s) sem aluno encontrado no SIGAA"
        for chave, valor, desc in (
            (CHAVE_GRADE_EM, agora, "Última geração da grade de aulas SIGAA"),
            (CHAVE_GRADE_RESUMO, resumo, "Resumo da última grade de aulas SIGAA"),
        ):
            cur.execute(
                """
                INSERT INTO configuracoes (chave, valor, descricao)
                VALUES (%s, %s, %s)
                ON DUPLICATE KEY UPDATE valor = VALUES(valor), descricao = VALUES(descricao)
                """,
                (chave, valor, desc),
            )
    conn.commit()
    return {
        "encontros": len(aulas),
        "disciplinas": disciplinas,
        "dias": dias,
        "turmas_ok": turmas_ok,
        "turmas_sem_match": turmas_sem_match,
        "docentes": docentes_gravados,
        "docentes_vinculados": vinculados,
    }


def _pares_de_arquivo(
    dados: Any, representantes: list[dict[str, Any]]
) -> list[tuple[dict[str, Any], dict[str, Any] | None, str]]:
    """Retorna lista (rep, registro|None, motivo_falha)."""
    pares: list[tuple[dict[str, Any], dict[str, Any] | None, str]] = []

    if isinstance(dados, dict) and isinstance(dados.get("consultas"), list):
        por_turma: dict[int, dict[str, Any]] = {}
        for item in dados["consultas"]:
            if not isinstance(item, dict):
                continue
            try:
                tid = int(item.get("turma_id"))
            except (TypeError, ValueError):
                continue
            por_turma[tid] = item
        for rep in representantes:
            tid = int(rep["turma_id"])
            item = por_turma.get(tid)
            if item is None:
                pares.append((rep, None, "ausente no JSON"))
                continue
            registro = item.get("registro")
            if not isinstance(registro, dict):
                registro = registro_da_consulta(
                    item.get("dados"), str(rep["numero_matricula"]).strip()
                )
            if registro is None:
                pares.append((rep, None, str(item.get("erro") or "sem registro")))
            else:
                pares.append((rep, registro, ""))
        return pares

    # Formato antigo: dump do campus indexado por matrícula
    registros = registros_da_resposta(dados)
    por_mat = indice_por_matricula(registros)
    for rep in representantes:
        mat = str(rep["numero_matricula"]).strip()
        registro = por_mat.get(mat)
        if registro is None:
            mat_dig = re.sub(r"\D", "", mat)
            registro = next(
                (
                    r
                    for m, r in por_mat.items()
                    if re.sub(r"\D", "", m) == mat_dig
                ),
                None,
            )
        if registro is None:
            pares.append((rep, None, "sem match no dump"))
        else:
            pares.append((rep, registro, ""))
    return pares


def main() -> int:
    parser = argparse.ArgumentParser(
        description=(
            "Importa a grade de aulas e docentes do SIGAA "
            "(1 consulta /matriculados por turma EduCuidar, filtrada por matrícula)."
        )
    )
    parser.add_argument(
        "--periodo",
        default="",
        help="Período letivo AAAA/S (padrão: configuracoes.api_sigaa_periodo_letivo).",
    )
    parser.add_argument(
        "--arquivo",
        type=Path,
        help="Lê respostas já salvas (formato novo com consultas[] ou dump antigo).",
    )
    parser.add_argument(
        "--salvar-json",
        action="store_true",
        help=f"Salva as respostas por turma em {JSON_GRADE.name}.",
    )
    parser.add_argument(
        "--concorrencia",
        type=int,
        default=CONCORRENCIA,
        help=f"Consultas paralelas (padrão: {CONCORRENCIA}).",
    )
    parser.add_argument(
        "--unidade",
        default="",
        help=argparse.SUPPRESS,  # legado; dump por unidade não é mais usado
    )
    args = parser.parse_args()

    env = carregar_config_api()
    periodo = (args.periodo or env.get("API_PERIODO_LETIVO") or "").strip()
    if not periodo:
        print("Erro: configure o período letivo em API SIGAA.", file=sys.stderr)
        return 1

    concorrencia = max(1, int(args.concorrencia or CONCORRENCIA))

    conn = None
    try:
        conn = pymysql.connect(**carregar_config_mysql())
        garantir_tabelas(conn)
        ano = _ano_corrente(conn)
        representantes = turmas_com_representante(conn, ano)
        if not representantes:
            print(
                f"Nenhuma turma EduCuidar de {ano} com aluno (matrícula) vinculado.",
                file=sys.stderr,
            )
            return 1

        print(
            f"Turmas com representante: {len(representantes)} "
            f"(ano {ano}, período {periodo})"
        )

        consultas_brutas: list[dict[str, Any]] = []
        pares: list[tuple[dict[str, Any], dict[str, Any] | None, str]]

        if args.arquivo:
            dados = json.loads(args.arquivo.read_text(encoding="utf-8"))
            pares = _pares_de_arquivo(dados, representantes)
        else:
            print(
                f"Consultando SIGAA por matrícula "
                f"({len(representantes)} turma(s), concorrência={concorrencia})…"
            )
            consultas_brutas = consultar_representantes(
                env, representantes, periodo, concorrencia=concorrencia
            )
            pares = []
            for item in consultas_brutas:
                rep = {
                    "turma_id": item["turma_id"],
                    "curso_nome": item.get("curso_nome"),
                    "ano_curso": item.get("ano_curso"),
                    "aluno_id": item.get("aluno_id"),
                    "aluno_nome": item.get("aluno_nome"),
                    "numero_matricula": item.get("matricula"),
                }
                registro = item.get("registro")
                if not isinstance(registro, dict):
                    motivo = str(item.get("erro") or f"HTTP {item.get('status')}")
                    pares.append((rep, None, motivo))
                elif not _tem_turno_turma(registro) and not (
                    isinstance(registro.get("disciplinas"), list)
                    and registro.get("disciplinas")
                ):
                    pares.append((rep, None, "sem disciplinas/turno_turma"))
                else:
                    pares.append((rep, registro, ""))

            if args.salvar_json:
                garantir_diretorios()
                payload = {
                    "periodo_letivo": periodo,
                    "gerado_em": datetime.now().isoformat(timespec="seconds"),
                    "consultas": [
                        {
                            "turma_id": c["turma_id"],
                            "curso_nome": c.get("curso_nome"),
                            "ano_curso": c.get("ano_curso"),
                            "aluno_id": c.get("aluno_id"),
                            "aluno_nome": c.get("aluno_nome"),
                            "matricula": c.get("matricula"),
                            "status": c.get("status"),
                            "tentativas": c.get("tentativas"),
                            "erro": c.get("erro"),
                            "dados": c.get("dados"),
                            "registro": c.get("registro"),
                        }
                        for c in consultas_brutas
                    ],
                }
                JSON_GRADE.write_text(
                    json.dumps(payload, ensure_ascii=False, indent=2),
                    encoding="utf-8",
                )
                print(f"JSON salvo em {JSON_GRADE}")

        aulas: list[dict[str, Any]] = []
        docentes: list[dict[str, Any]] = []
        turmas_ok = 0
        turmas_sem_match = 0
        for rep, registro, motivo in pares:
            tid = int(rep["turma_id"])
            mat = str(rep.get("numero_matricula") or "").strip()
            if registro is None:
                turmas_sem_match += 1
                print(
                    f"  Sem match SIGAA: turma {tid} "
                    f"({rep.get('curso_nome')} {rep.get('ano_curso')}º) "
                    f"aluno {rep.get('aluno_nome')} matrícula {mat}"
                    + (f" — {motivo}" if motivo else "")
                )
                continue
            aulas.extend(extrair_aulas_do_registro(registro, tid))
            docentes.extend(extrair_docentes_do_registro(registro, tid))
            turmas_ok += 1
            n_disc = len(registro.get("disciplinas") or [])
            print(
                f"  OK turma {tid} ({rep.get('curso_nome')} {rep.get('ano_curso')}º) "
                f"via {rep.get('aluno_nome')} ({mat}) — {n_disc} disciplina(s)"
            )

        if not aulas:
            print(
                "Nenhuma aula expandida a partir dos alunos representantes.",
                file=sys.stderr,
            )
            return 1

        stats = gravar(conn, aulas, docentes, periodo, turmas_ok, turmas_sem_match)
        print(f"Período letivo: {periodo}")
        print(
            f"Grade gravada: {stats['turmas_ok']} turma(s), "
            f"{stats['disciplinas']} disciplinas, {stats['dias']} dias, "
            f"{stats['encontros']} encontros; "
            f"{stats['docentes']} docentes ({stats['docentes_vinculados']} com usuário)."
        )
        if stats["turmas_sem_match"]:
            print(
                f"Atenção: {stats['turmas_sem_match']} turma(s) sem correspondente no SIGAA."
            )
        return 0
    except HTTPError as error:
        detalhe = error.read().decode("utf-8", errors="replace")
        print(f"Erro HTTP {error.code}: {detalhe}", file=sys.stderr)
        return 1
    except URLError as error:
        print(f"Erro de conexão: {error.reason}", file=sys.stderr)
        return 1
    except TimeoutError:
        print("Erro: tempo limite excedido.", file=sys.stderr)
        return 1
    except pymysql.MySQLError as error:
        if conn:
            conn.rollback()
        print(f"Erro no banco: {error}", file=sys.stderr)
        return 1
    except (OSError, ValueError, RuntimeError, json.JSONDecodeError) as error:
        print(f"Erro: {error}", file=sys.stderr)
        return 1
    finally:
        if conn:
            conn.close()


if __name__ == "__main__":
    raise SystemExit(main())

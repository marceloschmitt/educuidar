#!/usr/bin/env python3
"""Consulta matriculados SIGAA e grava a grade de aulas + docentes no EduCuidar.

Usa turno_turma para expandir datas efetivas (turno + números de período).
Acionado pelo botão em Configurações → API SIGAA (ou via CLI).

Uso:
    python3 importar_grade.py
    python3 importar_grade.py --unidade 31
    python3 importar_grade.py --arquivo resposta_matriculas_grade.json
"""

from __future__ import annotations

import argparse
import json
import re
import sys
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

TIMEOUT_SEGUNDOS = 300
CHAVE_GRADE_EM = "api_sigaa_grade_gerada_em"
CHAVE_GRADE_RESUMO = "api_sigaa_grade_resumo"
JSON_GRADE = DIR_PYTHON / "resposta_matriculas_grade.json"


def montar_url(base_url: str, unidade: str, periodo_letivo: str) -> str:
    params = {
        "unidade": unidade,
        "matriculado": "sim",
        "periodo_letivo": periodo_letivo,
    }
    return f"{base_url.rstrip('/')}/api/v1/sig/sigaa/matriculados?{urlencode(params)}"


def consultar_matriculados(unidade: str, periodo_letivo: str) -> Any:
    env = carregar_config_api()
    base_url = env.get("API_BASE_URL") or DEFAULTS["api_sigaa_base_url"]
    token = obter_access_token(env)
    url = montar_url(base_url, unidade, periodo_letivo)
    request = Request(
        url,
        headers={
            "Accept": "application/json",
            "Authorization": f"Bearer {token}",
            "User-Agent": USER_AGENT,
        },
        method="GET",
    )
    with urlopen(request, timeout=TIMEOUT_SEGUNDOS, context=ssl_context(env)) as response:
        return json.loads(response.read().decode("utf-8"))


def registros_da_resposta(dados: Any) -> list[dict[str, Any]]:
    if isinstance(dados, dict) and isinstance(dados.get("data"), list):
        return [r for r in dados["data"] if isinstance(r, dict)]
    if isinstance(dados, dict):
        return [r for r in dados.values() if isinstance(r, dict)]
    if isinstance(dados, list):
        return [r for r in dados if isinstance(r, dict)]
    raise ValueError("Formato inesperado da resposta de matriculados.")


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
                codigo_disciplina VARCHAR(50) NOT NULL,
                disciplina_nome VARCHAR(255) NULL,
                curso_nome VARCHAR(255) NULL,
                id_turma_sigaa INT NOT NULL DEFAULT 0,
                data_aula DATE NOT NULL,
                periodos INT NOT NULL DEFAULT 1,
                turno CHAR(1) NULL,
                periodos_nums VARCHAR(64) NULL,
                periodo_letivo VARCHAR(10) NULL,
                UNIQUE KEY uq_disciplina_aula (
                    codigo_disciplina, curso_nome, id_turma_sigaa, data_aula
                ),
                INDEX idx_data_aula (data_aula),
                INDEX idx_codigo_data (codigo_disciplina, data_aula),
                INDEX idx_periodo_letivo (periodo_letivo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """
        )
        if not _coluna_existe(cur, "disciplina_aulas", "turno"):
            cur.execute("ALTER TABLE disciplina_aulas ADD COLUMN turno CHAR(1) NULL")
        if not _coluna_existe(cur, "disciplina_aulas", "periodos_nums"):
            cur.execute(
                "ALTER TABLE disciplina_aulas ADD COLUMN periodos_nums VARCHAR(64) NULL"
            )
        cur.execute(
            """
            CREATE TABLE IF NOT EXISTS disciplina_docentes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                codigo_disciplina VARCHAR(50) NOT NULL,
                disciplina_nome VARCHAR(255) NULL,
                curso_nome VARCHAR(255) NULL,
                id_turma_sigaa INT NOT NULL DEFAULT 0,
                docente_nome VARCHAR(255) NOT NULL,
                docente_email VARCHAR(255) NULL,
                docente_cpf VARCHAR(14) NULL,
                user_id INT NULL,
                periodo_letivo VARCHAR(10) NULL,
                UNIQUE KEY uq_disciplina_docente (
                    codigo_disciplina, curso_nome, id_turma_sigaa,
                    docente_nome, periodo_letivo
                ),
                INDEX idx_codigo_turma (codigo_disciplina, id_turma_sigaa),
                INDEX idx_user_id (user_id),
                INDEX idx_periodo_letivo (periodo_letivo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            """
        )
    conn.commit()


def _periodos_nums_str(nums: list[int]) -> str:
    return ",".join(str(n) for n in sorted(set(int(x) for x in nums if int(x) > 0)))


def extrair_aulas(registros: list[dict[str, Any]]) -> list[dict[str, Any]]:
    """Une turmas SIGAA: uma linha por (codigo, curso, id_turma, data)."""
    vistos: dict[tuple, dict[str, Any]] = {}

    for registro in registros:
        nome_curso = str(registro.get("curso") or "").strip()
        disciplinas = registro.get("disciplinas") or []
        if not isinstance(disciplinas, list):
            continue
        for item in disciplinas:
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
                chave = (codigo, nome_curso, id_turma, data_aula.isoformat())
                nums = list(info.get("periodos_nums") or [])
                turno = str(info.get("turno") or "").upper()[:1]
                atual = vistos.get(chave)
                if atual is None:
                    vistos[chave] = {
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
                    if nome_disc and (
                        not atual["disciplina_nome"]
                        or atual["disciplina_nome"] == codigo
                    ):
                        atual["disciplina_nome"] = nome_disc

    return list(vistos.values())


def extrair_docentes(registros: list[dict[str, Any]]) -> list[dict[str, Any]]:
    vistos: dict[tuple, dict[str, Any]] = {}
    for registro in registros:
        nome_curso = str(registro.get("curso") or "").strip()
        disciplinas = registro.get("disciplinas") or []
        if not isinstance(disciplinas, list):
            continue
        for item in disciplinas:
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
                chave = (codigo, nome_curso, id_turma, nome.lower())
                if chave in vistos:
                    continue
                vistos[chave] = {
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


def gravar(
    conn,
    aulas: list[dict[str, Any]],
    docentes: list[dict[str, Any]],
    periodo_letivo: str,
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
                    (codigo_disciplina, disciplina_nome, curso_nome, id_turma_sigaa,
                     data_aula, periodos, turno, periodos_nums, periodo_letivo)
                VALUES
                    (%(codigo_disciplina)s, %(disciplina_nome)s, %(curso_nome)s,
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
                    (codigo_disciplina, disciplina_nome, curso_nome, id_turma_sigaa,
                     docente_nome, docente_email, docente_cpf, user_id, periodo_letivo)
                VALUES
                    (%(codigo_disciplina)s, %(disciplina_nome)s, %(curso_nome)s,
                     %(id_turma_sigaa)s, %(docente_nome)s, %(docente_email)s,
                     %(docente_cpf)s, %(user_id)s, %(periodo_letivo)s)
                """,
                linhas,
            )
            docentes_gravados = len(linhas)

        agora = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        disciplinas = len({a["codigo_disciplina"] for a in aulas})
        dias = len({a["data_aula"] for a in aulas})
        turmas = len({a["id_turma_sigaa"] for a in aulas if a["id_turma_sigaa"]})
        resumo = (
            f"{disciplinas} disciplina(s), {turmas} turma(s) SIGAA, "
            f"{dias} dia(s) de aula, {len(aulas)} encontro(s), "
            f"{docentes_gravados} docente(s) ({vinculados} com usuário)"
        )
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
        "turmas": turmas,
        "docentes": docentes_gravados,
        "docentes_vinculados": vinculados,
    }


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Importa a grade de aulas e docentes do SIGAA."
    )
    parser.add_argument("--unidade", default="31", help="Código da unidade (padrão: 31).")
    parser.add_argument(
        "--periodo",
        default="",
        help="Período letivo AAAA/S (padrão: configuracoes.api_sigaa_periodo_letivo).",
    )
    parser.add_argument(
        "--arquivo",
        type=Path,
        help="Lê matriculados de um JSON em vez de consultar a API.",
    )
    parser.add_argument(
        "--salvar-json",
        action="store_true",
        help=f"Salva a resposta bruta em {JSON_GRADE.name}.",
    )
    args = parser.parse_args()

    env = carregar_config_api()
    periodo = (args.periodo or env.get("API_PERIODO_LETIVO") or "").strip()
    if not periodo:
        print("Erro: configure o período letivo em API SIGAA.", file=sys.stderr)
        return 1

    conn = None
    try:
        if args.arquivo:
            dados = json.loads(args.arquivo.read_text(encoding="utf-8"))
        else:
            print(f"Consultando matriculados (unidade={args.unidade}, período={periodo})…")
            dados = consultar_matriculados(args.unidade, periodo)
            if args.salvar_json:
                garantir_diretorios()
                JSON_GRADE.write_text(
                    json.dumps(dados, ensure_ascii=False, indent=2),
                    encoding="utf-8",
                )
                print(f"JSON salvo em {JSON_GRADE}")

        registros = registros_da_resposta(dados)
        aulas = extrair_aulas(registros)
        docentes = extrair_docentes(registros)
        if not aulas:
            print(
                "Nenhuma aula expandida. Verifique se a resposta traz turno_turma "
                "(mapa matrícula→registro, não lista enxuta).",
                file=sys.stderr,
            )
            return 1

        conn = pymysql.connect(**carregar_config_mysql())
        garantir_tabelas(conn)
        stats = gravar(conn, aulas, docentes, periodo)
        print(f"Período letivo: {periodo}")
        print(
            f"Grade gravada: {stats['disciplinas']} disciplinas, "
            f"{stats['turmas']} turmas SIGAA, {stats['dias']} dias, "
            f"{stats['encontros']} encontros; "
            f"{stats['docentes']} docentes ({stats['docentes_vinculados']} com usuário)."
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

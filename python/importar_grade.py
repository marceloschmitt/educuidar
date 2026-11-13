#!/usr/bin/env python3
"""Consulta matriculados SIGAA e grava a grade de aulas no EduCuidar.

Usa turno_turma para expandir datas efetivas de aula por disciplina.
Acionado pelo botão em Configurações → API SIGAA (ou via CLI).

Uso:
    python3 importar_grade.py
    python3 importar_grade.py --unidade 31
    python3 importar_grade.py --arquivo resposta_matriculas_grade.json
"""

from __future__ import annotations

import argparse
import json
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
from turno_turma import extrair_datas_com_periodos

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
        # Mapa matrícula → registro (formato com turno_turma)
        return [r for r in dados.values() if isinstance(r, dict)]
    if isinstance(dados, list):
        return [r for r in dados if isinstance(r, dict)]
    raise ValueError("Formato inesperado da resposta de matriculados.")


def garantir_tabela(conn) -> None:
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
    conn.commit()


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
            turno = item.get("turno_turma")
            por_data = extrair_datas_com_periodos(turno, incluir_sabado=True)
            if not por_data:
                continue
            for data_aula, periodos in por_data.items():
                chave = (codigo, nome_curso, id_turma, data_aula.isoformat())
                atual = vistos.get(chave)
                if atual is None:
                    vistos[chave] = {
                        "codigo_disciplina": codigo,
                        "disciplina_nome": nome_disc,
                        "curso_nome": nome_curso,
                        "id_turma_sigaa": id_turma,
                        "data_aula": data_aula.isoformat(),
                        "periodos": int(periodos),
                    }
                else:
                    atual["periodos"] = max(int(atual["periodos"]), int(periodos))
                    if nome_disc and (
                        not atual["disciplina_nome"] or atual["disciplina_nome"] == codigo
                    ):
                        atual["disciplina_nome"] = nome_disc

    return list(vistos.values())


def gravar_aulas(conn, aulas: list[dict[str, Any]], periodo_letivo: str) -> dict[str, int]:
    with conn.cursor() as cur:
        cur.execute(
            "DELETE FROM disciplina_aulas WHERE periodo_letivo = %s OR periodo_letivo IS NULL",
            (periodo_letivo,),
        )
        if aulas:
            cur.executemany(
                """
                INSERT INTO disciplina_aulas
                    (codigo_disciplina, disciplina_nome, curso_nome, id_turma_sigaa,
                     data_aula, periodos, periodo_letivo)
                VALUES
                    (%(codigo_disciplina)s, %(disciplina_nome)s, %(curso_nome)s,
                     %(id_turma_sigaa)s, %(data_aula)s, %(periodos)s, %(periodo_letivo)s)
                """,
                [{**a, "periodo_letivo": periodo_letivo} for a in aulas],
            )
        agora = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        disciplinas = len({a["codigo_disciplina"] for a in aulas})
        dias = len({a["data_aula"] for a in aulas})
        turmas = len({a["id_turma_sigaa"] for a in aulas if a["id_turma_sigaa"]})
        resumo = (
            f"{disciplinas} disciplina(s), {turmas} turma(s) SIGAA, "
            f"{dias} dia(s) de aula, {len(aulas)} encontro(s)"
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
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="Importa a grade de aulas do SIGAA.")
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
        if not aulas:
            print(
                "Nenhuma aula expandida. Verifique se a resposta traz turno_turma "
                "(mapa matrícula→registro, não lista enxuta).",
                file=sys.stderr,
            )
            return 1

        conn = pymysql.connect(**carregar_config_mysql())
        garantir_tabela(conn)
        stats = gravar_aulas(conn, aulas, periodo)
        print(f"Período letivo: {periodo}")
        print(
            f"Grade gravada: {stats['disciplinas']} disciplinas, "
            f"{stats['turmas']} turmas SIGAA, {stats['dias']} dias, "
            f"{stats['encontros']} encontros."
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

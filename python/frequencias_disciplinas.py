#!/usr/bin/env python3
"""Grava a frequência de cada aluno por disciplina informada pelo SIGAA.

Para cada disciplina com controle de frequência, guarda aulas (períodos),
faltas, presenças e percentual em frequencia_disciplina. Chamado ao final de
consulta_alunos.py; também pode rodar sozinho sobre resposta_alunos.json.

O SIGAA às vezes retira do total do aluno a aula de um dia abonado (depende
da ordem entre o registro do abono e o da chamada). Como todos os alunos de
uma turma têm as mesmas aulas, cada disciplina usa o maior número de aulas
da turma; faltas vêm do SIGAA (incluem as justificadas) e presenças e
percentual são recalculados. Alunos com matrícula atrasada ou trancamento
mantêm o número do SIGAA, pois para eles a diferença é legítima.

    python3 frequencias_disciplinas.py
    python3 frequencias_disciplinas.py --dry-run
"""

from __future__ import annotations

import argparse
import json
import sys
from datetime import datetime
from pathlib import Path
from typing import Any

import pymysql

from faltas_automaticas import (
    carregar_config_mysql,
    ler_configuracao,
    mapear_alunos_por_cpf,
    parsear_data_api,
    resolver_aluno_id,
)
from paths import JSON_RESPOSTA_ALUNOS, ROOT

ARQUIVO_DDL = ROOT / "sql" / "frequencia_disciplina.sql"

# Ausências especiais em que o aluno de fato teve menos aulas que a turma.
AUSENCIAS_QUE_REDUZEM_AULAS = ("matricula_atrasada", "trancamento_cancelamento")


def _inteiro(valor: Any) -> int:
    try:
        return int(valor)
    except (TypeError, ValueError):
        return 0


def _decimal(valor: Any) -> float | None:
    try:
        return round(float(valor), 2)
    except (TypeError, ValueError):
        return None


def _ano_do_periodo(frequencias: dict[str, Any], ano_padrao: int) -> int:
    info = frequencias.get("info") if isinstance(frequencias.get("info"), dict) else {}
    data_iso = parsear_data_api(str(info.get("data_inicial") or ""))
    return int(data_iso[:4]) if data_iso else ano_padrao


def extrair_frequencias(respostas: list[dict[str, Any]], ano_padrao: int) -> list[dict[str, Any]]:
    """Uma entrada por aluno e ano, com as disciplinas indexadas pelo código."""
    por_aluno: dict[tuple[str, int], dict[str, Any]] = {}

    for resposta in respostas:
        if resposta.get("status") != 200 or not isinstance(resposta.get("dados"), dict):
            continue
        for perfil in resposta["dados"].values():
            if not isinstance(perfil, dict):
                continue
            for curso in perfil.get("cursos", []) or []:
                frequencias = curso.get("frequencias") if isinstance(curso, dict) else None
                if not isinstance(frequencias, dict):
                    continue
                ano = _ano_do_periodo(frequencias, ano_padrao)
                chave = (str(resposta.get("aluno_id") or resposta.get("login") or ""), ano)
                entrada = por_aluno.setdefault(
                    chave,
                    {
                        "aluno_id": resposta.get("aluno_id"),
                        "login": resposta.get("login"),
                        "ano": ano,
                        "disciplinas": {},
                    },
                )
                disciplinas = frequencias.get("disciplinas")
                if not isinstance(disciplinas, dict):
                    continue
                turma = str(curso.get("turma_entrada") or curso.get("id_curso") or "")
                especiais = frequencias.get("ausencias_especiais")
                especiais = especiais if isinstance(especiais, dict) else {}
                manter_aulas = any(especiais.get(tipo) for tipo in AUSENCIAS_QUE_REDUZEM_AULAS)
                for codigo_chave, disc in disciplinas.items():
                    if not isinstance(disc, dict) or not disc.get("possui_controle_frequencia"):
                        continue
                    codigo = str(disc.get("cod_disciplina") or codigo_chave or "").strip()
                    if not codigo:
                        continue
                    freq = disc.get("frequencia") if isinstance(disc.get("frequencia"), dict) else {}
                    linha = {
                        "cod_disciplina": codigo[:50],
                        "disciplina_nome": str(disc.get("nome") or codigo).strip()[:255],
                        "carga_horaria": _inteiro(disc.get("carga_horaria")) or None,
                        "aulas": _inteiro(freq.get("horarios")),
                        "faltas": _inteiro(freq.get("ausencias")),
                        "presencas": _inteiro(freq.get("presencas")),
                        "percentual_frequencia": _decimal(freq.get("percentual_frequencia")),
                        "ultima_aula": parsear_data_api(str(disc.get("ultima_aula_ministrada") or "")),
                        "turma": turma,
                        "manter_aulas": manter_aulas,
                    }
                    atual = entrada["disciplinas"].get(codigo)
                    # Mesmo código em dois cursos do aluno: fica o registro com mais aulas.
                    if atual is None or linha["aulas"] > atual["aulas"]:
                        entrada["disciplinas"][codigo] = linha

    return list(por_aluno.values())


def corrigir_aulas(entradas: list[dict[str, Any]]) -> int:
    """Iguala as aulas de cada disciplina ao maior número da turma; devolve quantas mudaram."""
    maior: dict[tuple[int, str, str], int] = {}
    for entrada in entradas:
        for linha in entrada["disciplinas"].values():
            chave = (entrada["ano"], linha["turma"], linha["cod_disciplina"])
            maior[chave] = max(maior.get(chave, 0), linha["aulas"])

    corrigidas = 0
    for entrada in entradas:
        for linha in entrada["disciplinas"].values():
            if linha["manter_aulas"]:
                continue
            aulas = maior[(entrada["ano"], linha["turma"], linha["cod_disciplina"])]
            if aulas == linha["aulas"]:
                continue
            linha["aulas"] = aulas
            linha["presencas"] = max(aulas - linha["faltas"], 0)
            linha["percentual_frequencia"] = round(linha["presencas"] * 100 / aulas, 2)
            corrigidas += 1
    return corrigidas


def garantir_tabela(conn) -> None:
    ddl = "\n".join(
        linha for linha in ARQUIVO_DDL.read_text(encoding="utf-8").splitlines()
        if not linha.strip().startswith("--")
    ).strip().rstrip(";")
    with conn.cursor() as cur:
        cur.execute(ddl)


def gravar_frequencias(
    respostas: list[dict[str, Any]] | None = None,
    *,
    arquivo_resposta: Path | None = None,
    dry_run: bool = False,
) -> dict[str, Any]:
    if respostas is None:
        caminho = arquivo_resposta or JSON_RESPOSTA_ALUNOS
        respostas = json.loads(caminho.read_text(encoding="utf-8"))
        if not isinstance(respostas, list):
            raise ValueError("resposta_alunos.json deve ser uma lista")

    resumo = {
        "alunos": 0,
        "disciplinas": 0,
        "removidas": 0,
        "corrigidas": 0,
        "sem_aluno": 0,
        "desistentes": 0,
        "dry_run": dry_run,
    }

    conn = pymysql.connect(**carregar_config_mysql())
    try:
        garantir_tabela(conn)
        ano_corrente = ler_configuracao(conn, "ano_corrente")
        ano_padrao = int(ano_corrente) if ano_corrente.isdigit() else datetime.now().year
        alunos_cpf = mapear_alunos_por_cpf(conn)

        with conn.cursor() as cur:
            cur.execute("SELECT id FROM alunos WHERE COALESCE(desistente, 0) = 0")
            alunos_ativos = {int(row["id"]) for row in cur.fetchall()}

            entradas = extrair_frequencias(respostas, ano_padrao)
            resumo["corrigidas"] = corrigir_aulas(entradas)

            for entrada in entradas:
                aluno_id = resolver_aluno_id(entrada, alunos_cpf)
                if not aluno_id:
                    resumo["sem_aluno"] += 1
                    continue
                if aluno_id not in alunos_ativos:
                    resumo["desistentes"] += 1
                    continue
                resumo["alunos"] += 1
                codigos = list(entrada["disciplinas"].keys())

                for linha in entrada["disciplinas"].values():
                    cur.execute(
                        """
                        INSERT INTO frequencia_disciplina
                            (aluno_id, ano, cod_disciplina, disciplina_nome, carga_horaria,
                             aulas, faltas, presencas, percentual_frequencia, ultima_aula)
                        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                        ON DUPLICATE KEY UPDATE
                            disciplina_nome = VALUES(disciplina_nome),
                            carga_horaria = VALUES(carga_horaria),
                            aulas = VALUES(aulas),
                            faltas = VALUES(faltas),
                            presencas = VALUES(presencas),
                            percentual_frequencia = VALUES(percentual_frequencia),
                            ultima_aula = VALUES(ultima_aula),
                            atualizado_em = CURRENT_TIMESTAMP
                        """,
                        (
                            aluno_id, entrada["ano"], linha["cod_disciplina"], linha["disciplina_nome"],
                            linha["carga_horaria"], linha["aulas"], linha["faltas"], linha["presencas"],
                            linha["percentual_frequencia"], linha["ultima_aula"],
                        ),
                    )
                    resumo["disciplinas"] += 1

                # Disciplinas que o SIGAA deixou de informar para o aluno (trancamento, troca de turma).
                sql_remover = "DELETE FROM frequencia_disciplina WHERE aluno_id = %s AND ano = %s"
                params: list[Any] = [aluno_id, entrada["ano"]]
                if codigos:
                    sql_remover += f" AND cod_disciplina NOT IN ({','.join(['%s'] * len(codigos))})"
                    params.extend(codigos)
                cur.execute(sql_remover, params)
                resumo["removidas"] += int(cur.rowcount)

            cur.execute(
                """
                DELETE fd FROM frequencia_disciplina fd
                INNER JOIN alunos a ON a.id = fd.aluno_id
                WHERE COALESCE(a.desistente, 0) <> 0
                """
            )
            resumo["removidas"] += int(cur.rowcount)

        if dry_run:
            conn.rollback()
        else:
            conn.commit()
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()

    return resumo


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("--arquivo", type=Path, default=JSON_RESPOSTA_ALUNOS)
    parser.add_argument("--dry-run", action="store_true", help="Calcula sem gravar no banco")
    args = parser.parse_args()

    if not args.arquivo.exists():
        print(f"Arquivo não encontrado: {args.arquivo}", file=sys.stderr)
        return 1

    resumo = gravar_frequencias(arquivo_resposta=args.arquivo, dry_run=args.dry_run)
    prefixo = "[dry-run] " if args.dry_run else ""
    print(
        f"{prefixo}Frequência por disciplina: {resumo['alunos']} aluno(s), "
        f"{resumo['disciplinas']} disciplina(s) gravada(s), {resumo['removidas']} removida(s), "
        f"{resumo['corrigidas']} com aulas igualadas à turma, "
        f"{resumo['sem_aluno']} sem vínculo no banco, {resumo['desistentes']} desistente(s) ignorado(s)."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

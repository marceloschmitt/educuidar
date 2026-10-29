#!/usr/bin/env python3
"""Atualiza alunos.data_nascimento a partir de resposta_alunos.json (SIGAA).

Uso no servidor:
    python3 atualizar_datas_nascimento.py --dry-run
    python3 atualizar_datas_nascimento.py
    python3 atualizar_datas_nascimento.py --arquivo /caminho/resposta_alunos.json
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from datetime import date, datetime
from pathlib import Path
from typing import Any

try:
    import pymysql
except ImportError:
    print("Instale o pacote pymysql: pip install pymysql", file=sys.stderr)
    sys.exit(1)

from api_auth import carregar_config_mysql
from paths import JSON_RESPOSTA_ALUNOS

DATA_RE = re.compile(r"^(\d{4})-(\d{2})-(\d{2})$")


def log(msg: str, *, erro: bool = False) -> None:
    print(msg, file=sys.stderr if erro else sys.stdout, flush=True)


def normalizar_data(valor: Any) -> str | None:
    if valor is None:
        return None
    if isinstance(valor, date) and not isinstance(valor, datetime):
        return valor.isoformat()
    texto = str(valor).strip()
    if not texto:
        return None
    # ISO puro
    m = DATA_RE.match(texto[:10])
    if m:
        try:
            return date(int(m.group(1)), int(m.group(2)), int(m.group(3))).isoformat()
        except ValueError:
            return None
    # dd/mm/yyyy ou dd-mm-yyyy
    for fmt in ("%d/%m/%Y", "%d-%m-%Y", "%Y/%m/%d"):
        try:
            return datetime.strptime(texto[:10], fmt).date().isoformat()
        except ValueError:
            continue
    return None


def extrair_data_nascimento(item: dict[str, Any]) -> str | None:
    dados = item.get("dados")
    if not isinstance(dados, dict):
        return None

    login = str(item.get("login") or "").strip()
    candidatos: list[Any] = []
    if login and isinstance(dados.get(login), dict):
        candidatos.append(dados[login].get("data_nascimento"))
    for perfil in dados.values():
        if isinstance(perfil, dict) and "data_nascimento" in perfil:
            candidatos.append(perfil.get("data_nascimento"))

    for bruto in candidatos:
        normalizada = normalizar_data(bruto)
        if normalizada:
            return normalizada
    return None


def carregar_pares(caminho: Path) -> list[tuple[int, str, str]]:
    raw = json.loads(caminho.read_text(encoding="utf-8"))
    if not isinstance(raw, list):
        raise ValueError(f"{caminho} deve ser uma lista JSON.")

    pares: list[tuple[int, str, str]] = []
    for item in raw:
        if not isinstance(item, dict):
            continue
        if item.get("status") not in (None, 200, "200"):
            continue
        aluno_id = item.get("aluno_id")
        try:
            aluno_id = int(aluno_id)
        except (TypeError, ValueError):
            continue
        if aluno_id <= 0:
            continue
        data_nasc = extrair_data_nascimento(item)
        if not data_nasc:
            continue
        nome = str(item.get("nome") or "").strip()
        pares.append((aluno_id, data_nasc, nome))
    return pares


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Atualiza data_nascimento dos alunos a partir do JSON do SIGAA."
    )
    parser.add_argument(
        "--arquivo",
        type=Path,
        default=JSON_RESPOSTA_ALUNOS,
        help=f"JSON de origem (padrão: {JSON_RESPOSTA_ALUNOS})",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Só mostra o que seria alterado, sem gravar no banco.",
    )
    args = parser.parse_args()

    if not args.arquivo.is_file():
        log(f"Arquivo não encontrado: {args.arquivo}", erro=True)
        return 1

    try:
        pares = carregar_pares(args.arquivo)
    except (OSError, json.JSONDecodeError, ValueError) as exc:
        log(f"Erro ao ler JSON: {exc}", erro=True)
        return 1

    log(f"Arquivo: {args.arquivo}")
    log(f"Registros com data de nascimento: {len(pares)}")
    if args.dry_run:
        log("Modo dry-run: nenhuma alteração será gravada.")

    conn = pymysql.connect(**carregar_config_mysql())
    ja_tinham_corretos: list[str] = []
    ja_tinham_errados: list[str] = []
    sem_data: list[str] = []
    inexistentes: list[str] = []
    erros: list[str] = []
    atualizados = 0

    try:
        with conn.cursor() as cur:
            for aluno_id, data_nasc, nome in pares:
                cur.execute(
                    "SELECT id, nome, data_nascimento FROM alunos WHERE id = %s",
                    (aluno_id,),
                )
                row = cur.fetchone()
                if not row:
                    inexistentes.append(
                        f"id={aluno_id} ({nome}) → {data_nasc}"
                    )
                    continue

                atual = normalizar_data(row.get("data_nascimento"))
                nome_bd = str(row.get("nome") or nome).strip()
                rotulo = f"id={aluno_id} {nome_bd}"

                if atual == data_nasc:
                    ja_tinham_corretos.append(f"{rotulo}: {atual}")
                    continue

                if atual:
                    ja_tinham_errados.append(
                        f"{rotulo}: banco={atual} → SIGAA={data_nasc}"
                    )
                else:
                    sem_data.append(f"{rotulo}: (vazio) → {data_nasc}")

                if args.dry_run:
                    atualizados += 1
                    continue

                try:
                    cur.execute(
                        "UPDATE alunos SET data_nascimento = %s WHERE id = %s",
                        (data_nasc, aluno_id),
                    )
                    atualizados += 1
                except Exception as exc:  # noqa: BLE001 — continua os demais
                    erros.append(f"{rotulo}: {exc}")

        if args.dry_run:
            conn.rollback()
        else:
            conn.commit()
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()

    def listar(titulo: str, linhas: list[str]) -> None:
        log("")
        log(f"=== {titulo} ({len(linhas)}) ===")
        if not linhas:
            log("(nenhum)")
            return
        for linha in linhas:
            log(f"  {linha}")

    listar("Já tinham data e estava correta", ja_tinham_corretos)
    listar("Já tinham data e estava ERRADA", ja_tinham_errados)
    listar("Não tinham data (serão preenchidos)", sem_data)
    if inexistentes:
        listar("Sem aluno correspondente no banco", inexistentes)
    if erros:
        listar("Erros ao atualizar", erros)

    log("")
    log(
        "Resumo: "
        f"{len(ja_tinham_corretos)} já corretos, "
        f"{len(ja_tinham_errados)} com data errada, "
        f"{len(sem_data)} sem data, "
        f"{atualizados} "
        f"{'seriam atualizados' if args.dry_run else 'atualizados'}, "
        f"{len(inexistentes)} sem aluno no banco, "
        f"{len(erros)} erro(s)."
    )
    return 1 if erros else 0


if __name__ == "__main__":
    raise SystemExit(main())

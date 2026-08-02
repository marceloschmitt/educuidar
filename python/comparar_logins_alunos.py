#!/usr/bin/env python3
"""
Compara alunos do banco com o JSON de matrículas/logins.

O match é feito pelo nome (nome ou nome_social do BD x Nome do JSON).
Para matches sem dúvida:
  - atualiza alunos.cpf com o Login do JSON
  - atualiza alunos.numero_matricula com a Matricula do JSON
  - atualiza alunos.nome com o Nome do JSON (nome correto)

Gera relatório de alunos sem login e casos em dúvida (não atualizados).
"""

from __future__ import annotations

import argparse
import json
import re
import sys
import unicodedata
from collections import defaultdict
from difflib import SequenceMatcher
from pathlib import Path

try:
    import pymysql
except ImportError:
    print("Instale o pacote pymysql: pip install pymysql", file=sys.stderr)
    sys.exit(1)

BASE_DIR = Path(__file__).resolve().parent
PROJECT_DIR = BASE_DIR.parent
JSON_PATH = BASE_DIR / "resposta_matriculas.json"
CONFIG_PATH = PROJECT_DIR / "config" / "config.php"
OUTPUT_PATH = BASE_DIR / "alunos_sem_login.txt"

# Similaridade mínima para aceitar match aproximado (ex.: YASMIM x YASMIN)
SIMILARIDADE_OK = 0.92
# Abaixo disso nem entra como candidato
SIMILARIDADE_MIN = 0.85


def carregar_config_mysql(config_path: Path) -> dict:
    texto = config_path.read_text(encoding="utf-8")
    valores = {}
    for chave in ("host", "db_name", "username", "password"):
        match = re.search(rf"'{chave}'\s*=>\s*'([^']*)'", texto)
        if not match:
            raise ValueError(f"Não foi possível ler '{chave}' em {config_path}")
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


def normalizar_nome(valor) -> str:
    if not valor:
        return ""
    texto = unicodedata.normalize("NFKD", str(valor))
    texto = "".join(ch for ch in texto if not unicodedata.combining(ch))
    texto = texto.upper()
    texto = re.sub(r"[^A-Z0-9\s]", " ", texto)
    texto = re.sub(r"\s+", " ", texto).strip()
    return texto


def formatar_nome_titulo(valor) -> str:
    """Primeira letra de cada palavra maiúscula; demais minúsculas (preserva acentos)."""
    if not valor:
        return ""
    texto = re.sub(r"\s+", " ", str(valor).strip())
    partes = []
    for palavra in texto.split(" "):
        if not palavra:
            continue
        partes.append(palavra[0].upper() + palavra[1:].lower() if len(palavra) > 1 else palavra.upper())
    return " ".join(partes)


def normalizar_cpf(valor) -> str:
    digitos = re.sub(r"\D", "", str(valor or ""))
    return digitos


def normalizar_matricula(valor) -> str:
    if valor is None:
        return ""
    texto = str(valor).strip()
    if not texto or texto.lower() in {"none", "null"}:
        return ""
    texto = re.sub(r"\s+", "", texto)
    if texto.isdigit():
        return str(int(texto))
    return texto


def carregar_json(path: Path) -> list[dict]:
    with path.open(encoding="utf-8") as fh:
        dados = json.load(fh)
    if not isinstance(dados, list):
        raise ValueError("O JSON deve ser uma lista de objetos")
    return dados


def indexar_json_por_nome(registros: list[dict]):
    por_nome: dict[str, list[dict]] = defaultdict(list)
    entradas = []

    for item in registros:
        nome_norm = normalizar_nome(item.get("Nome"))
        if not nome_norm:
            continue
        entrada = {
            "nome": item.get("Nome") or "",
            "nome_norm": nome_norm,
            "login": normalizar_cpf(item.get("Login")),
            "matricula": normalizar_matricula(item.get("Matricula")),
            "email": item.get("Email") or "",
        }
        por_nome[nome_norm].append(entrada)
        entradas.append(entrada)

    return por_nome, entradas


def buscar_alunos(conn) -> list[dict]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id, nome, nome_social, numero_matricula, cpf, email
            FROM alunos
            WHERE COALESCE(desistente, 0) = 0
            ORDER BY nome ASC
            """
        )
        return list(cur.fetchall())


def similaridade_nomes(a: str, b: str) -> float:
    if not a or not b:
        return 0.0
    if a == b:
        return 1.0
    return SequenceMatcher(None, a, b).ratio()


def tokens_quase_iguais(a: str, b: str) -> bool:
    """
    True se os nomes têm os mesmos tokens na mesma ordem,
    permitindo 1 caractere de diferença em no máximo 1 token
    (ex.: YASMIM x YASMIN).
    """
    ta = a.split()
    tb = b.split()
    if len(ta) != len(tb) or not ta:
        return False
    diferencas = 0
    for xa, xb in zip(ta, tb):
        if xa == xb:
            continue
        if abs(len(xa) - len(xb)) > 1:
            return False
        # Distância de edição <= 1
        if SequenceMatcher(None, xa, xb).ratio() < 0.8:
            return False
        # Conta diferença de caracteres simples
        if len(xa) == len(xb):
            diffs = sum(1 for ca, cb in zip(xa, xb) if ca != cb)
            if diffs > 1:
                return False
        else:
            # inserção/remoção de 1 letra
            if SequenceMatcher(None, xa, xb).ratio() < 0.85:
                return False
        diferencas += 1
        if diferencas > 1:
            return False
    return diferencas <= 1


def dedupar_matches(matches: list[dict]) -> list[dict]:
    unicos = {}
    for m in matches:
        chave = (m["login"], m["matricula"], m["nome"])
        unicos[chave] = m
    return list(unicos.values())


def coletar_matches_exatos(nomes_norm: list[str], por_nome: dict) -> list[dict]:
    matches = []
    for n in nomes_norm:
        matches.extend(por_nome.get(n, []))
    return dedupar_matches(matches)


def coletar_matches_aproximados(
    nomes_norm: list[str], entradas_json: list[dict]
) -> list[tuple[dict, float]]:
    """Retorna lista de (entrada, similaridade) dos melhores candidatos aproximados."""
    candidatos: dict[tuple, tuple[dict, float]] = {}

    for nome_bd in nomes_norm:
        for entrada in entradas_json:
            nome_json = entrada["nome_norm"]
            sim = similaridade_nomes(nome_bd, nome_json)
            quase_token = tokens_quase_iguais(nome_bd, nome_json)
            if sim < SIMILARIDADE_MIN and not quase_token:
                continue
            if quase_token and sim < SIMILARIDADE_OK:
                sim = max(sim, SIMILARIDADE_OK)
            chave = (entrada["login"], entrada["matricula"], entrada["nome"])
            atual = candidatos.get(chave)
            if atual is None or sim > atual[1]:
                candidatos[chave] = (entrada, sim)

    ordenados = sorted(candidatos.values(), key=lambda x: x[1], reverse=True)
    if not ordenados:
        return []

    # Mantém só os do melhor "patamar" (diferença pequena do topo)
    melhor = ordenados[0][1]
    return [(e, s) for e, s in ordenados if s >= melhor - 0.02]


def classificar_matches(matches: list[dict], aproximado: bool, similaridade: float = 1.0) -> dict:
    """Define status/motivo/login/matrícula/nome a partir da lista de matches."""
    resultado = {
        "status": "",
        "motivo": "",
        "login": "",
        "matricula_json": "",
        "nome_json": "",
        "matches_json": matches,
        "similaridade": similaridade,
    }

    logins = {m["login"] for m in matches if m["login"]}
    matriculas = {m["matricula"] for m in matches if m["matricula"]}
    nomes_json = {m["nome"].strip() for m in matches if (m.get("nome") or "").strip()}
    prefixo = "aproximado" if aproximado else "exato"

    if len(logins) == 0:
        resultado["status"] = "duvida"
        resultado["motivo"] = f"Nome ({prefixo}) encontrado no JSON, mas Login vazio"
        return resultado

    if len(logins) > 1:
        resultado["status"] = "duvida"
        resultado["motivo"] = (
            f"Nome ({prefixo}) com {len(logins)} logins diferentes no JSON"
        )
        return resultado

    login = next(iter(logins))
    resultado["login"] = login

    if len(matriculas) == 0:
        resultado["status"] = "duvida"
        resultado["motivo"] = (
            f"Login único ({prefixo}), mas matrícula vazia no JSON"
        )
        return resultado

    if len(matriculas) > 1:
        resultado["status"] = "duvida"
        resultado["motivo"] = (
            f"Login único ({prefixo}), porém {len(matriculas)} matrículas diferentes no JSON"
        )
        return resultado

    if len(nomes_json) == 0:
        resultado["status"] = "duvida"
        resultado["motivo"] = f"Login único ({prefixo}), mas Nome vazio no JSON"
        return resultado

    if len(nomes_json) > 1:
        # Mesmo login/matrícula com grafias diferentes no JSON: usa a primeira
        # apenas se forem equivalentes após normalização
        norms = {normalizar_nome(n) for n in nomes_json}
        if len(norms) > 1:
            resultado["status"] = "duvida"
            resultado["motivo"] = (
                f"Login único ({prefixo}), porém nomes distintos no JSON"
            )
            return resultado

    resultado["matricula_json"] = next(iter(matriculas))
    resultado["nome_json"] = formatar_nome_titulo(next(iter(nomes_json)))
    resultado["status"] = "ok"
    if aproximado:
        resultado["motivo"] = (
            f"Match aproximado por nome (similaridade {similaridade:.1%}): "
            f"{resultado['nome_json']}"
        )
    elif len(matches) > 1:
        resultado["motivo"] = (
            f"Match por nome OK ({len(matches)} registros JSON com mesmo login/matrícula)"
        )
    else:
        resultado["motivo"] = "Match por nome OK"
    return resultado


def avaliar_aluno(aluno: dict, por_nome: dict, entradas_json: list[dict]) -> dict:
    nome = aluno.get("nome") or ""
    nome_social = aluno.get("nome_social") or ""
    nomes_norm = []
    for candidato in (nome, nome_social):
        n = normalizar_nome(candidato)
        if n and n not in nomes_norm:
            nomes_norm.append(n)

    resultado = {
        "id": aluno["id"],
        "nome": nome,
        "nome_social": nome_social,
        "matricula_bd": aluno.get("numero_matricula") or "",
        "cpf_bd": aluno.get("cpf") or "",
        "email_bd": aluno.get("email") or "",
        "status": "",
        "motivo": "",
        "login": "",
        "matricula_json": "",
        "nome_json": "",
        "matches_json": [],
        "similaridade": 0.0,
    }

    if not nomes_norm:
        resultado["status"] = "duvida"
        resultado["motivo"] = "Aluno sem nome válido no BD para comparação"
        return resultado

    matches = coletar_matches_exatos(nomes_norm, por_nome)
    if matches:
        classif = classificar_matches(matches, aproximado=False, similaridade=1.0)
        resultado.update(classif)
        return resultado

    aproximados = coletar_matches_aproximados(nomes_norm, entradas_json)
    if not aproximados:
        resultado["status"] = "sem_login"
        resultado["motivo"] = "Nenhum registro com nome igual ou suficientemente parecido no JSON"
        return resultado

    melhor_sim = aproximados[0][1]
    matches_aprox = [e for e, s in aproximados if s >= SIMILARIDADE_OK or tokens_quase_iguais(
        nomes_norm[0], e["nome_norm"]
    )]
    # Se o melhor está abaixo do limiar OK, vai para dúvida
    if melhor_sim < SIMILARIDADE_OK and not any(
        tokens_quase_iguais(n, e["nome_norm"]) for n in nomes_norm for e, _ in aproximados
    ):
        resultado["status"] = "duvida"
        resultado["motivo"] = (
            f"Possível nome parecido (similaridade {melhor_sim:.1%}), revisar manualmente"
        )
        resultado["matches_json"] = [e for e, _ in aproximados[:5]]
        resultado["similaridade"] = melhor_sim
        return resultado

    if not matches_aprox:
        matches_aprox = [aproximados[0][0]]

    # Se há vários candidatos aproximados com logins distintos → dúvida
    classif = classificar_matches(dedupar_matches(matches_aprox), aproximado=True, similaridade=melhor_sim)
    resultado.update(classif)
    return resultado


def atualizar_aluno(conn, aluno_id: int, cpf: str, matricula: str, nome: str) -> None:
    with conn.cursor() as cur:
        cur.execute(
            """
            UPDATE alunos
            SET cpf = %s, numero_matricula = %s, nome = %s
            WHERE id = %s
            """,
            (cpf, matricula, nome, aluno_id),
        )


def formatar_linha(item: dict) -> str:
    linhas = [
        f"ID: {item['id']}",
        f"Nome: {item['nome']}",
    ]
    if item["nome_social"]:
        linhas.append(f"Nome social: {item['nome_social']}")
    if item.get("matricula_bd"):
        linhas.append(f"Matrícula BD: {item['matricula_bd']}")
    if item.get("cpf_bd"):
        linhas.append(f"CPF BD: {item['cpf_bd']}")
    if item.get("email_bd"):
        linhas.append(f"E-mail BD: {item['email_bd']}")
    linhas.append(f"Status: {item['status']}")
    linhas.append(f"Motivo: {item['motivo']}")
    if item.get("nome_json"):
        linhas.append(f"Nome JSON: {item['nome_json']}")
    if item.get("login"):
        linhas.append(f"Login/CPF JSON: {item['login']}")
    if item.get("matricula_json"):
        linhas.append(f"Matrícula JSON: {item['matricula_json']}")
    if item.get("similaridade"):
        linhas.append(f"Similaridade: {item['similaridade']:.1%}")
    if item["matches_json"]:
        linhas.append("Matches no JSON:")
        for m in item["matches_json"]:
            linhas.append(
                f"  - Nome={m['nome']} | Login={m['login']} | "
                f"Matricula={m['matricula']} | Email={m['email']}"
            )
    return "\n".join(linhas)


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Compara alunos com JSON e atualiza nome, CPF e matrícula nos matches."
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Apenas simula: não grava alterações no banco",
    )
    args = parser.parse_args()

    if not JSON_PATH.exists():
        print(f"Arquivo não encontrado: {JSON_PATH}", file=sys.stderr)
        return 1
    if not CONFIG_PATH.exists():
        print(f"Config não encontrada: {CONFIG_PATH}", file=sys.stderr)
        return 1

    registros_json = carregar_json(JSON_PATH)
    por_nome, entradas_json = indexar_json_por_nome(registros_json)

    cfg = carregar_config_mysql(CONFIG_PATH)
    try:
        conn = pymysql.connect(**cfg)
    except pymysql.MySQLError as exc:
        print(
            f"Não foi possível conectar ao MySQL "
            f"({cfg['user']}@{cfg['host']}/{cfg['database']}): {exc}",
            file=sys.stderr,
        )
        return 1

    atualizados = []
    sem_login = []
    duvidas = []
    inalterados = 0

    try:
        alunos = buscar_alunos(conn)

        for aluno in alunos:
            r = avaliar_aluno(aluno, por_nome, entradas_json)
            if r["status"] != "ok":
                if r["status"] == "sem_login":
                    sem_login.append(r)
                else:
                    duvidas.append(r)
                continue

            cpf_atual = normalizar_cpf(aluno.get("cpf"))
            mat_atual = normalizar_matricula(aluno.get("numero_matricula"))
            nome_atual = (aluno.get("nome") or "").strip()
            nome_novo = formatar_nome_titulo(r.get("nome_json") or "")
            r["nome_json"] = nome_novo
            precisa = (
                cpf_atual != r["login"]
                or mat_atual != r["matricula_json"]
                or nome_atual != nome_novo
            )

            if not precisa:
                inalterados += 1
                continue

            if not args.dry_run:
                atualizar_aluno(
                    conn, r["id"], r["login"], r["matricula_json"], nome_novo
                )

            r["motivo"] = (
                f"{'[DRY-RUN] ' if args.dry_run else ''}"
                f"Atualizar nome '{nome_atual}' -> '{nome_novo}'; "
                f"CPF {cpf_atual or '(vazio)'} -> {r['login']}; "
                f"matrícula {mat_atual or '(vazia)'} -> {r['matricula_json']}"
            )
            atualizados.append(r)

        if not args.dry_run:
            conn.commit()
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()

    with OUTPUT_PATH.open("w", encoding="utf-8") as out:
        out.write("Comparação alunos BD x resposta_matriculas.json (match por nome)\n")
        out.write("=" * 60 + "\n")
        if args.dry_run:
            out.write("Modo: DRY-RUN (nenhuma alteração gravada)\n")
        out.write(f"Alunos no BD (não desistentes): {len(alunos)}\n")
        out.write(f"Registros no JSON: {len(registros_json)}\n")
        out.write(f"Atualizados: {len(atualizados)}\n")
        out.write(f"Já estavam corretos: {inalterados}\n")
        out.write(f"Sem login: {len(sem_login)}\n")
        out.write(f"Em dúvida: {len(duvidas)}\n")
        out.write("\n")

        out.write("#" * 60 + "\n")
        out.write("ALUNOS ATUALIZADOS (nome + CPF + matrícula)\n")
        out.write("#" * 60 + "\n\n")
        if not atualizados:
            out.write("(nenhum)\n\n")
        else:
            for item in atualizados:
                out.write(formatar_linha(item))
                out.write("\n" + "-" * 40 + "\n\n")

        out.write("#" * 60 + "\n")
        out.write("ALUNOS SEM LOGIN ENCONTRADO\n")
        out.write("#" * 60 + "\n\n")
        if not sem_login:
            out.write("(nenhum)\n\n")
        else:
            for item in sem_login:
                out.write(formatar_linha(item))
                out.write("\n" + "-" * 40 + "\n\n")

        out.write("#" * 60 + "\n")
        out.write("CASOS EM DÚVIDA (não atualizados)\n")
        out.write("#" * 60 + "\n\n")
        if not duvidas:
            out.write("(nenhum)\n\n")
        else:
            for item in duvidas:
                out.write(formatar_linha(item))
                out.write("\n" + "-" * 40 + "\n\n")

    modo = "DRY-RUN" if args.dry_run else "GRAVADO"
    print(f"Relatório gerado: {OUTPUT_PATH}")
    print(
        f"[{modo}] Atualizados={len(atualizados)} | "
        f"Já corretos={inalterados} | "
        f"Sem login={len(sem_login)} | Dúvida={len(duvidas)}"
    )
    print()

    print("#" * 60)
    print("CASOS EM DÚVIDA (não atualizados)")
    print("#" * 60)
    if not duvidas:
        print("(nenhum)")
    else:
        for item in duvidas:
            print(formatar_linha(item))
            print("-" * 40)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

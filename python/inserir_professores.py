#!/usr/bin/env python3
"""Insere no EduCuidar os professores dos cursos integrados (SIGAA).

Consulta a API de matriculados filtrando pelo nível do curso (N = integrado
ao ensino médio), monta nome, username e e-mail de cada docente e cria no
banco, com autenticação LDAP, os que ainda não existem. Usuários que já
existem não são alterados.

Um professor é considerado existente quando o username, o e-mail ou o nome
completo já estão cadastrados em users.

Requisitos:
  - Credenciais em Configurações → API SIGAA (exceto com --arquivo)
  - pymysql e acesso ao MySQL (config/config.php)

Uso:
    python3 inserir_professores.py
    python3 inserir_professores.py --dry-run
    python3 inserir_professores.py --tipo-usuario Professor
    python3 inserir_professores.py --arquivo resposta_matriculados.json
"""

from __future__ import annotations

import argparse
import json
import re
import sys
import unicodedata
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

DOMINIO_EMAIL_PADRAO = "poa.ifrs.edu.br"
TIPO_USUARIO_PADRAO = "Professor"
TIMEOUT_SEGUNDOS = 180
PARTICULAS = {"de", "da", "do", "das", "dos", "e"}


def formatar_nome(nome: str) -> str:
    palavras = nome.strip().lower().split()
    formatadas = []
    for i, palavra in enumerate(palavras):
        if i > 0 and palavra in PARTICULAS:
            formatadas.append(palavra)
        else:
            formatadas.append(
                re.sub(r"(^|[-'’])(\w)", lambda m: m.group(1) + m.group(2).upper(), palavra)
            )
    return " ".join(formatadas)


def chave_ordenacao(nome: str) -> str:
    sem_acento = unicodedata.normalize("NFKD", nome)
    return "".join(c for c in sem_acento if not unicodedata.combining(c)).lower()


def chave_nome(nome: str) -> str:
    return " ".join(chave_ordenacao(nome).split())


def primeiro_e_ultimo(nome: str) -> tuple[str, str]:
    palavras = [
        re.sub(r"[^a-z]", "", chave_ordenacao(p))
        for p in nome.split()
        if p.lower() not in PARTICULAS
    ]
    palavras = [p for p in palavras if p]
    if not palavras:
        return "", ""
    return palavras[0], palavras[-1]


def montar_professor(nome_api: str, email_api: str, dominio: str) -> dict[str, str]:
    nome = formatar_nome(nome_api)
    primeiro, ultimo = primeiro_e_ultimo(nome)
    email = email_api.strip().lower()
    if not email.endswith(f"@{dominio.lower()}"):
        email = f"{primeiro}.{ultimo}@{dominio}"
    return {
        "nome": nome,
        "username": f"{primeiro}{ultimo}",
        "email": email,
    }


def montar_url(base_url: str, unidade: str, curso_nivel: str) -> str:
    parametros = urlencode(
        {"unidade": unidade, "matriculado": "sim", "curso_nivel": curso_nivel}
    )
    return f"{base_url.rstrip('/')}/api/v1/sig/sigaa/matriculados?{parametros}"


def consultar_api(unidade: str, curso_nivel: str) -> Any:
    env = carregar_config_api()
    base_url = env.get("API_BASE_URL") or DEFAULTS["api_sigaa_base_url"]
    token = obter_access_token(env)
    request = Request(
        montar_url(base_url, unidade, curso_nivel),
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
        dados = dados["data"]
    elif isinstance(dados, dict):
        dados = list(dados.values())
    if not isinstance(dados, list):
        raise ValueError("Formato inesperado: esperava mapa ou lista de matrículas.")
    return [r for r in dados if isinstance(r, dict)]


def extrair_professores(
    registros: list[dict[str, Any]], curso_nivel: str, dominio: str
) -> list[dict[str, str]]:
    nivel = curso_nivel.strip().upper()
    por_chave: dict[str, dict[str, str]] = {}
    for registro in registros:
        nivel_registro = str(registro.get("curso_nivel") or "").strip().upper()
        if nivel and nivel_registro and nivel_registro != nivel:
            continue
        for disciplina in registro.get("disciplinas") or []:
            if not isinstance(disciplina, dict):
                continue
            for docente in disciplina.get("docentes") or []:
                if not isinstance(docente, dict):
                    continue
                nome = str(docente.get("docente") or "").strip()
                if nome == "":
                    continue
                cpf = re.sub(r"\D", "", str(docente.get("cpf_docente") or ""))
                chave = cpf or chave_ordenacao(nome)
                if chave not in por_chave:
                    email_api = str(docente.get("email_docente") or "")
                    por_chave[chave] = montar_professor(nome, email_api, dominio)
    return sorted(por_chave.values(), key=lambda p: chave_ordenacao(p["nome"]))


def buscar_tipo_usuario(conn, tipo: str) -> dict[str, Any]:
    with conn.cursor() as cur:
        if tipo.isdigit():
            cur.execute("SELECT id, nome FROM user_types WHERE id = %s", (int(tipo),))
        else:
            cur.execute("SELECT id, nome FROM user_types WHERE nome = %s", (tipo,))
        row = cur.fetchone()
        if row:
            return row
        cur.execute("SELECT id, nome FROM user_types ORDER BY nome")
        disponiveis = ", ".join(f"{r['id']} = {r['nome']}" for r in cur.fetchall())
    raise ValueError(
        f"Tipo de usuário '{tipo}' não encontrado. Disponíveis: {disponiveis or 'nenhum'}."
    )


def carregar_usuarios(conn) -> list[dict[str, Any]]:
    with conn.cursor() as cur:
        cur.execute("SELECT id, username, email, full_name FROM users")
        return list(cur.fetchall())


def encontrar_existente(
    professor: dict[str, str],
    por_username: dict[str, dict[str, Any]],
    por_email: dict[str, dict[str, Any]],
    por_nome: dict[str, dict[str, Any]],
) -> tuple[str, dict[str, Any]] | None:
    candidatos = (
        ("username", por_username.get(professor["username"].lower())),
        ("e-mail", por_email.get(professor["email"].lower())),
        ("nome", por_nome.get(chave_nome(professor["nome"]))),
    )
    for motivo, usuario in candidatos:
        if usuario:
            return motivo, usuario
    return None


def inserir_professores(
    conn, professores: list[dict[str, str]], tipo_id: int, dry_run: bool
) -> tuple[list[dict[str, str]], list[tuple[dict[str, str], str, dict[str, Any]]]]:
    usuarios = carregar_usuarios(conn)
    por_username = {str(u["username"] or "").lower(): u for u in usuarios}
    por_email = {str(u["email"] or "").lower(): u for u in usuarios}
    por_nome = {chave_nome(str(u["full_name"] or "")): u for u in usuarios}

    adicionados: list[dict[str, str]] = []
    existentes: list[tuple[dict[str, str], str, dict[str, Any]]] = []

    with conn.cursor() as cur:
        for professor in professores:
            if not professor["username"]:
                continue
            encontrado = encontrar_existente(professor, por_username, por_email, por_nome)
            if encontrado:
                existentes.append((professor, encontrado[0], encontrado[1]))
                continue

            if not dry_run:
                cur.execute(
                    """
                    INSERT INTO users (username, email, password, full_name, auth_type)
                    VALUES (%s, %s, NULL, %s, 'ldap')
                    """,
                    (professor["username"], professor["email"], professor["nome"]),
                )
                cur.execute(
                    "INSERT INTO user_user_types (user_id, user_type_id) VALUES (%s, %s)",
                    (cur.lastrowid, tipo_id),
                )

            novo = {"username": professor["username"], "email": professor["email"]}
            por_username[professor["username"].lower()] = novo
            por_email[professor["email"].lower()] = novo
            por_nome[chave_nome(professor["nome"])] = novo
            adicionados.append(professor)

    if dry_run:
        conn.rollback()
    else:
        conn.commit()
    return adicionados, existentes


def imprimir_tabela(professores: list[dict[str, str]]) -> None:
    colunas = ("nome", "username", "email")
    larguras = {c: max([len(c)] + [len(p[c]) for p in professores]) for c in colunas}
    print("  ".join(c.capitalize().ljust(larguras[c]) for c in colunas).rstrip())
    print("  ".join("-" * larguras[c] for c in colunas))
    for professor in professores:
        print("  ".join(professor[c].ljust(larguras[c]) for c in colunas).rstrip())


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Insere os professores dos cursos integrados que ainda não existem."
    )
    parser.add_argument("--unidade", default="31", help="Código da unidade no SIGAA (padrão: 31).")
    parser.add_argument("--curso-nivel", default="N", help="Nível do curso (padrão: N = integrado).")
    parser.add_argument(
        "--tipo-usuario",
        default=TIPO_USUARIO_PADRAO,
        help=f"Nome ou id do tipo de usuário dos novos professores (padrão: {TIPO_USUARIO_PADRAO}).",
    )
    parser.add_argument(
        "--dominio",
        default=DOMINIO_EMAIL_PADRAO,
        help=(
            "Domínio do e-mail: usa o e-mail da API quando for deste domínio, "
            f"senão gera primeiro.ultimo@dominio (padrão: {DOMINIO_EMAIL_PADRAO})."
        ),
    )
    parser.add_argument(
        "--arquivo",
        type=Path,
        help="Lê uma resposta de matriculados já salva em vez de consultar a API.",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Mostra quem seria adicionado, sem gravar no banco.",
    )
    args = parser.parse_args()

    conn = None
    try:
        conn = pymysql.connect(**carregar_config_mysql())
        tipo = buscar_tipo_usuario(conn, args.tipo_usuario.strip())

        if args.arquivo:
            dados = json.loads(args.arquivo.read_text(encoding="utf-8"))
        else:
            dados = consultar_api(args.unidade, args.curso_nivel)
        professores = extrair_professores(
            registros_da_resposta(dados), args.curso_nivel, args.dominio
        )
        adicionados, existentes = inserir_professores(
            conn, professores, int(tipo["id"]), args.dry_run
        )
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
        print(f"Erro no banco de dados: {error}", file=sys.stderr)
        return 1
    except (OSError, ValueError, RuntimeError, json.JSONDecodeError) as error:
        print(f"Erro: {error}", file=sys.stderr)
        return 1
    finally:
        if conn:
            conn.close()

    acao = "seriam adicionados" if args.dry_run else "adicionados"
    print(f"Professores encontrados na API: {len(professores)}")
    print(f"Tipo de usuário: {tipo['nome']} | Autenticação: LDAP")
    print()
    if adicionados:
        print(f"Professores {acao} ({len(adicionados)}):")
        imprimir_tabela(adicionados)
    else:
        print("Nenhum professor novo: todos já estão cadastrados.")

    diferentes = [
        (p, motivo, u)
        for p, motivo, u in existentes
        if chave_nome(str(u.get("full_name") or p["nome"])) != chave_nome(p["nome"])
    ]
    print()
    print(f"Já cadastrados (não alterados): {len(existentes)}")
    if diferentes:
        print()
        print("Atenção: username ou e-mail já usados por um usuário com outro nome:")
        for professor, motivo, usuario in diferentes:
            valor = professor["username"] if motivo == "username" else professor["email"]
            print(
                f"  {professor['nome']} ({motivo} {valor})"
                f" -> usuário existente: {usuario.get('full_name')}"
            )
    if args.dry_run:
        print()
        print("Dry-run: nada foi gravado no banco.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

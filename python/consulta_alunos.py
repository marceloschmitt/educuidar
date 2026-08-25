#!/usr/bin/env python3
"""Consulta detalhes dos alunos no webservice SIGAA (por CPF/login).

Busca no banco EduCuidar os alunos não desistentes que possuem CPF,
consulta a API para cada login (CPF) em paralelo, salva o resultado em
resposta_alunos.json e registra falhas no log (stdout/stderr).

Requisitos:
  - Credenciais, período e datas em Configurações → API SIGAA
  - pymysql e acesso ao MySQL (config/config.php)

Uso:
    python3 consulta_alunos.py
    python3 consulta_alunos.py --dry-run   # só lista quem seria consultado
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
from urllib.request import Request, urlopen

try:
    import pymysql
except ImportError:
    print("Instale o pacote pymysql: pip install pymysql", file=sys.stderr)
    sys.exit(1)

from api_auth import (
    USER_AGENT,
    carregar_env,
    env_ou_padrao,
    obter_access_token,
    ssl_context,
    verificar_ssl,
)
from paths import (
    ARQUIVO_CONFIG_PHP,
    JSON_RESPOSTA_ALUNOS,
    garantir_diretorios,
)

ARQUIVO_SAIDA = JSON_RESPOSTA_ALUNOS

CONCORRENCIA = 50
TIMEOUT_SEGUNDOS = 120
TENTATIVAS = 3
LIMITE_CONSULTAS = None

API_URL_ALUNOS_BASE = ""
API_TOKEN = ""
PERIODO_LETIVO = ""
FREQUENCIA_DATA_INICIAL = ""
FREQUENCIA_DATA_FINAL = ""
_ENV: dict[str, str] = {}


def agora() -> str:
    return datetime.now().strftime("%d/%m/%Y %H:%M:%S")


def log(msg: str = "", *, erro: bool = False) -> None:
    destino = sys.stderr if erro else sys.stdout
    if msg == "":
        print(file=destino)
        return
    print(f"[{agora()}] {msg}", file=destino)

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
    }


def normalizar_cpf(valor) -> str:
    return re.sub(r"\D", "", str(valor or ""))


def buscar_alunos_com_cpf(conn) -> list[dict[str, Any]]:
    """Alunos não desistentes com CPF preenchido (login da API)."""
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id, nome, nome_social, cpf, numero_matricula, email
            FROM alunos
            WHERE COALESCE(desistente, 0) = 0
              AND cpf IS NOT NULL
              AND TRIM(cpf) <> ''
            ORDER BY nome ASC
            """
        )
        rows = list(cur.fetchall())

    alunos = []
    for row in rows:
        login = normalizar_cpf(row.get("cpf"))
        if len(login) != 11:
            continue
        alunos.append(
            {
                "id": row["id"],
                "Nome": row.get("nome") or "",
                "NomeSocial": row.get("nome_social") or "",
                "Login": login,
                "Matricula": row.get("numero_matricula") or "",
                "Email": row.get("email") or "",
            }
        )
    return alunos


def consultar_webservice(url: str, token: str, timeout: int = TIMEOUT_SEGUNDOS) -> tuple[int, str]:
    request = Request(
        url,
        headers={
            "Accept": "application/json",
            "Authorization": f"Bearer {token}",
            "User-Agent": USER_AGENT,
        },
        method="GET",
    )

    context = ssl_context(_ENV)

    with urlopen(request, timeout=timeout, context=context) as response:
        status = response.getcode()
        body = response.read().decode("utf-8")

    return status, body


def montar_url_alunos(login: str) -> str:
    from urllib.parse import quote

    return (
        f"{API_URL_ALUNOS_BASE}"
        f"&periodo_letivo={quote(PERIODO_LETIVO, safe='')}"
        f"&frequencia_data_inicial={quote(FREQUENCIA_DATA_INICIAL, safe='')}"
        f"&frequencia_data_final={quote(FREQUENCIA_DATA_FINAL, safe='')}"
    ).format(login=login)


def eh_erro_http_temporario(status: int) -> bool:
    return status >= 500


def eh_erro_temporario(erro: str) -> bool:
    texto = erro.lower()
    return (
        "tempo limite" in texto
        or "timed out" in texto
        or "falha de conexao" in texto
        or "falha de conexão" in texto
    )


def descrever_resultado(resultado: dict[str, Any]) -> str:
    if resultado.get("status") == 200:
        return "200"
    if "erro" in resultado:
        erro = str(resultado["erro"]).strip()
        status = resultado.get("status")
        try:
            parsed = json.loads(erro)
            if isinstance(parsed, dict) and "message" in parsed:
                mensagem = str(parsed["message"])
                return f"HTTP {status}: {mensagem}" if status else mensagem
        except json.JSONDecodeError:
            pass
        if len(erro) > 80:
            erro = erro[:77] + "..."
        if status:
            return f"HTTP {status}: {erro}"
        return f"erro: {erro}"
    return str(resultado.get("status", "erro"))


def resumir_falhas(resultados: list[dict[str, Any]]) -> dict[str, int]:
    resumo: dict[str, int] = {}
    for resultado in resultados:
        if resultado.get("status") == 200:
            continue
        chave = str(resultado.get("erro", f"HTTP {resultado.get('status', 'desconhecido')}"))
        resumo[chave] = resumo.get(chave, 0) + 1
    return resumo


def consultar_aluno(aluno: dict[str, Any]) -> dict[str, Any]:
    login = str(aluno.get("Login", "")).strip()
    resultado: dict[str, Any] = {
        "aluno_id": aluno.get("id"),
        "login": login,
        "nome": aluno.get("Nome"),
        "matricula": aluno.get("Matricula"),
    }

    if login == "":
        resultado["erro"] = "Aluno sem login (CPF)."
        return resultado

    url = montar_url_alunos(login)
    ultimo_erro = ""

    for tentativa in range(1, TENTATIVAS + 1):
        try:
            status, body = consultar_webservice(url, API_TOKEN)
        except HTTPError as error:
            corpo = error.read().decode("utf-8", errors="replace")
            ultimo_erro = corpo
            resultado["status"] = error.code
            if tentativa < TENTATIVAS and eh_erro_http_temporario(error.code):
                continue
            resultado["erro"] = corpo
            resultado["tentativas"] = tentativa
            return resultado
        except URLError as error:
            ultimo_erro = f"Falha de conexão: {error.reason}"
            if tentativa < TENTATIVAS and eh_erro_temporario(ultimo_erro):
                continue
            resultado["erro"] = ultimo_erro
            resultado["tentativas"] = tentativa
            return resultado
        except TimeoutError:
            ultimo_erro = f"Tempo limite excedido ({TIMEOUT_SEGUNDOS}s)."
            if tentativa < TENTATIVAS:
                continue
            resultado["erro"] = ultimo_erro
            resultado["tentativas"] = tentativa
            return resultado
        else:
            resultado["status"] = status
            resultado["tentativas"] = tentativa
            try:
                resultado["dados"] = json.loads(body)
            except json.JSONDecodeError:
                resultado["dados_brutos"] = body
            return resultado

    resultado["erro"] = ultimo_erro or "Falha desconhecida."
    resultado["tentativas"] = TENTATIVAS
    return resultado


def main() -> int:
    global API_URL_ALUNOS_BASE, API_TOKEN, _ENV
    global PERIODO_LETIVO, FREQUENCIA_DATA_INICIAL, FREQUENCIA_DATA_FINAL

    inicio = datetime.now()
    log("=== Coleta SIGAA iniciada ===")

    parser = argparse.ArgumentParser(
        description="Consulta detalhes SIGAA dos alunos não desistentes (login = CPF)."
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Apenas lista os alunos que seriam consultados, sem chamar a API",
    )
    parser.add_argument(
        "--limite",
        type=int,
        default=None,
        help="Limita a quantidade de consultas (útil para teste)",
    )
    parser.add_argument(
        "--sem-importar-faltas",
        action="store_true",
        help="Não extrai/insere faltas automáticas após a consulta",
    )
    parser.add_argument(
        "--gerar-lista-faltas",
        action="store_true",
        help="Gera python/lista_faltas.json com as faltas extraídas (debug)",
    )
    args = parser.parse_args()

    if not ARQUIVO_CONFIG_PHP.exists():
        log(f"Erro: config não encontrada: {ARQUIVO_CONFIG_PHP}", erro=True)
        return 1

    try:
        cfg = carregar_config_mysql(ARQUIVO_CONFIG_PHP)
        conn = pymysql.connect(**cfg)
        try:
            alunos = buscar_alunos_com_cpf(conn)
        finally:
            conn.close()
    except Exception as error:
        log(f"Erro ao ler alunos do banco: {error}", erro=True)
        return 1

    limite = args.limite if args.limite is not None else LIMITE_CONSULTAS
    if limite is not None:
        alunos = alunos[:limite]

    total = len(alunos)
    log(f"Alunos não desistentes com CPF válido: {total}")

    if total == 0:
        log("Nada a consultar.")
        log(f"=== Coleta finalizada em {agora()} ===")
        return 0

    if args.dry_run:
        for i, aluno in enumerate(alunos, start=1):
            log(
                f"  {i}/{total} - id={aluno['id']} login={aluno['Login']} "
                f"nome={aluno['Nome']}"
            )
        log("Dry-run: nenhuma consulta à API foi feita.")
        log(f"=== Coleta finalizada em {agora()} ===")
        return 0

    try:
        _ENV = carregar_env()
    except Exception as error:
        log(f"Erro ao ler configuração da API no banco: {error}", erro=True)
        return 1

    if not (_ENV.get("API_CLIENT_ID") and _ENV.get("API_CLIENT_SECRET")):
        log(
            "Erro: API SIGAA não configurada. "
            "Acesse Configurações → API SIGAA no sistema.",
            erro=True,
        )
        return 1

    if not (
        _ENV.get("API_PERIODO_LETIVO")
        and _ENV.get("API_FREQUENCIA_DATA_INICIAL")
        and _ENV.get("API_FREQUENCIA_DATA_FINAL")
    ):
        log(
            "Erro: período letivo e datas de frequência não configurados. "
            "Acesse Configurações → API SIGAA.",
            erro=True,
        )
        return 1

    if not verificar_ssl(_ENV):
        log("Aviso: verificação SSL desativada na configuração da API.")

    API_URL_ALUNOS_BASE = env_ou_padrao(
        _ENV,
        "API_URL_ALUNOS",
        "https://app.ifrs.edu.br/api/v1/sig/sigaa/alunos"
        "?login={login}&tipo_frequencia=intervalo",
    )
    PERIODO_LETIVO = _ENV["API_PERIODO_LETIVO"].strip()
    FREQUENCIA_DATA_INICIAL = _ENV["API_FREQUENCIA_DATA_INICIAL"].strip()
    FREQUENCIA_DATA_FINAL = _ENV["API_FREQUENCIA_DATA_FINAL"].strip()
    log(
        f"Período {PERIODO_LETIVO}: "
        f"{FREQUENCIA_DATA_INICIAL} a {FREQUENCIA_DATA_FINAL}"
    )

    try:
        API_TOKEN = obter_access_token(_ENV)
        log("Access token OAuth obtido.")
    except (ValueError, RuntimeError) as error:
        log(f"Erro de autenticação: {error}", erro=True)
        return 1

    log(
        f"Consultando {total} aluno(s) com concorrência {CONCORRENCIA}, "
        f"timeout {TIMEOUT_SEGUNDOS}s, até {TENTATIVAS} tentativa(s)..."
    )

    resultados: list[dict[str, Any]] = []
    sucessos = 0
    falhas = 0

    with ThreadPoolExecutor(max_workers=CONCORRENCIA) as executor:
        futuros = {executor.submit(consultar_aluno, aluno): aluno for aluno in alunos}
        for concluido, futuro in enumerate(as_completed(futuros), start=1):
            resultado = futuro.result()
            resultados.append(resultado)
            if resultado.get("status") == 200:
                sucessos += 1
            else:
                falhas += 1
            log(
                f"  {concluido}/{total} - login {resultado['login']} -> "
                f"{descrever_resultado(resultado)}"
            )

    # Mantém ordem estável por nome/login
    resultados.sort(key=lambda item: (item.get("nome") or "", item.get("login") or ""))

    erros = [item for item in resultados if item.get("status") != 200]
    garantir_diretorios()
    ARQUIVO_SAIDA.write_text(
        json.dumps(resultados, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )

    log()
    log(f"Concluído: {sucessos} sucesso(s), {falhas} falha(s).")
    log(f"Resposta completa salva em: {ARQUIVO_SAIDA}")

    if erros:
        log("Falhas na consulta:", erro=True)
        for item in erros:
            log(
                f"  - id={item.get('aluno_id')} login={item.get('login')} "
                f"nome={item.get('nome')} -> {descrever_resultado(item)}",
                erro=True,
            )
        log("Resumo das falhas:", erro=True)
        for tipo_erro, quantidade in sorted(
            resumir_falhas(resultados).items(),
            key=lambda item: item[1],
            reverse=True,
        ):
            log(f"  - {quantidade}x {tipo_erro}", erro=True)

    if not args.sem_importar_faltas:
        log()
        log("Importando faltas automáticas...")
        try:
            from faltas_automaticas import importar_faltas, processar_alertas_alunos

            user_id = (_ENV.get("REGISTRO_AUTOMATICO_USER_ID") or "").strip() or None
            resumo = importar_faltas(
                resultados,
                dry_run=False,
                user_id_env=user_id,
                gerar_lista_faltas=args.gerar_lista_faltas,
            )
            log(f"Faltas extraídas: {resumo['total_faltas_extraidas']}")
            log(f"Eventos inseridos: {resumo['inseridos']}")
            log(
                f"Pulados (falta do professor no dia): {resumo['pulados_professor']}"
            )
            log(f"Pulados (duplicado): {resumo['pulados_duplicado']}")
            log(f"Pulados (sem aluno): {resumo['pulados_sem_aluno']}")
            if resumo.get("lista_arquivo"):
                log(f"Lista de faltas: {resumo['lista_arquivo']}")
            processar_alertas_alunos(resumo.get("alunos_afetados") or [])
        except Exception as error:
            log(f"Aviso: falha ao importar faltas: {error}", erro=True)

    fim = datetime.now()
    duracao = fim - inicio
    log(
        f"=== Coleta finalizada | início {inicio.strftime('%d/%m/%Y %H:%M:%S')} "
        f"| fim {fim.strftime('%d/%m/%Y %H:%M:%S')} "
        f"| duração {duracao} ==="
    )
    return 0

if __name__ == "__main__":
    raise SystemExit(main())

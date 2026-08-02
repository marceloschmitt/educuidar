"""Carrega configuração da API SIGAA a partir do banco EduCuidar."""

from __future__ import annotations

import json
import re
import ssl
from pathlib import Path
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen

import pymysql

from paths import ARQUIVO_CONFIG_PHP

USER_AGENT = "EduCuidar/1.0"

DEFAULTS = {
    "api_sigaa_base_url": "https://app.ifrs.edu.br",
    "api_sigaa_oauth_url": "",
    "api_sigaa_client_id": "",
    "api_sigaa_client_secret": "",
    "api_sigaa_url_alunos": (
        "https://app.ifrs.edu.br/api/v1/sig/sigaa/alunos"
        "?login={login}&tipo_frequencia=intervalo"
    ),
    "api_sigaa_verify_ssl": "0",
    "api_sigaa_registro_user_id": "",
    "api_sigaa_periodo_letivo": "",
    "api_sigaa_frequencia_data_inicial": "",
    "api_sigaa_frequencia_data_final": "",
}


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
    }


def carregar_config_api(config_path: Path | None = None) -> dict[str, str]:
    """Lê chaves api_sigaa_* da tabela configuracoes."""
    cfg = dict(DEFAULTS)
    conn = pymysql.connect(**carregar_config_mysql(config_path))
    try:
        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT chave, valor FROM configuracoes
                WHERE chave LIKE 'api_sigaa_%'
                """
            )
            for row in cur.fetchall():
                chave = row["chave"]
                valor = row["valor"]
                cfg[chave] = "" if valor is None else str(valor)
    finally:
        conn.close()

    if not cfg.get("api_sigaa_oauth_url"):
        base = (cfg.get("api_sigaa_base_url") or DEFAULTS["api_sigaa_base_url"]).rstrip("/")
        cfg["api_sigaa_oauth_url"] = f"{base}/oauth/token"

    # Aliases usados pelos scripts
    cfg["API_BASE_URL"] = cfg.get("api_sigaa_base_url") or ""
    cfg["API_OAUTH_URL"] = cfg.get("api_sigaa_oauth_url") or ""
    cfg["API_CLIENT_ID"] = cfg.get("api_sigaa_client_id") or ""
    cfg["API_CLIENT_SECRET"] = cfg.get("api_sigaa_client_secret") or ""
    cfg["API_URL_ALUNOS"] = cfg.get("api_sigaa_url_alunos") or DEFAULTS["api_sigaa_url_alunos"]
    cfg["API_VERIFY_SSL"] = cfg.get("api_sigaa_verify_ssl") or "0"
    cfg["REGISTRO_AUTOMATICO_USER_ID"] = cfg.get("api_sigaa_registro_user_id") or ""
    cfg["API_PERIODO_LETIVO"] = cfg.get("api_sigaa_periodo_letivo") or ""
    cfg["API_FREQUENCIA_DATA_INICIAL"] = cfg.get("api_sigaa_frequencia_data_inicial") or ""
    cfg["API_FREQUENCIA_DATA_FINAL"] = cfg.get("api_sigaa_frequencia_data_final") or ""
    return cfg


# Compatibilidade com scripts que ainda chamam carregar_env()
def carregar_env(caminho=None) -> dict[str, str]:
    return carregar_config_api()


def verificar_ssl(env: dict[str, str] | None = None) -> bool:
    env = env if env is not None else carregar_config_api()
    valor = (env.get("API_VERIFY_SSL") or env.get("api_sigaa_verify_ssl") or "0").strip().lower()
    return valor in ("1", "true", "yes", "on")


def obter_access_token(env: dict[str, str] | None = None) -> str:
    """Obtém Bearer token via OAuth client_credentials."""
    env = env if env is not None else carregar_config_api()

    client_id = (env.get("API_CLIENT_ID") or "").strip()
    client_secret = (env.get("API_CLIENT_SECRET") or "").strip()
    oauth_url = (env.get("API_OAUTH_URL") or "").strip()

    if not client_id or not client_secret:
        raise ValueError(
            "Configure Client ID e Client Secret em Configurações → API SIGAA."
        )
    if not oauth_url:
        raise ValueError("Configure a URL OAuth em Configurações → API SIGAA.")

    corpo = json.dumps(
        {
            "grant_type": "client_credentials",
            "client_id": client_id,
            "client_secret": client_secret,
        }
    ).encode("utf-8")

    request = Request(
        oauth_url,
        data=corpo,
        headers={
            "Accept": "application/json",
            "Content-Type": "application/json",
            "User-Agent": USER_AGENT,
        },
        method="POST",
    )

    context = None if verificar_ssl(env) else ssl._create_unverified_context()
    try:
        with urlopen(request, timeout=60, context=context) as response:
            payload: Any = json.loads(response.read().decode("utf-8"))
    except HTTPError as error:
        detalhe = error.read().decode("utf-8", errors="replace")
        raise RuntimeError(f"Falha OAuth HTTP {error.code}: {detalhe}") from error
    except URLError as error:
        raise RuntimeError(f"Falha de conexão OAuth: {error.reason}") from error

    token = str(payload.get("access_token") or "").strip()
    if not token:
        raise RuntimeError(f"Resposta OAuth sem access_token: {payload}")
    return token


def env_ou_padrao(env: dict[str, str], chave: str, padrao: str = "") -> str:
    valor = (env.get(chave) or "").strip()
    return valor if valor else padrao


def ssl_context(env: dict[str, str] | None = None):
    if verificar_ssl(env):
        return None
    return ssl._create_unverified_context()

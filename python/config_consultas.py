"""Datas e período da consulta de frequência (tabela configuracoes)."""

from __future__ import annotations

from api_auth import carregar_config_api


def _cfg() -> dict[str, str]:
    return carregar_config_api()


def frequencia_data_inicial() -> str:
    valor = (_cfg().get("api_sigaa_frequencia_data_inicial") or "").strip()
    if not valor:
        raise ValueError(
            "Configure a data inicial em Configurações → API SIGAA."
        )
    return valor


def frequencia_data_final() -> str:
    valor = (_cfg().get("api_sigaa_frequencia_data_final") or "").strip()
    if not valor:
        raise ValueError(
            "Configure a data final em Configurações → API SIGAA."
        )
    return valor


def periodo_letivo() -> str:
    valor = (_cfg().get("api_sigaa_periodo_letivo") or "").strip()
    if not valor:
        raise ValueError(
            "Configure o período letivo em Configurações → API SIGAA."
        )
    return valor

#!/usr/bin/env python3
"""Parse de turno_turma do SIGAA (matriculados).

Formato típico:
  2N1234 (29/07/2026 - 07/08/2026),  7N1234 (08/08/2026 - 09/08/2026), ...
  5T56 6T23 (29/07/2026 - 13/11/2026)

Primeiro dígito = dia da semana SIGAA (1=dom … 7=sáb).
Letra = turno (M/T/N…).
Dígitos após a letra = números dos períodos (ex.: 1234 → [1,2,3,4]).
"""

from __future__ import annotations

import re
from datetime import date, datetime, timedelta
from typing import Any

DIAS_AULA_SIGAA = (2, 3, 4, 5, 6, 7)

_RE_BLOCO = re.compile(
    r"((?:[1-7][A-Za-z]\w*\s*)+)"
    r"\((\d{2}/\d{2}/\d{4})\s*-\s*(\d{2}/\d{2}/\d{4})\)"
)
_RE_DIA_TOKEN = re.compile(r"([1-7])([A-Za-z])(\d*)")
_RE_DIA = re.compile(r"(?:^|,)\s*([1-7])[A-Za-z]")


def _sigaa_para_weekday_python(dia_sigaa: int) -> int:
    return (dia_sigaa + 5) % 7


def _parse_data_br(texto: str) -> date | None:
    try:
        return datetime.strptime(texto.strip(), "%d/%m/%Y").date()
    except ValueError:
        return None


def _periodos_nums_do_sufixo(sufixo: str) -> list[int]:
    digitos = re.sub(r"\D", "", sufixo or "")
    if digitos == "":
        return [1]
    return [int(ch) for ch in digitos]


def _periodos_do_sufixo(sufixo: str) -> int:
    return max(len(_periodos_nums_do_sufixo(sufixo)), 1)


def _mesclar_detalhe(
    atual: dict[str, Any] | None,
    turno: str,
    periodos_nums: list[int],
) -> dict[str, Any]:
    nums = sorted(set(periodos_nums))
    if atual is None:
        return {
            "turno": turno.upper()[:1] if turno else "",
            "periodos_nums": nums,
            "periodos": max(len(nums), 1),
        }
    unidos = sorted(set(atual.get("periodos_nums") or []) | set(nums))
    turno_atual = str(atual.get("turno") or "")
    if not turno_atual and turno:
        turno_atual = turno.upper()[:1]
    return {
        "turno": turno_atual,
        "periodos_nums": unidos,
        "periodos": max(len(unidos), 1),
    }


def extrair_datas_aula(
    turno_turma: Any,
    *,
    incluir_sabado: bool = True,
    incluir_domingo: bool = False,
) -> list[date]:
    """Expande turno_turma em datas efetivas de aula (ordenadas, únicas)."""
    return sorted(
        extrair_datas_detalhadas(
            turno_turma,
            incluir_sabado=incluir_sabado,
            incluir_domingo=incluir_domingo,
        ).keys()
    )


def extrair_datas_com_periodos(
    turno_turma: Any,
    *,
    incluir_sabado: bool = True,
    incluir_domingo: bool = False,
) -> dict[date, int]:
    """Mapa data → quantidade de períodos previstos naquele encontro."""
    detalhado = extrair_datas_detalhadas(
        turno_turma,
        incluir_sabado=incluir_sabado,
        incluir_domingo=incluir_domingo,
    )
    return {d: int(info["periodos"]) for d, info in detalhado.items()}


def extrair_datas_detalhadas(
    turno_turma: Any,
    *,
    incluir_sabado: bool = True,
    incluir_domingo: bool = False,
    data_min: date | None = None,
    data_max: date | None = None,
) -> dict[date, dict[str, Any]]:
    """Mapa data → {turno, periodos_nums, periodos}.

    Se data_min/data_max forem informados, só expande encontros nesse intervalo
    (útil para snapshot limitado às datas da config SIGAA).
    """
    if turno_turma is None:
        return {}

    texto = str(turno_turma).strip()
    if texto == "":
        return {}

    permitidos = set(DIAS_AULA_SIGAA)
    if not incluir_sabado:
        permitidos.discard(7)
    if incluir_domingo:
        permitidos.add(1)

    por_data: dict[date, dict[str, Any]] = {}
    for match in _RE_BLOCO.finditer(texto):
        tokens: list[tuple[int, str, list[int]]] = []
        for token in _RE_DIA_TOKEN.finditer(match.group(1)):
            dia_sigaa = int(token.group(1))
            if dia_sigaa not in permitidos:
                continue
            turno = token.group(2).upper()
            nums = _periodos_nums_do_sufixo(token.group(3))
            tokens.append((dia_sigaa, turno, nums))
        if not tokens:
            continue

        inicio = _parse_data_br(match.group(2))
        fim = _parse_data_br(match.group(3))
        if inicio is None or fim is None:
            continue
        if fim < inicio:
            inicio, fim = fim, inicio
        if data_min is not None and fim < data_min:
            continue
        if data_max is not None and inicio > data_max:
            continue
        if data_min is not None and inicio < data_min:
            inicio = data_min
        if data_max is not None and fim > data_max:
            fim = data_max

        # weekday → (turno, periodos_nums)
        por_weekday: dict[int, dict[str, Any]] = {}
        for dia_sigaa, turno, nums in tokens:
            wd = _sigaa_para_weekday_python(dia_sigaa)
            por_weekday[wd] = _mesclar_detalhe(por_weekday.get(wd), turno, nums)

        atual = inicio
        while atual <= fim:
            if atual.weekday() in por_weekday:
                info = por_weekday[atual.weekday()]
                por_data[atual] = _mesclar_detalhe(
                    por_data.get(atual),
                    str(info.get("turno") or ""),
                    list(info.get("periodos_nums") or []),
                )
            atual += timedelta(days=1)

    return por_data


def extrair_dias_sigaa(
    turno_turma: Any,
    *,
    apenas_uteis: bool = False,
    incluir_sabado: bool = True,
) -> list[int]:
    datas = extrair_datas_aula(
        turno_turma,
        incluir_sabado=incluir_sabado and not apenas_uteis,
        incluir_domingo=False,
    )
    if datas:
        dias: set[int] = set()
        for d in datas:
            dia_sigaa = ((d.weekday() + 1) % 7) + 1
            if apenas_uteis and dia_sigaa not in (2, 3, 4, 5, 6):
                continue
            if not incluir_sabado and dia_sigaa == 7:
                continue
            if dia_sigaa == 1:
                continue
            dias.add(dia_sigaa)
        return sorted(dias)

    if turno_turma is None:
        return []
    texto = str(turno_turma).strip()
    if texto == "":
        return []

    dias_fb: set[int] = set()
    for match in _RE_DIA.finditer(texto):
        dia = int(match.group(1))
        if apenas_uteis and dia not in (2, 3, 4, 5, 6):
            continue
        if dia == 1:
            continue
        if not incluir_sabado and dia == 7:
            continue
        if dia not in DIAS_AULA_SIGAA and dia != 7:
            continue
        dias_fb.add(dia)
    return sorted(dias_fb)

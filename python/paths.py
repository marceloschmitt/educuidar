"""Caminhos do diretório python/ do EduCuidar."""

from __future__ import annotations

from pathlib import Path

DIR_PYTHON = Path(__file__).resolve().parent
ROOT = DIR_PYTHON.parent

ARQUIVO_CONFIG_PHP = ROOT / "config" / "config.php"

JSON_RESPOSTA_ALUNOS = DIR_PYTHON / "resposta_alunos.json"
JSON_LISTA_FALTAS = DIR_PYTHON / "lista_faltas.json"


def garantir_diretorios() -> None:
    DIR_PYTHON.mkdir(parents=True, exist_ok=True)

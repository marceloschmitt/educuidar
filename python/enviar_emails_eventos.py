#!/usr/bin/env python3
"""
Envia e-mails de eventos aos responsáveis após 2 horas do registro.

Agende no cron, por exemplo a cada 15 minutos:
  */15 * * * * cd /caminho/educuidar && python3 python/enviar_emails_eventos.py

O atraso de 2h permite apagar um registro feito por engano antes do envio.
"""

from __future__ import annotations

import subprocess
import sys

from paths import DIR_PYTHON, ROOT


def main() -> int:
    cli = DIR_PYTHON / "enviar_emails_eventos_cli.php"
    if not cli.is_file():
        print(f"CLI não encontrado: {cli}", file=sys.stderr)
        return 1

    cmd = ["php", str(cli)]
    print("Executando:", " ".join(cmd))
    result = subprocess.run(cmd, cwd=str(ROOT))
    return int(result.returncode)


if __name__ == "__main__":
    raise SystemExit(main())

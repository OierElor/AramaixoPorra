#!/usr/bin/env bash
#
# Aramaixo Porra — PROBA LOKALA
#
# Webgunea zure ordenagailuan abiarazten du, DATU ERREALEKIN, ezer publiko egin gabe.
# Aldaketak ikusteko `git push` egin AURRETIK.
#
#   ./proba.sh          → http://127.0.0.1:8777
#   ./proba.sh 8080     → beste ataka batean
#
# Gelditzeko: Ctrl+C
#
# Nola dabilen: MySQL-era ezin da lokaletik konektatu, beraz datu-kontsultak
# zuzeneko API publikora (irakurketa hutsa) birbidaltzen dira `dev/router.php`-k.
# Kodea zurea da (lokala); datuak errealak. Idazketak blokeatuta daude.

set -euo pipefail

cd "$(dirname "$0")"

ATAKA="${1:-8777}"

if ! [[ "$ATAKA" =~ ^[0-9]{2,5}$ ]]; then
    echo "❌ Ataka baliogabea: $ATAKA" >&2
    echo "   Erabilera: ./proba.sh [ataka]" >&2
    exit 1
fi

if ! command -v php >/dev/null 2>&1; then
    echo "❌ 'php' ez dago instalatuta. Behar da proba-zerbitzaria abiarazteko." >&2
    exit 1
fi

if [ ! -f dev/router.php ]; then
    echo "❌ dev/router.php ez da aurkitu. Errepoaren errotik exekutatu behar da." >&2
    exit 1
fi

# Ataka libre dagoen egiaztatu (bash-en /dev/tcp bidez, tresna gehigarririk gabe).
if (echo >"/dev/tcp/127.0.0.1/$ATAKA") >/dev/null 2>&1; then
    echo "❌ $ATAKA ataka okupatuta dago. Saiatu beste batekin: ./proba.sh $((ATAKA + 1))" >&2
    exit 1
fi

cat <<EOF

  🔧 ARAMAIXO PORRA — PROBA LOKALA

  Helbidea    http://127.0.0.1:$ATAKA

  Datuak      ✔ ERREALAK (zuzeneko APItik, irakurketa hutsa)
  Kodea       ✔ ZUREA (fitxategi lokalak, cache gabe)

  Blokeatuta  ✗ Aurre-porrak eta zuzenketak (emaila + log erreala idatziko lukete)
              ✗ Admin idazketa GUZTIAK (POST/PUT/DELETE → 403)

  Adibideak   http://127.0.0.1:$ATAKA/vuelta/2026/
              http://127.0.0.1:$ATAKA/tresnak/grafikoak/
              http://127.0.0.1:$ATAKA/admin/          (irakurketa soilik)

  Produkzioa EZIN da hemendik aldatu. Gelditzeko: Ctrl+C

EOF

exec php -S "127.0.0.1:$ATAKA" -t . dev/router.php

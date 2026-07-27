#!/usr/bin/env bash
# Cache-bertsioa (?v=YYYYMMDD) eguneratu orri publiko GUZTIETAN aldi berean.
#
# Zergatik: fitxategi estatikoak (HTML/CSS/JS) nabigatzaileak cachean gordetzen ditu.
# Aldaketa bat argitaratzean, `?v=` bertsioa aldatu behar da erabiltzaileek bertsio
# berria jaso dezaten. Bertsioa 35+ fitxategitan (145+ aldiz) dago hardcoded; eskuz
# eguneratzea nekeza eta akatsetara emana da. Script honek dena batera egiten du.
#
# Erabilera:
#   ./bump-version.sh            # gaurko data erabili (YYYYMMDD)
#   ./bump-version.sh 20260801   # data zehatza ezarri
#
# Ondoren: `git diff --stat` begiratu, commit eta push (gero Plesk pull).
# admin/index.html-ek EZ du `?v=` (index.php-k zerbitzatzen du) → ez da ukitzen.

set -euo pipefail
cd "$(dirname "$0")"

BERRIA="${1:-$(date +%Y%m%d)}"
if ! [[ "$BERRIA" =~ ^[0-9]{8}$ ]]; then
  echo "✗ Bertsioak 8 digitu izan behar ditu (YYYYMMDD). Jasoa: '$BERRIA'" >&2
  exit 1
fi

# Uneko bertsioa (ohikoena) informazio gisa.
ZAHARRA="$(git ls-files '*.html' '*.php' | xargs grep -hoE '\?v=[0-9]{8}' 2>/dev/null \
           | sort | uniq -c | sort -rn | head -1 | grep -oE '[0-9]{8}' || echo '????????')"

if [[ "$ZAHARRA" == "$BERRIA" ]]; then
  echo "ℹ Bertsioa jada $BERRIA da. Ez dago ezer egiteko."
  exit 0
fi

# ?v=<8 digitu> → ?v=$BERRIA git-trackeatutako HTML/PHP guztietan.
MAPPED=0
while IFS= read -r f; do
  if grep -qE '\?v=[0-9]{8}' "$f"; then
    sed -i -E "s/\?v=[0-9]{8}/?v=$BERRIA/g" "$f"
    MAPPED=$((MAPPED + 1))
  fi
done < <(git ls-files '*.html' '*.php')

GUZTIRA="$(git ls-files '*.html' '*.php' | xargs grep -hoE "\?v=$BERRIA" 2>/dev/null | wc -l | tr -d ' ')"
echo "✓ Cache-bertsioa: $ZAHARRA → $BERRIA"
echo "  $MAPPED fitxategi · $GUZTIRA aipamen eguneratuta."
echo "  Hurrengoa: git diff --stat && git commit && git push (gero Plesk pull)."

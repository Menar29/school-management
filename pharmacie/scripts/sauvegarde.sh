#!/bin/sh
# Sauvegarde quotidienne de la base de données (à lancer par cron chaque nuit).
#   crontab -e  puis :  0 3 * * * /chemin/vers/pharmacie/scripts/sauvegarde.sh
# Les identifiants sont lus dans ~/.my.cnf (section [mysqldump]) pour ne pas apparaître ici.
set -eu
DB="${DB_NAME:-pharmacie}"
DEST="${BACKUP_DIR:-$(dirname "$0")/../sauvegardes}"
mkdir -p "$DEST"
FILE="$DEST/pharmacie-$(date +%Y%m%d-%H%M).sql.gz"
mysqldump --single-transaction --routines --default-character-set=utf8mb4 "$DB" | gzip > "$FILE"
# garde 30 jours
find "$DEST" -name 'pharmacie-*.sql.gz' -mtime +30 -delete
echo "Sauvegarde : $FILE"

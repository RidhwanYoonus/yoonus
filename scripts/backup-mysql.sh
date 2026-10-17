#!/usr/bin/env bash
# Daily MySQL backup with 30-day retention, copied off-server.
# Cron (as the app user):  15 3 * * *  /var/www/shop/scripts/backup-mysql.sh >> /var/log/shop-backup.log 2>&1
# Needs: ~/.my.cnf with [mysqldump] user/password, and an rclone remote named "offsite" (S3, Backblaze B2, …).
set -euo pipefail

DB="${DB_DATABASE:-jewellery}"
DIR="${BACKUP_DIR:-/var/backups/shop}"
REMOTE="${BACKUP_REMOTE:-offsite:shop-backups}"
STAMP="$(date +%Y%m%d-%H%M%S)"
FILE="$DIR/${DB}-${STAMP}.sql.gz"

mkdir -p "$DIR"
mysqldump --single-transaction --quick --routines --triggers --default-character-set=utf8mb4 "$DB" | gzip -9 > "$FILE"
gzip -t "$FILE"

if command -v rclone >/dev/null; then
    rclone copy "$FILE" "$REMOTE"
    rclone delete --min-age 30d "$REMOTE"
fi
find "$DIR" -name "${DB}-*.sql.gz" -mtime +30 -delete
echo "$(date -Is) backup ok: $FILE"

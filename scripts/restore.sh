#!/usr/bin/env bash
#
# Restore a Resort365 backup into a MySQL database (Step 7.5, docs/OPERATIONS.md §Restore).
#
#   scripts/restore.sh <backup.zip> <database> [--files <attachments-dir>]
#
# The database must exist and should be empty; this script never drops anything. Connection details come from the environment:
# DB_HOST (127.0.0.1), DB_PORT (3306), DB_USERNAME, DB_PASSWORD; BACKUP_ARCHIVE_PASSWORD when the backup is encrypted.
# With --files, the attachments in the backup are copied into the given directory (normally storage/app/private/attachments).
# For point-in-time recovery, replay the binary logs after this (see the runbook).
set -euo pipefail

if [[ $# -lt 2 ]]; then
    sed -n '3,11p' "$0"
    exit 1
fi

archive=$1
database=$2
files_dir=""

if [[ ${3:-} == "--files" ]]; then
    files_dir=${4:?"--files needs a directory"}
fi

: "${DB_HOST:=127.0.0.1}" "${DB_PORT:=3306}" "${DB_USERNAME:?set DB_USERNAME}" "${DB_PASSWORD:?set DB_PASSWORD}"

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

echo "Extracting $archive"
if [[ -n ${BACKUP_ARCHIVE_PASSWORD:-} ]]; then
    unzip -q -P "$BACKUP_ARCHIVE_PASSWORD" "$archive" -d "$work"
else
    unzip -q "$archive" -d "$work"
fi

dump=$(find "$work/db-dumps" -name '*.sql*' | head -n 1)
[[ -n $dump ]] || { echo "No database dump in the backup" >&2; exit 1; }
[[ $dump == *.gz ]] && { gunzip "$dump"; dump=${dump%.gz}; }

existing=$(MYSQL_PWD=$DB_PASSWORD mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -N -e "select count(*) from information_schema.tables where table_schema = '$database'")
if [[ $existing != 0 ]]; then
    echo "Database $database is not empty ($existing tables). Restore into an empty database." >&2
    exit 1
fi

echo "Importing into $database"
MYSQL_PWD=$DB_PASSWORD mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" --default-character-set=utf8mb4 "$database" < "$dump"

if [[ -n $files_dir ]]; then
    source_dir=$(find "$work" -type d -path '*storage/app/private/attachments' | head -n 1)
    if [[ -n $source_dir ]]; then
        echo "Copying attachments into $files_dir"
        mkdir -p "$files_dir"
        cp -a "$source_dir/." "$files_dir/"
    else
        echo "The backup has no attachments (they may live on S3)."
    fi
fi

echo "Done. Point DB_DATABASE at $database, run 'php artisan migrate --force' for newer migrations, then 'php artisan up'."

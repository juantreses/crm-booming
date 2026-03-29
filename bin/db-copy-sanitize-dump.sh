#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SQL_FILE="${SCRIPT_DIR}/db-copy-sanitize.sql"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-espocrm_db}"
DB_USER="${DB_USER:-espocrm_user}"
OUT_FILE="${OUT_FILE:-db.sanitized.sql}"

if [[ ! -f "${SQL_FILE}" ]]; then
  echo "SQL file not found: ${SQL_FILE}" >&2
  exit 1
fi

if [[ -z "${CONFIRM:-}" ]]; then
  echo "This will dump DB '${DB_NAME}' from ${DB_HOST}:${DB_PORT} to '${OUT_FILE}'."
  echo "The dump will be sanitized on import by appending the sanitizer SQL."
  echo "Set CONFIRM=1 to proceed."
  exit 1
fi

if [[ "${DB_HOST}" == "127.0.0.1" || "${DB_HOST}" == "localhost" ]]; then
  if [[ "${CONFIRM_LOCAL:-}" != "1" ]]; then
    echo "Refusing to dump from local host by default (${DB_HOST})." >&2
    echo "Set CONFIRM_LOCAL=1 to override."
    exit 1
  fi
fi

MYSQLDUMP_ARGS=(
  "--host=${DB_HOST}"
  "--port=${DB_PORT}"
  "--user=${DB_USER}"
  "--single-transaction"
  "--routines"
  "--triggers"
  "--events"
  "${DB_NAME}"
)

if [[ -n "${DB_PASSWORD:-}" ]]; then
  MYSQL_PWD="${DB_PASSWORD}" mysqldump "${MYSQLDUMP_ARGS[@]}" > "${OUT_FILE}"
else
  mysqldump "${MYSQLDUMP_ARGS[@]}" -p > "${OUT_FILE}"
fi

{
  echo ""
  echo "-- Sanitizer SQL appended by db-copy-sanitize-dump.sh"
  cat "${SQL_FILE}"
} >> "${OUT_FILE}"

echo "Sanitized dump written to ${OUT_FILE}"

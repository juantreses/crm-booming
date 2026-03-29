#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SQL_FILE="${SCRIPT_DIR}/db-copy-sanitize.sql"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-espocrm_db}"
DB_USER="${DB_USER:-espocrm_user}"

if [[ ! -f "${SQL_FILE}" ]]; then
  echo "SQL file not found: ${SQL_FILE}" >&2
  exit 1
fi

if [[ -z "${CONFIRM:-}" ]]; then
  echo "This will sanitize DB '${DB_NAME}' on ${DB_HOST}:${DB_PORT}."
  echo "Set CONFIRM=1 to proceed."
  exit 1
fi

if [[ "${DB_HOST}" != "127.0.0.1" && "${DB_HOST}" != "localhost" ]]; then
  if [[ "${CONFIRM_REMOTE:-}" != "1" ]]; then
    echo "Refusing to run on non-local host (${DB_HOST})." >&2
    echo "Set CONFIRM_REMOTE=1 to override."
    exit 1
  fi
fi

MYSQL_ARGS=(
  "--host=${DB_HOST}"
  "--port=${DB_PORT}"
  "--user=${DB_USER}"
  "${DB_NAME}"
)

if [[ -n "${DB_PASSWORD:-}" ]]; then
  MYSQL_PWD="${DB_PASSWORD}" mysql "${MYSQL_ARGS[@]}" < "${SQL_FILE}"
else
  mysql "${MYSQL_ARGS[@]}" -p < "${SQL_FILE}"
fi

echo "DB sanitization complete."

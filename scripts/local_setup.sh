#!/bin/bash
# ローカル開発用DBを一発でセットアップするスクリプト。
# 前提: MySQL(または互換DB)がローカルで動いていること（例: `brew install mysql && mysql.server start`）。
#
# 使い方:
#   ./scripts/local_setup.sh
#   MYSQL_DB=別名にしたい場合 MYSQL_USER=root MYSQL_PASSWORD=xxx ./scripts/local_setup.sh
#
# やること:
#   1. ローカルDBを作成（既にあれば作り直す）
#   2. schema/schema.sql でテーブル構造を再現
#   3. schema/seed.sql でダミーデータを投入
set -euo pipefail

MYSQL_DB="${MYSQL_DB:-whabitat_local}"
MYSQL_USER="${MYSQL_USER:-root}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-}"
MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"

MYSQL_ARGS=(-h "$MYSQL_HOST" -u "$MYSQL_USER")
if [ -n "$MYSQL_PASSWORD" ]; then
  MYSQL_ARGS+=(-p"$MYSQL_PASSWORD")
fi

echo "==> データベース '$MYSQL_DB' を作成(存在すれば作り直し)"
mysql "${MYSQL_ARGS[@]}" -e "DROP DATABASE IF EXISTS \`$MYSQL_DB\`; CREATE DATABASE \`$MYSQL_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "==> テーブル構造を投入(schema/schema.sql)"
mysql "${MYSQL_ARGS[@]}" "$MYSQL_DB" < "$REPO_ROOT/schema/schema.sql"

echo "==> ダミーデータを投入(schema/seed.sql)"
mysql "${MYSQL_ARGS[@]}" "$MYSQL_DB" < "$REPO_ROOT/schema/seed.sql"

echo ""
echo "完了。.env の DB_HOST/DB_NAME/DB_USER/DB_PASS を以下に合わせてください:"
echo "  DB_HOST=$MYSQL_HOST"
echo "  DB_NAME=$MYSQL_DB"
echo "  DB_USER=$MYSQL_USER"
echo "  DB_PASS=$MYSQL_PASSWORD"
echo ""
echo "起動: php -S localhost:8000"

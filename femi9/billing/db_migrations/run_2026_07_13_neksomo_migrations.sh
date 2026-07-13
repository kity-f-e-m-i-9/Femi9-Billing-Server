#!/bin/bash
# Run all 7 migrations from today's Neksomo/pieces-per-pack/Femi9-LLP-rate work
# against production, in order. Fill in your real production DB values below
# (find them in production's femi9/billing/shared/.env, or your hosting panel).
#
# Usage: ./run_2026_07_13_neksomo_migrations.sh

set -e  # stop immediately if any migration fails, instead of running the rest

DB_HOST="CHANGE_ME"       # e.g. localhost, or your DB host from hosting panel
DB_PORT="3306"            # production usually uses the standard MySQL port
DB_USER="CHANGE_ME"       # e.g. billing0femi9_femi9admin
DB_PASS="CHANGE_ME"
DB_NAME="CHANGE_ME"       # e.g. billing0femi9_billingapp

MYSQL="mysql -h${DB_HOST} -P${DB_PORT} -u${DB_USER} -p${DB_PASS} ${DB_NAME}"

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "1/7: Neksomo login..."
$MYSQL < "${DIR}/2026_07_13_neksomo_login.sql"

echo "2/7: pieces_per_pack column..."
$MYSQL < "${DIR}/2026_07_13_products_pieces_per_pack.sql"

echo "3/7: pieces_per_pack backfill (13 known products)..."
$MYSQL < "${DIR}/2026_07_13_products_pieces_per_pack_backfill.sql"

echo "4/7: purchase_price columns (superseded by step 7, harmless to run)..."
$MYSQL < "${DIR}/2026_07_13_products_purchase_price.sql"

echo "5/7: neksomo_llp_piece_sales table..."
$MYSQL < "${DIR}/2026_07_13_neksomo_llp_piece_sales.sql"

echo "6/7: convert to neksomo_llp_piece_rates (rate list)..."
$MYSQL < "${DIR}/2026_07_13_neksomo_llp_piece_rates.sql"

echo "7/7: neksomo_llp_piece_purchase_rates table + drop old purchase_price columns..."
$MYSQL < "${DIR}/2026_07_13_neksomo_llp_piece_purchase_rates.sql"

echo "Done. Verifying..."
$MYSQL -e "SELECT username, usertype FROM admin_log WHERE usertype='neksomo';"
$MYSQL -e "SELECT id, productName, pieces_per_pack FROM products ORDER BY id;"
$MYSQL -e "SHOW TABLES LIKE 'neksomo_llp%';"

#!/usr/bin/env bash
# Only creates a fresh random database and an isolated temporary WordPress site.
set -euo pipefail
repo="$(cd "$(dirname "$0")/.." && pwd)"
: "${NIL_MYSQL_PASSWORD:?Set the disposable service password}"
: "${NIL_TEST_LABEL:=local}"
: "${NIL_TEST_HPOS:=yes}"
case "$NIL_TEST_LABEL" in *[!A-Za-z0-9_-]*) exit 2;; esac
case "$NIL_TEST_HPOS" in yes|no) ;; *) exit 2;; esac
work="$(mktemp -d "${RUNNER_TEMP:-/tmp}/nil-manager-mysql.XXXXXXXX")"
export NIL_TEST_WP_ROOT="$work/wordpress" NIL_TEST_RESULTS_DIR="$work/results"
mkdir -p "$NIL_TEST_WP_ROOT" "$NIL_TEST_RESULTS_DIR"
# Copy only fixture reports to the repository's ignored output directory, even on failure.
trap 'mkdir -p "$repo/dist/mysql-results/$NIL_TEST_LABEL-$NIL_TEST_HPOS"; cp -R "$NIL_TEST_RESULTS_DIR/." "$repo/dist/mysql-results/$NIL_TEST_LABEL-$NIL_TEST_HPOS/"' EXIT
fixture_db="$(php "$repo/scripts/mysql-create-db.php")"
printf '%s\n' "$fixture_db" > "$work/.nil-manager-test"
wp --allow-root --path="$NIL_TEST_WP_ROOT" core download --version=7.1.3 --quiet
wp --allow-root --path="$NIL_TEST_WP_ROOT" config create --dbname="$fixture_db" --dbuser="${NIL_MYSQL_USER:-root}" --dbpass="$NIL_MYSQL_PASSWORD" --dbhost="${NIL_MYSQL_HOST:-127.0.0.1}:${NIL_MYSQL_PORT:-3306}" --dbprefix=nil_test_ --skip-check --quiet
for constant in NIL_DISPOSABLE_FIXTURE NIL_TEST_REAL_MYSQL DISABLE_WP_CRON WP_HTTP_BLOCK_EXTERNAL; do
  wp --allow-root --path="$NIL_TEST_WP_ROOT" config set "$constant" true --raw --quiet
done
wp --allow-root --path="$NIL_TEST_WP_ROOT" core install --url=http://nil-manager.test --title='Nil Manager SQL fixture' --admin_user=nil_fixture --admin_password="$(php -r 'echo bin2hex(random_bytes(18));')" --admin_email=fixture@example.test --skip-email --quiet
mkdir -p "$NIL_TEST_WP_ROOT/wp-content/mu-plugins"
printf '%s\n' '<?php add_filter("pre_wp_mail", "__return_true");' > "$NIL_TEST_WP_ROOT/wp-content/mu-plugins/no-fixture-mail.php"
curl --fail --silent --show-error --location https://downloads.wordpress.org/plugin/woocommerce.11.2.1.zip --output "$work/woocommerce.zip"
wp --allow-root --path="$NIL_TEST_WP_ROOT" plugin install "$work/woocommerce.zip" --activate --quiet
ln -s "$repo/nilwears-bulk-manager" "$NIL_TEST_WP_ROOT/wp-content/plugins/nilwears-bulk-manager"
wp --allow-root --path="$NIL_TEST_WP_ROOT" plugin activate nilwears-bulk-manager --quiet
wp --allow-root --path="$NIL_TEST_WP_ROOT" option update woocommerce_custom_orders_table_enabled "$NIL_TEST_HPOS" --quiet
wp --allow-root --path="$NIL_TEST_WP_ROOT" option update woocommerce_custom_orders_table_data_sync_enabled no --quiet
php "$repo/tests/mysql-suite.php" 2>&1 | tee "$NIL_TEST_RESULTS_DIR/functional.log"
php "$repo/tests/mysql-concurrency.php" 2>&1 | tee "$NIL_TEST_RESULTS_DIR/concurrency.log"

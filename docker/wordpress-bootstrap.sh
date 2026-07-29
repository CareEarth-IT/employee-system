#!/bin/sh
set -e

WP_DIR="/var/www/html/wordpress"
WP_CLI="/usr/local/bin/wp"

if [ ! -f "$WP_DIR/wp-settings.php" ]; then
    echo "WARN: WordPress core not found at $WP_DIR"
    exit 0
fi

APP_URL="${APP_URL:-https://employee.careearth.net}"
APP_URL="${APP_URL%/}"
WP_URL="${WORDPRESS_SITE_URL:-${APP_URL}/wordpress}"
WP_TITLE="${WORDPRESS_SITE_TITLE:-CE-Group お知らせ}"
WP_ADMIN_USER="${WORDPRESS_ADMIN_USER:-ceadmin}"
WP_ADMIN_EMAIL="${WORDPRESS_ADMIN_EMAIL:-yuta_masui@careearth.info}"
WP_ADMIN_PASSWORD="${WORDPRESS_ADMIN_PASSWORD:-}"

DB_NAME="${DB_DATABASE:-ceemployee}"
DB_USER="${DB_USERNAME:-ceemployee}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_CHARSET="utf8mb4"

# Cloud SQL unix socket (Cloud Run) or TCP host
if [ -n "${DB_SOCKET:-}" ]; then
    WP_DB_HOST="localhost:${DB_SOCKET}"
else
    WP_DB_HOST="${DB_HOST:-127.0.0.1}"
    if [ -n "${DB_PORT:-}" ] && [ "$DB_PORT" != "3306" ]; then
        WP_DB_HOST="${WP_DB_HOST}:${DB_PORT}"
    fi
fi

UPLOADS_DIR="$WP_DIR/wp-content/uploads"
mkdir -p "$UPLOADS_DIR"
# WordPress still creates YYYY/MM locally during upload even in stateless mode.
YEAR=$(date +%Y)
MONTH=$(date +%m)
mkdir -p "$UPLOADS_DIR/$YEAR/$MONTH"
chown -R www-data:www-data "$WP_DIR/wp-content"
chmod -R ug+rwx "$UPLOADS_DIR"

# Deterministic salts from APP_KEY so all Cloud Run instances stay consistent
derive_salt() {
    printf '%s' "${APP_KEY:-employee-wordpress}:$1" | openssl dgst -sha256 -binary | openssl base64 -A
}

AUTH_KEY=$(derive_salt auth_key)
SECURE_AUTH_KEY=$(derive_salt secure_auth_key)
LOGGED_IN_KEY=$(derive_salt logged_in_key)
NONCE_KEY=$(derive_salt nonce_key)
AUTH_SALT=$(derive_salt auth_salt)
SECURE_AUTH_SALT=$(derive_salt secure_auth_salt)
LOGGED_IN_SALT=$(derive_salt logged_in_salt)
NONCE_SALT=$(derive_salt nonce_salt)

php_escape() {
    printf "%s" "$1" | sed "s/'/\\\\'/g"
}

cat > "$WP_DIR/wp-config.php" <<EOF
<?php
define('DB_NAME', '$(php_escape "$DB_NAME")');
define('DB_USER', '$(php_escape "$DB_USER")');
define('DB_PASSWORD', '$(php_escape "$DB_PASSWORD")');
define('DB_HOST', '$(php_escape "$WP_DB_HOST")');
define('DB_CHARSET', '$DB_CHARSET');
define('DB_COLLATE', '');

define('AUTH_KEY',         '$(php_escape "$AUTH_KEY")');
define('SECURE_AUTH_KEY',  '$(php_escape "$SECURE_AUTH_KEY")');
define('LOGGED_IN_KEY',    '$(php_escape "$LOGGED_IN_KEY")');
define('NONCE_KEY',        '$(php_escape "$NONCE_KEY")');
define('AUTH_SALT',        '$(php_escape "$AUTH_SALT")');
define('SECURE_AUTH_SALT', '$(php_escape "$SECURE_AUTH_SALT")');
define('LOGGED_IN_SALT',   '$(php_escape "$LOGGED_IN_SALT")');
define('NONCE_SALT',       '$(php_escape "$NONCE_SALT")');

\$table_prefix = 'wp_';

define('WP_HOME', '$(php_escape "$WP_URL")');
define('WP_SITEURL', '$(php_escape "$WP_URL")');
define('WP_DEBUG', false);
define('DISALLOW_FILE_EDIT', true);
define('AUTOMATIC_UPDATER_DISABLED', true);
define('FS_METHOD', 'direct');

/**
 * Custom early bootstrap (before WordPress core).
 * Edit docker/wordpress/wp-config-extra.php in the repo / image.
 */
if (file_exists(__DIR__ . '/wp-config-extra.php')) {
    require_once __DIR__ . '/wp-config-extra.php';
}

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';
EOF

chown www-data:www-data "$WP_DIR/wp-config.php"
chmod 640 "$WP_DIR/wp-config.php"

cd "$WP_DIR"

if ! "$WP_CLI" core is-installed --allow-root >/dev/null 2>&1; then
    if [ -z "$WP_ADMIN_PASSWORD" ]; then
        WP_ADMIN_PASSWORD=$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-20)
        echo "WordPress admin password (generated): $WP_ADMIN_PASSWORD"
    fi

    echo "==> Installing WordPress at $WP_URL"
    "$WP_CLI" core install \
        --url="$WP_URL" \
        --title="$WP_TITLE" \
        --admin_user="$WP_ADMIN_USER" \
        --admin_password="$WP_ADMIN_PASSWORD" \
        --admin_email="$WP_ADMIN_EMAIL" \
        --skip-email \
        --allow-root \
        || echo "WARN: wp core install failed"
else
    echo "WordPress already installed"
    "$WP_CLI" option update home "$WP_URL" --allow-root >/dev/null 2>&1 || true
    "$WP_CLI" option update siteurl "$WP_URL" --allow-root >/dev/null 2>&1 || true
fi

if "$WP_CLI" theme is-installed cocoon-child --allow-root >/dev/null 2>&1; then
    "$WP_CLI" theme activate cocoon-child --allow-root \
        || echo "WARN: failed to activate cocoon-child"
else
    echo "WARN: cocoon-child theme not found in image"
fi

if "$WP_CLI" plugin is-installed wp-stateless --allow-root >/dev/null 2>&1; then
    "$WP_CLI" plugin activate wp-stateless --allow-root \
        || echo "WARN: failed to activate wp-stateless"
    # Connection check is cached 4h; clear so IAM / ADC fixes apply immediately.
    "$WP_CLI" transient delete sm::is_connected_to_gs --allow-root >/dev/null 2>&1 || true

    # Admin UI can save sm_mode=disabled and break uploads on Cloud Run (ephemeral local disk).
    if [ -n "${WORDPRESS_GCS_BUCKET:-}" ]; then
        WP_STATELESS_MODE="${WORDPRESS_GCS_MODE:-stateless}"
        "$WP_CLI" option update sm_mode "$WP_STATELESS_MODE" --allow-root >/dev/null 2>&1 || true
        "$WP_CLI" option update sm_bucket "$WORDPRESS_GCS_BUCKET" --allow-root >/dev/null 2>&1 || true
        echo "==> WP-Stateless options synced: mode=${WP_STATELESS_MODE} bucket=${WORDPRESS_GCS_BUCKET}"
    fi
else
    echo "WARN: wp-stateless plugin not found in image"
fi

if [ -n "${WORDPRESS_GCS_BUCKET:-}" ]; then
    echo "==> WP-Stateless GCS bucket: ${WORDPRESS_GCS_BUCKET} (ADC=${WORDPRESS_GCS_USE_ADC:-0})"
    php -r '
      $bucket = getenv("WORDPRESS_GCS_BUCKET") ?: "";
      if ($bucket === "") { exit(0); }
      $autoload = "/var/www/html/wordpress/wp-content/plugins/wp-stateless/lib/Google/vendor/autoload.php";
      if (!is_readable($autoload)) { fwrite(STDERR, "WARN: Google autoload missing\n"); exit(0); }
      require $autoload;
      try {
        $client = new Google\Client();
        $client->useApplicationDefaultCredentials();
        $client->setScopes(["https://www.googleapis.com/auth/devstorage.full_control"]);
        $service = new Google\Service\Storage($client);
        $service->buckets->get($bucket);
        echo "WP-Stateless GCS connection OK: {$bucket}\n";
      } catch (Throwable $e) {
        fwrite(STDERR, "WARN: WP-Stateless GCS connection failed: ".$e->getMessage()."\n");
      }
    ' || true
fi

"$WP_CLI" option update blog_public 0 --allow-root >/dev/null 2>&1 || true
"$WP_CLI" rewrite structure '/%postname%/' --allow-root >/dev/null 2>&1 || true
"$WP_CLI" rewrite flush --allow-root >/dev/null 2>&1 || true

chown -R www-data:www-data "$WP_DIR/wp-content"

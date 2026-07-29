#!/bin/sh
set -e
CLIENT_FILE="${1:-/var/www/html/wordpress/wp-content/plugins/wp-stateless/lib/classes/class-gs-client.php}"
SCRIPT_DIR="$(CDPATH= cd -- "$(dirname "$0")" && pwd)"
php "$SCRIPT_DIR/apply-wp-stateless-adc.php" "$CLIENT_FILE"

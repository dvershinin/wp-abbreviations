#!/bin/bash
# Setup script for WordPress test environment.

set -euo pipefail

cd "$(dirname "$0")"

# Configuration
WP_URL="${WP_URL:-http://wordpress}"
WP_PATH="${WP_PATH:-/var/www/html}"
WP_ADMIN_USER="admin"
WP_ADMIN_PASS="admin"
WP_ADMIN_EMAIL="admin@example.com"

echo "=== WordPress Test Setup ==="
echo "WP_URL: $WP_URL"
echo "WP_PATH: $WP_PATH"

# Ensure WP-CLI is available inside the wordpress container (not provided by the base image).
echo "Ensuring WP-CLI is installed in wordpress container..."
docker compose exec -T wordpress bash -lc 'set -e; if ! command -v wp >/dev/null 2>&1; then curl -sS https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp && chmod +x /usr/local/bin/wp; fi'

# Wait for WordPress to be accessible from within the container.
echo "Waiting for WordPress..."
for i in {1..60}; do
    if docker compose exec -T wordpress curl -sf http://localhost/ >/dev/null 2>&1; then
        echo "WordPress is accessible"
        break
    fi
    if [ "$i" -eq 60 ]; then
        echo "WordPress did not become ready in time."
        exit 1
    fi
    echo "  Attempt $i..."
    sleep 2
done

# Check if already installed
if docker compose exec -T wordpress wp core is-installed --path="$WP_PATH" --allow-root >/dev/null 2>&1; then
    echo "WordPress already installed"
else
    echo "Installing WordPress..."
    docker compose exec -T wordpress wp core install \
        --path="$WP_PATH" \
        --url="$WP_URL" \
        --title="Abbreviations Test Site" \
        --admin_user="$WP_ADMIN_USER" \
        --admin_password="$WP_ADMIN_PASS" \
        --admin_email="$WP_ADMIN_EMAIL" \
        --skip-email \
        --allow-root
fi

# Activate the plugin
echo "Activating abbreviations plugin..."
docker compose exec -T wordpress wp plugin activate abbreviations --path="$WP_PATH" --allow-root || true

# Enable pretty permalinks for REST API
echo "Setting up permalinks..."
docker compose exec -T wordpress wp rewrite structure '/%postname%/' --path="$WP_PATH" --allow-root || true

# Clear abbreviations option to ensure clean test state
echo "Clearing abbreviations option..."
docker compose exec -T wordpress wp eval "delete_option('abbreviations'); wp_cache_flush();" --path="$WP_PATH" --allow-root || true

echo "=== Setup Complete ==="

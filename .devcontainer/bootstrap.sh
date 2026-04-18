#!/usr/bin/env bash

set -euo pipefail

WP_ROOT="/var/www/html"
THEME_DIR="$WP_ROOT/wp-content/themes/cocoon-child-master"
MU_PLUGIN_DIR="$WP_ROOT/wp-content/mu-plugins"
LOCAL_PLUGIN_SRC="$THEME_DIR/local-dev/plugin/kotodaman-local-runtime.php"
LOCAL_PLUGIN_DST="$MU_PLUGIN_DIR/kotodaman-local-runtime.php"
SEED_SCRIPT="$THEME_DIR/local-dev/seed/seed.php"
SEARCH_JSON_PATH="$THEME_DIR/lib/character-search/all_characters_search.json"
SITE_URL="http://localhost:8080"
SITE_TITLE="Kotodaman DB Local"
ADMIN_USER="admin"
ADMIN_PASSWORD="admin"
ADMIN_EMAIL="admin@example.com"

log() {
  printf '[bootstrap] %s\n' "$1"
}

wait_for_wordpress() {
  log "Waiting for WordPress files and database"
  until wp core version --path="$WP_ROOT" --allow-root >/dev/null 2>&1; do
    sleep 2
  done

  until php -r 'mysqli_report(MYSQLI_REPORT_OFF); $db = @new mysqli(getenv("WORDPRESS_DB_HOST") ?: "db", getenv("WORDPRESS_DB_USER") ?: "wordpress", getenv("WORDPRESS_DB_PASSWORD") ?: "wordpress", getenv("WORDPRESS_DB_NAME") ?: "wordpress"); exit($db->connect_errno ? 1 : 0);' >/dev/null 2>&1; do
    sleep 2
  done
}

install_wordpress() {
  if ! wp core is-installed --path="$WP_ROOT" --allow-root >/dev/null 2>&1; then
    log "Installing WordPress"
    wp core install \
      --path="$WP_ROOT" \
      --url="$SITE_URL" \
      --title="$SITE_TITLE" \
      --admin_user="$ADMIN_USER" \
      --admin_password="$ADMIN_PASSWORD" \
      --admin_email="$ADMIN_EMAIL" \
      --skip-email \
      --allow-root
  else
    log "WordPress already installed"
  fi

  wp option update home "$SITE_URL" --path="$WP_ROOT" --allow-root
  wp option update siteurl "$SITE_URL" --path="$WP_ROOT" --allow-root
  wp rewrite structure '/%postname%/' --hard --path="$WP_ROOT" --allow-root
}

install_parent_theme() {
  if [ ! -d "$WP_ROOT/wp-content/themes/cocoon-master" ]; then
    log "Installing Cocoon parent theme"
    wp theme install https://github.com/yhira/cocoon/archive/refs/heads/master.zip --force --path="$WP_ROOT" --allow-root

    if [ -d "$WP_ROOT/wp-content/themes/cocoon" ] && [ ! -d "$WP_ROOT/wp-content/themes/cocoon-master" ]; then
      mv "$WP_ROOT/wp-content/themes/cocoon" "$WP_ROOT/wp-content/themes/cocoon-master"
    fi
  else
    log "Cocoon parent theme already present"
  fi
}

install_plugins() {
  if ! wp plugin is-installed advanced-custom-fields --path="$WP_ROOT" --allow-root >/dev/null 2>&1; then
    log "Installing ACF free"
    wp plugin install advanced-custom-fields --activate --path="$WP_ROOT" --allow-root
  else
    log "Activating ACF free"
    wp plugin activate advanced-custom-fields --path="$WP_ROOT" --allow-root || true
  fi
}

install_mu_plugin() {
  log "Installing local runtime mu-plugin"
  mkdir -p "$MU_PLUGIN_DIR"
  cp "$LOCAL_PLUGIN_SRC" "$LOCAL_PLUGIN_DST"
}

activate_theme() {
  log "Activating child theme"
  wp theme activate cocoon-child-master --path="$WP_ROOT" --allow-root
}

seed_data() {
  log "Seeding local dummy data"
  wp eval-file "$SEED_SCRIPT" --path="$WP_ROOT" --allow-root
}

ensure_search_json_readable() {
  if [ -e "$SEARCH_JSON_PATH" ]; then
    chmod 644 "$SEARCH_JSON_PATH" || true
  fi
}

generate_search_json() {
  if [ -s "$SEARCH_JSON_PATH" ]; then
    ensure_search_json_readable
    log "Keeping existing search JSON"
    return
  fi

  log "Generating search JSON"
  wp eval 'if (function_exists("koto_generate_search_json_all")) { koto_generate_search_json_all(); echo "generated\n"; } else { fwrite(STDERR, "koto_generate_search_json_all not found\n"); exit(1); }' --path="$WP_ROOT" --allow-root
  ensure_search_json_readable
}

flush_rewrites() {
  wp rewrite flush --hard --path="$WP_ROOT" --allow-root
}

wait_for_wordpress
install_wordpress
install_parent_theme
install_plugins
install_mu_plugin
activate_theme
seed_data
generate_search_json
flush_rewrites

log "Local environment ready"
log "Site: $SITE_URL"
log "Admin: $ADMIN_USER / $ADMIN_PASSWORD"

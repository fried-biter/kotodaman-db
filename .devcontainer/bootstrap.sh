#!/usr/bin/env bash

set -euo pipefail

WP_ROOT="/var/www/html"
THEME_DIR="$WP_ROOT/wp-content/themes/cocoon-child-master"
WP_PLUGIN_DIR="$WP_ROOT/wp-content/plugins"
MU_PLUGIN_DIR="$WP_ROOT/wp-content/mu-plugins"
LOCAL_PLUGIN_SRC="$THEME_DIR/local-dev/plugin/kotodaman-local-runtime.php"
LOCAL_PLUGIN_DST="$MU_PLUGIN_DIR/kotodaman-local-runtime.php"
SEED_SCRIPT="$THEME_DIR/local-dev/seed/seed.php"
SEARCH_JSON_PATH="$THEME_DIR/lib/character-search/all_characters_search.json"
ACF_JSON_DIR="$THEME_DIR/acf-json"
SITE_URL="http://localhost:8080"
SITE_TITLE="Kotodaman DB Local"
ADMIN_USER="admin"
ADMIN_PASSWORD="admin"
ADMIN_EMAIL="admin@example.com"
ACF_PRO_SITE_URL="${ACF_PRO_SITE_URL:-$SITE_URL}"
ACF_PRO_VERSION="${ACF_PRO_VERSION:-^6.0}"

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
  if [ -z "${ACF_PRO_LICENSE:-}" ]; then
    log "ACF_PRO_LICENSE is required. Put the ACF PRO license key in .devcontainer/.env."
    exit 1
  fi

  if ! wp plugin is-installed advanced-custom-fields-pro --path="$WP_ROOT" --allow-root >/dev/null 2>&1; then
    install_acf_pro
  else
    log "ACF PRO already installed"
  fi

  if wp plugin is-installed advanced-custom-fields --path="$WP_ROOT" --allow-root >/dev/null 2>&1; then
    log "Removing ACF free"
    wp plugin deactivate advanced-custom-fields --path="$WP_ROOT" --allow-root || true
    wp plugin delete advanced-custom-fields --path="$WP_ROOT" --allow-root || true
  fi

  log "Activating ACF PRO"
  wp plugin activate advanced-custom-fields-pro --path="$WP_ROOT" --allow-root
}

install_acf_pro() {
  log "Installing ACF PRO with Composer"

  local composer_dir="/tmp/kotodaman-acf-pro"
  rm -rf "$composer_dir"
  mkdir -p "$composer_dir" "$WP_PLUGIN_DIR"

  cat >"$composer_dir/composer.json" <<EOF
{
  "repositories": [
    {
      "type": "composer",
      "url": "https://connect.advancedcustomfields.com"
    }
  ],
  "require": {
    "composer/installers": "^2.0",
    "wpengine/advanced-custom-fields-pro": "$ACF_PRO_VERSION"
  },
  "extra": {
    "installer-paths": {
      "$WP_PLUGIN_DIR/{\$name}/": ["type:wordpress-plugin"]
    }
  },
  "config": {
    "allow-plugins": {
      "composer/installers": true
    }
  }
}
EOF

  COMPOSER_ALLOW_SUPERUSER=1 \
  COMPOSER_AUTH="$(printf '{"http-basic":{"connect.advancedcustomfields.com":{"username":"%s","password":"%s"}}}' "$ACF_PRO_LICENSE" "$ACF_PRO_SITE_URL")" \
    composer install --working-dir="$composer_dir" --no-dev --prefer-dist --no-interaction --no-progress
}

ensure_acf_json_dir() {
  log "Preparing ACF local JSON directory"
  mkdir -p "$ACF_JSON_DIR"
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

sync_acf_json_definitions() {
  log "Syncing ACF definitions from local JSON"

  if compgen -G "$ACF_JSON_DIR/*.json" >/dev/null; then
    wp acf json sync --path="$WP_ROOT" --allow-root
  else
    log "No ACF local JSON files found"
  fi
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
ensure_acf_json_dir
install_mu_plugin
activate_theme
sync_acf_json_definitions
seed_data
generate_search_json
flush_rewrites

log "Local environment ready"
log "Site: $SITE_URL"
log "Admin: $ADMIN_USER / $ADMIN_PASSWORD"

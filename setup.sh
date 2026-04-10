#!/usr/bin/env bash
# setup.sh — wp-phpunit-scaffold interactive setup helper
#
# Run this script from within your plugin project root AFTER copying the
# scaffold/ directory contents into your project.
#
# Usage:
#   bash setup.sh
#
# The script will ask a few questions and then perform find+replace across
# the scaffold files, swapping out the default placeholder values for your
# actual plugin details.

set -e

# ── Colour helpers ───────────────────────────────────────────────────────────

CYAN='\033[0;36m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BOLD='\033[1m'
RESET='\033[0m'

info()    { echo -e "${CYAN}${*}${RESET}"; }
success() { echo -e "${GREEN}✔ ${*}${RESET}"; }
warn()    { echo -e "${YELLOW}⚠  ${*}${RESET}"; }
header()  { echo -e "\n${BOLD}${*}${RESET}"; }

# ── Detect sed flavour ───────────────────────────────────────────────────────
# macOS sed requires an empty-string argument after -i; GNU sed does not.

if sed --version 2>&1 | grep -q 'GNU'; then
    sedi() { sed -i "$@"; }
else
    sedi() { sed -i '' "$@"; }
fi

# Replace $2 with $3 in file $1 (skips silently if file missing)
replace_in_file() {
    local file="$1" from="$2" to="$3"
    [ -f "$file" ] || return 0
    local esc_from esc_to
    esc_from=$(printf '%s' "$from" | sed 's/[\/&[\.*^$]/\\&/g')
    esc_to=$(printf '%s' "$to"   | sed 's/[\/&]/\\&/g')
    sedi "s/${esc_from}/${esc_to}/g" "$file"
}

to_title_case() {
    echo "$1" | tr '-' ' ' | \
        awk '{for(i=1;i<=NF;i++) $i=toupper(substr($i,1,1)) tolower(substr($i,2)); print}'
}

to_constant_case() {
    echo "$1" | tr '[:lower:]-' '[:upper:]_'
}

ask() {
    local prompt="$1" default="$2" response
    echo -en "${BOLD}${prompt}${RESET} [${CYAN}${default}${RESET}]: "
    read -r response
    printf '%s' "${response:-$default}"
}

# ── Header ───────────────────────────────────────────────────────────────────

echo ""
echo -e "${BOLD}╔══════════════════════════════════════════════╗${RESET}"
echo -e "${BOLD}║       wp-phpunit-scaffold setup helper       ║${RESET}"
echo -e "${BOLD}╚══════════════════════════════════════════════╝${RESET}"
echo ""
info "Customises scaffold files in this directory for your plugin."
info "Replaces placeholder values (my-plugin, wordpress, etc.) with your details."
echo ""

# ── Collect values ────────────────────────────────────────────────────────────

header "1. Plugin slug"
DETECTED_SLUG=$(basename "$PWD")
info "Detected from current directory: ${DETECTED_SLUG}"
PLUGIN_SLUG=$(ask "Plugin slug (kebab-case)" "$DETECTED_SLUG")

header "2. Plugin main file"
PLUGIN_MAIN_FILE=$(ask "Plugin main file" "${PLUGIN_SLUG}.php")
if [ ! -f "$PLUGIN_MAIN_FILE" ]; then
    warn "File '${PLUGIN_MAIN_FILE}' not found in this directory — continuing anyway."
fi

header "3. Plugin constant (PHP define name)"
DERIVED_CONSTANT=$(to_constant_case "$PLUGIN_SLUG")
info "Derived from slug: ${DERIVED_CONSTANT}"
PLUGIN_CONSTANT=$(ask "Plugin constant" "$DERIVED_CONSTANT")

header "4. Plugin name (human-readable)"
PLUGIN_NAME=$(ask "Plugin name" "$(to_title_case "$PLUGIN_SLUG")")

header "5. Database credentials (Docker test DB)"
DB_NAME=$(ask "DB name" "wordpress")
DB_USER=$(ask "DB user" "wordpress")
DB_PASSWORD=$(ask "DB password" "wordpress")

header "6. Docker image tag (pattonwebz/phpunit-wordpress)"
DOCKER_TAG=$(ask "Docker image tag" "1.0.0")

# ── Confirm ───────────────────────────────────────────────────────────────────

echo ""
header "Summary"
printf "  %-20s %s\n" "Plugin slug:"      "$PLUGIN_SLUG"
printf "  %-20s %s\n" "Main file:"        "$PLUGIN_MAIN_FILE"
printf "  %-20s %s\n" "Constant:"         "$PLUGIN_CONSTANT"
printf "  %-20s %s\n" "Plugin name:"      "$PLUGIN_NAME"
printf "  %-20s %s\n" "DB name:"          "$DB_NAME"
printf "  %-20s %s\n" "DB user:"          "$DB_USER"
printf "  %-20s %s\n" "DB password:"      "$DB_PASSWORD"
printf "  %-20s %s\n" "Docker tag:"       "$DOCKER_TAG"
echo ""
echo -en "${BOLD}Proceed with replacements?${RESET} [${CYAN}Y/n${RESET}]: "
read -r CONFIRM
if [[ "$CONFIRM" =~ ^[Nn]$ ]]; then
    warn "Aborted — no files were changed."
    exit 0
fi

# ── File-specific replacements ────────────────────────────────────────────────
# Each file gets context-aware substitutions to handle the case where DB name,
# user, and password all share the same default value ("wordpress").

echo ""
header "Applying replacements..."

process_file() {
    local file="$1"
    if [ ! -f "$file" ]; then
        warn "  Skipping (not found): ${file}"
        return 0
    fi

    case "$file" in

        docker-compose.yml)
            # Plugin volume/working_dir path
            replace_in_file "$file" \
                "wp-content/plugins/my-plugin" \
                "wp-content/plugins/${PLUGIN_SLUG}"
            # DB credentials — replace on the specific YAML key lines
            sedi "s/MYSQL_DATABASE: wordpress/MYSQL_DATABASE: ${DB_NAME}/" "$file"
            sedi "s/MYSQL_USER: wordpress/MYSQL_USER: ${DB_USER}/"         "$file"
            sedi "s/MYSQL_PASSWORD: wordpress/MYSQL_PASSWORD: ${DB_PASSWORD}/" "$file"
            sedi "s/WORDPRESS_DB_NAME: wordpress/WORDPRESS_DB_NAME: ${DB_NAME}/"     "$file"
            sedi "s/WORDPRESS_DB_USER: wordpress/WORDPRESS_DB_USER: ${DB_USER}/"     "$file"
            sedi "s/WORDPRESS_DB_PASSWORD: wordpress/WORDPRESS_DB_PASSWORD: ${DB_PASSWORD}/" "$file"
            # Docker image tag in the default fallback expression
            if [ "$DOCKER_TAG" != "1.0.0" ]; then
                replace_in_file "$file" ":-1.0.0}" ":-${DOCKER_TAG}}"
            fi
            ;;

        setup-phpunit.sh)
            sedi "s/DB_NAME=\"wordpress\"/DB_NAME=\"${DB_NAME}\"/" "$file"
            sedi "s/DB_USER=\"wordpress\"/DB_USER=\"${DB_USER}\"/" "$file"
            sedi "s/DB_PASS=\"wordpress\"/DB_PASS=\"${DB_PASSWORD}\"/" "$file"
            ;;

        tests/bootstrap.php)
            # Replace main file reference before slug to avoid partial match
            replace_in_file "$file" "my-plugin.php" "$PLUGIN_MAIN_FILE"
            replace_in_file "$file" "my-plugin"     "$PLUGIN_SLUG"
            replace_in_file "$file" "MY_PLUGIN"     "$PLUGIN_CONSTANT"
            replace_in_file "$file" "My Plugin"     "$PLUGIN_NAME"
            ;;

        .github/workflows/phpunit.yml)
            sedi "s/MYSQL_DATABASE: wordpress/MYSQL_DATABASE: ${DB_NAME}/" "$file"
            sedi "s/MYSQL_USER: wordpress/MYSQL_USER: ${DB_USER}/"         "$file"
            sedi "s/MYSQL_PASSWORD: wordpress/MYSQL_PASSWORD: ${DB_PASSWORD}/" "$file"
            # The install-wp-tests.sh invocation line
            sedi "s/ wordpress wordpress wordpress / ${DB_NAME} ${DB_USER} ${DB_PASSWORD} /" "$file"
            ;;

        .env.example)
            sedi "s/^DB_NAME=wordpress/DB_NAME=${DB_NAME}/"         "$file"
            sedi "s/^DB_USER=wordpress/DB_USER=${DB_USER}/"         "$file"
            sedi "s/^DB_PASSWORD=wordpress/DB_PASSWORD=${DB_PASSWORD}/" "$file"
            ;;

        phpunit.xml.dist | tests/bin/install-wp-tests.sh)
            # No defaults to replace in these files
            ;;
    esac

    success "  Updated: ${file}"
}

SCAFFOLD_FILES=(
    "docker-compose.yml"
    "setup-phpunit.sh"
    "phpunit.xml.dist"
    "tests/bootstrap.php"
    "tests/bin/install-wp-tests.sh"
    ".github/workflows/phpunit.yml"
    ".env.example"
)

for f in "${SCAFFOLD_FILES[@]}"; do
    process_file "$f"
done

# ── Permissions ───────────────────────────────────────────────────────────────

echo ""
header "Setting executable permissions..."
for script in "setup-phpunit.sh" "tests/bin/install-wp-tests.sh"; do
    if [ -f "$script" ]; then
        chmod 755 "$script"
        success "  chmod 755: ${script}"
    fi
done

# ── Done ─────────────────────────────────────────────────────────────────────

echo ""
echo -e "${GREEN}${BOLD}✔  Setup complete!${RESET}"
echo ""
echo "Next steps:"
echo "  1. Copy .env.example to .env and set your DB credentials:"
echo "       cp .env.example .env"
echo "  2. Spin up Docker, install WordPress, and run your tests:"
echo "       bash setup-phpunit.sh"
echo "  3. Or run PHPUnit directly once the stack is up:"
echo "       docker compose exec phpunit vendor/bin/phpunit"
echo ""

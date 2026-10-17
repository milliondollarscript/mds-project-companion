#!/usr/bin/env bash
set -euo pipefail

workspace_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../../.." && pwd)"
compose_file="$workspace_root/docker/docker-compose.mds3-migration-rehearsal.yml"
core_source="$workspace_root/repos/mds3/src/million-dollar-script"
extension_source="$workspace_root/repos/extensions/free/mds-project-companion"
current_project=""
current_port=""

cleanup_current() {
    if [[ "$current_project" == mdspc_uninstall_*_20260728 ]] && [[ "$current_port" =~ ^1808[1-3]$ ]]; then
        MDS3_REHEARSAL_PORT="$current_port" docker compose \
            -p "$current_project" \
            -f "$compose_file" \
            down -v --remove-orphans >/dev/null 2>&1 || true
    fi
}

compose() {
    MDS3_REHEARSAL_PORT="$current_port" docker compose \
        -p "$current_project" \
        -f "$compose_file" \
        "$@"
}

run_scenario() {
    local scenario="$1"
    local port="$2"
    local core_delete="$3"
    local companion_include="$4"
    local expected="$5"

    current_project="mdspc_uninstall_${scenario}_20260728"
    current_port="$port"
    cleanup_current

    compose up -d db wordpress >/dev/null
    compose run --rm wordpress-cli wp core install \
        --url="http://localhost:$port" \
        --title="Project Companion Uninstall Test" \
        --admin_user=admin \
        --admin_password=companion-test \
        --admin_email=admin@example.test \
        --skip-email >/dev/null
    compose cp "$core_source/." wordpress:/var/www/html/wp-content/plugins/million-dollar-script >/dev/null
    compose cp "$extension_source/." wordpress:/var/www/html/wp-content/plugins/mds-project-companion >/dev/null
    compose exec -T wordpress chown -R www-data:www-data \
        /var/www/html/wp-content/plugins/million-dollar-script \
        /var/www/html/wp-content/plugins/mds-project-companion
    compose run --rm wordpress-cli wp plugin activate million-dollar-script >/dev/null
    compose run --rm wordpress-cli wp plugin activate mds-project-companion >/dev/null

    compose run --rm \
        -e TEST_CORE_DELETE="$core_delete" \
        -e TEST_COMPANION_INCLUDE="$companion_include" \
        wordpress-cli wp eval '
            $settings = get_option("mds3_settings", array());
            $settings["delete_data_on_uninstall"] = getenv("TEST_CORE_DELETE");
            update_option("mds3_settings", $settings, false);
            update_option("mds3_extension_cleanup_included", array(
                "mds-project-companion" => "true" === getenv("TEST_COMPANION_INCLUDE"),
            ), false);
            update_option("mds_project_companion_discovery_cache", array("fixture" => true), false);
            update_option("mds_project_companion_connection", array("status" => "connected"), false);
            update_option("mds_project_companion_connector_secret", array("credential" => "fixture-secret"), false);
            global $wpdb;
            $table = $wpdb->prefix . "mds_project_companion_audit";
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
                WP_CLI::error("Audit table missing before uninstall.");
            }
        '

    compose run --rm wordpress-cli wp plugin uninstall mds-project-companion --deactivate >/dev/null
    compose run --rm \
        -e TEST_EXPECTED="$expected" \
        -e TEST_SCENARIO="$scenario" \
        wordpress-cli wp eval '
            $expected = getenv("TEST_EXPECTED");
            global $wpdb;
            $table = $wpdb->prefix . "mds_project_companion_audit";
            $has_settings = false !== get_option("mds_project_companion_settings", false);
            $has_cache = false !== get_option("mds_project_companion_discovery_cache", false);
            $has_connection = false !== get_option("mds_project_companion_connection", false);
            $has_secret = false !== get_option("mds_project_companion_connector_secret", false);
            $has_table = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
            $has_cron = (bool) wp_next_scheduled("mds_project_companion_cleanup");

            if ("preserved" === $expected && (!$has_settings || !$has_cache || !$has_connection || !$has_secret || !$has_table)) {
                WP_CLI::error("Expected companion data to be preserved.");
            }
            if ("deleted" === $expected && ($has_settings || $has_cache || $has_connection || $has_secret || $has_table || $has_cron)) {
                WP_CLI::error("Expected companion data and cron to be deleted: " . wp_json_encode(array(
                    "settings" => $has_settings,
                    "cache" => $has_cache,
                    "connection" => $has_connection,
                    "secret" => $has_secret,
                    "table" => $has_table,
                    "cron" => $has_cron,
                )));
            }
            if ($has_cron) {
                WP_CLI::error("Deactivation must clear the cleanup schedule.");
            }
            WP_CLI::success("Uninstall scenario " . getenv("TEST_SCENARIO") . " passed: " . $expected);
        '

    cleanup_current
    current_project=""
    current_port=""
}

trap cleanup_current EXIT

case "${1:-all}" in
    parent_off)
        run_scenario parent_off 18081 no true preserved
        ;;
    extension_excluded)
        run_scenario extension_excluded 18082 yes false preserved
        ;;
    deletion_approved)
        run_scenario deletion_approved 18083 yes true deleted
        ;;
    all)
        run_scenario parent_off 18081 no true preserved
        run_scenario extension_excluded 18082 yes false preserved
        run_scenario deletion_approved 18083 yes true deleted
        ;;
    *)
        echo "Usage: $0 [all|parent_off|extension_excluded|deletion_approved]" >&2
        exit 2
        ;;
esac

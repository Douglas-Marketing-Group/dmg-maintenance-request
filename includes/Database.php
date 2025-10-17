<?php

namespace DMG\DMGMaintenanceRequest;

if (! defined('ABSPATH')) {
    exit;
}

class Database
{
    private const SCHEMA_VERSION = '1.1.0';
    private const OPTION_KEY     = 'dmg_maint_db_version';

    /**
     * Define the canonical schema.
     */
    private static function get_schema(): array
    {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $charset = $wpdb->get_charset_collate();

        return [
            'dmg_maint_logs' => [
                'schema' => "CREATE TABLE {$prefix}dmg_maint_logs (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    event varchar(255) NOT NULL,
                    context longtext NULL,
                    ip varchar(45) NULL,
                    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id)
                ) {$charset};",

                'keys' => [
                    "ALTER TABLE {$prefix}dmg_maint_logs ADD INDEX event (event)"
                ],
            ],

            'dmg_maint_requests' => [
                'schema' => "CREATE TABLE {$prefix}dmg_maint_requests (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    email varchar(255) NOT NULL,
                    env varchar(255) NOT NULL,
                    sig varchar(64) NOT NULL,
                    status varchar(20) NOT NULL DEFAULT 'pending',
                    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id)
                ) {$charset};",

                'keys' => [
                    "ALTER TABLE {$prefix}dmg_maint_requests ADD INDEX sig (sig)",
                    "ALTER TABLE {$prefix}dmg_maint_requests ADD INDEX email (email)",
                    "ALTER TABLE {$prefix}dmg_maint_requests ADD INDEX status (status)"
                ],
            ],
        ];
    }

    /**
     * Main entry point — reconcile schema.
     */
    public static function sync(): void
    {
        $installed = get_option(self::OPTION_KEY);
        $desired   = self::SCHEMA_VERSION;

        // Only run dbDelta if schema version differs or missing
        if ($installed !== $desired) {
            self::apply_schema();
            update_option(self::OPTION_KEY, $desired);

            // Flag for admin notice
            set_transient('dmg_maint_db_schema_updated', $desired);
        }
    }

    /**
     * Run dbDelta on all defined tables.
     */
    private static function apply_schema(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $schema_definitions = self::get_schema();

        foreach ($schema_definitions as $table => $definition) {
            // 1️Apply base schema (columns only)
            dbDelta($definition['schema']);

            // 2️⃣ Sync indexes
            self::sync_table_keys($table, $definition['keys']);
        }
    }

    /**
     * Sync indexes for a given table.
     */
    private static function sync_table_keys(string $table, array $keys): void
    {
        global $wpdb;

        // Get current indexes
        $existing = $wpdb->get_results("SHOW INDEX FROM {$wpdb->prefix}{$table}");
        $existing_names = array_unique(array_map(fn($r) => $r->Key_name, $existing));

        foreach ($keys as $sql) {
            // Extract index name from "ADD INDEX name (col)"
            if (!preg_match('/ADD\s+(?:UNIQUE\s+)?INDEX\s+([a-zA-Z0-9_]+)/i', $sql, $m)) {
                continue;
            }
            $index_name = $m[1];

            // Drop old index if exists (guarantees fresh definition)
            if (in_array($index_name, $existing_names, true)) {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}{$table} DROP INDEX {$index_name}");
            }

            // Recreate index
            $wpdb->query($sql);
        }
    }

    /**
     * Drop all plugin tables.
     */
    public static function drop_tables(): void
    {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}dmg_maint_logs");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}dmg_maint_requests");

        delete_option(self::OPTION_KEY);
        delete_transient('dmg_maint_db_schema_updated');
    }
}

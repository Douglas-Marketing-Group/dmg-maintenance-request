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
        $charset_collate = $wpdb->get_charset_collate();

        return [
            'dmg_maint_logs' => "CREATE TABLE {$wpdb->prefix}dmg_maint_logs (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                event varchar(255) NOT NULL,
                context longtext NULL,
                ip varchar(45) NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY event (event)
            ) {$charset_collate};",

            'dmg_maint_requests' => "CREATE TABLE {$wpdb->prefix}dmg_maint_requests (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                email varchar(255) NOT NULL,
                env varchar(255) NOT NULL,
                sig varchar(64) NOT NULL,
                processed tinyint(1) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY sig (sig),
                KEY email (email)
            ) {$charset_collate};",
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
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach (self::get_schema() as $name => $sql) {
            dbDelta($sql);
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

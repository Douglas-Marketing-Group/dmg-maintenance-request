<?php

namespace DMG\DMGMaintenanceRequest;

if (! defined('ABSPATH')) {
    exit;
}

class Database
{
    private const SCHEMA_VERSION = '1.0.1';
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
}

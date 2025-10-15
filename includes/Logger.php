<?php

namespace DMG\DMGMaintenanceRequest;

if (! defined('ABSPATH')) {
    exit;
}

class Logger
{
    /**
     * Table name helper.
     */
    public static function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'dmg_maint_logs';
    }

    /**
     * Write a log entry.
     */
    public static function log(string $event, array $context = []): void
    {
        global $wpdb;

        $data = [
            'event'      => sanitize_text_field($event),
            'context'    => wp_json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at' => current_time('mysql'),
        ];

        $wpdb->insert(self::table_name(), $data);
    }

    /**
     * Optional: Fetch logs (for debugging or admin UI later)
     */
    public static function get_logs(int $limit = 50): array
    {
        global $wpdb;
        $table = self::table_name();
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit),
            ARRAY_A
        );
    }
}

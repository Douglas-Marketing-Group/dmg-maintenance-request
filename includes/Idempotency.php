<?php

namespace DMG\DMGMaintenanceRequest;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handles prevention of duplicate maintenance submissions.
 */
class Idempotency
{
    /**
     * Check if a signature has already been recorded.
     */
    public static function exists(string $sig): bool
    {
        global $wpdb;
        $table = "{$wpdb->prefix}dmg_maint_requests";

        return (bool) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE sig = %s", $sig)
        );
    }

    /**
     * Record a new signature entry.
     */
    public static function record(string $email, string $env, string $sig): void
    {
        global $wpdb;
        $table = "{$wpdb->prefix}dmg_maint_requests";

        $wpdb->insert(
            $table,
            [
                'email'      => sanitize_email($email),
                'env'        => sanitize_text_field($env),
                'sig'        => sanitize_text_field($sig),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s']
        );
    }

    /**
     * Mark a record as processed.
     */
    public static function markProcessed(string $sig): void
    {
        global $wpdb;
        $table = "{$wpdb->prefix}dmg_maint_requests";

        $wpdb->update(
            $table,
            ['processed' => 1],
            ['sig' => sanitize_text_field($sig)],
            ['%d'],
            ['%s']
        );
    }

    /**
     * Check and record in one go.
     * Returns true if duplicate, false if new.
     */
    public static function checkAndRecord(string $email, string $env, string $sig): bool
    {
        $idempotencyDisabled = defined('DMG_MAINT_DISABLE_IDEMPOTENCY') && DMG_MAINT_DISABLE_IDEMPOTENCY;
        if ($idempotencyDisabled) {
            // Always record, but never block duplicates
            self::record($email, $env, $sig);
            return false;
        }

        if (self::exists($sig)) {
            return true; // duplicate
        }

        self::record($email, $env, $sig);
        return false;
    }
}

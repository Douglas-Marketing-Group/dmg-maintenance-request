<?php

namespace DMG\DMGMaintenanceRequest;

if (! defined('ABSPATH')) {
    exit;
}

class Installer
{
    /**
     * Activation hook
     */
    public static function activate(): void
    {
        Database::sync();
    }

    /**
     * Initialize plugin (hooks, etc.)
     */
    public static function init(): void
    {
        Database::sync();
    }
}

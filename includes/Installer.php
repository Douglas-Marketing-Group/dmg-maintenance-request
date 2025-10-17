<?php

namespace DMG\DMGMaintenanceRequest;

use Jet_Engine\Blocks_Views\Dynamic_Content\Data;

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

    /**
     * Uninstall hook
     */
    public static function uninstall(): void
    {
        Database::drop_tables();
    }
}

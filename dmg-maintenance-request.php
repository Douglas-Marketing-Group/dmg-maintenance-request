<?php

/**
 * Plugin Name: DMG Maintenance Request
 * Description: Validates maintenance request links and triggers automation.
 * Version: 1.0.0
 * Author: DMG
 */

require 'vendor/autoload.php';

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (class_exists(\Dotenv\Dotenv::class)) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

define('DMG_MAINT_PATH', plugin_dir_path(__FILE__));
define('DMG_MAINT_SECRET', $_ENV['DMG_MAINT_SECRET'] ?? '');
define(
    'DMG_MAINT_DISABLE_IDEMPOTENCY',
    filter_var($_ENV['DMG_MAINT_DISABLE_IDEMPOTENCY'] ?: false, FILTER_VALIDATE_BOOLEAN)
);

add_action('init', [\DMG\DMGMaintenanceRequest\DMGMaintenanceRequest::class, 'init']);
add_action('plugins_loaded', [\DMG\DMGMaintenanceRequest\Installer::class, 'init']);

register_activation_hook(__FILE__, [\DMG\DMGMaintenanceRequest\Installer::class, 'activate']);
register_uninstall_hook(__FILE__, [\DMG\DMGMaintenanceRequest\Installer::class, 'uninstall']);

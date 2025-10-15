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

define('DMG_MAINT_SECRET', $_ENV['DMG_MAINT_SECRET'] ?? '');

add_action('template_redirect', [\DMG\DMGMaintenanceRequest\DMGMaintenanceRequest::class, 'check_signature']);

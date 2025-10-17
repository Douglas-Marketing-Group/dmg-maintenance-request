<?php

/**
 * Template: Admin notice for DB schema update
 *
 * @var string $updated_version Version number passed from the caller.
 */

if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="notice notice-success is-dismissible">
    <p>
        <strong>DMG Maintenance Request:</strong>
        Database schema updated to version <?php echo esc_html($updated_version); ?> successfully.
    </p>
</div>
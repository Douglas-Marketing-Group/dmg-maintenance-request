<?php

namespace DMG\DMGMaintenanceRequest;

if (! defined('ABSPATH')) {
    exit;
}

class DMGMaintenanceRequest
{
    public static function init(): void
    {
        // Validate the maintenance request link on page load
        add_action('template_redirect', [\DMG\DMGMaintenanceRequest\DMGMaintenanceRequest::class, 'check_signature']);

        // This fires on all Elementor Pro form submissions
        add_action('elementor_pro/forms/new_record', [self::class, 'handle_form_submission'], 10, 2);

        add_action('admin_notices', [self::class, 'show_admin_db_schema_notice'], 10, 2);
    }

    /**
     * Show admin notice if DB schema was updated.
     */
    public static function show_admin_db_schema_notice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $updated_version = get_transient('dmg_maint_db_schema_updated');
        if (!$updated_version) {
            return;
        }

        $template_path = DMG_MAINT_PATH . 'templates/admin-notice-schema-updated.php';
        if (file_exists($template_path)) {
            include $template_path;
        }

        delete_transient('dmg_maint_db_schema_updated');
    }

    /**
     * Validates the maintenance request link.
     */
    public static function check_signature()
    {
        $page = get_page_by_path('site-maintenance-request'); // ← your Elementor page slug
        if (! $page) return;

        // Only guard this page
        if (! is_page($page->ID)) return;

        // Step 1: Check for required query params
        $email = isset($_GET['email']) ? rawurldecode($_GET['email']) : '';
        $env   = isset($_GET['env']) ? sanitize_text_field($_GET['env']) : '';
        $hid   = isset($_GET['hid']) ? sanitize_text_field($_GET['hid']) : '';
        $pc    = isset($_GET['pc']) ? sanitize_text_field($_GET['pc']) : '';
        $cl    = isset($_GET['cl']) ? sanitize_text_field($_GET['cl']) : '';
        $aid   = isset($_GET['aid']) ? sanitize_text_field($_GET['aid']) : '';
        $exp   = isset($_GET['exp']) ? intval($_GET['exp']) : 0;
        $sig   = isset($_GET['sig']) ? sanitize_text_field($_GET['sig']) : '';

        if (!$email || !$env || !$exp || !$sig || !$hid) {
            Logger::log('Missing or incomplete request parameters', [
                'email' => $email,
                'env'   => $env,
                'hid'   => $hid,
                'pc'    => $pc,
                'cl'    => $cl,
                'aid'   => $aid,
                'exp'   => $exp,
                'sig'   => $sig,
            ]);

            wp_redirect(home_url('/maintenance-link-invalid/'));
            exit;
        }

        // Step 2: Expiration check
        if (time() > $exp) {
            Logger::log('This maintenance request link has expired', [
                'email' => $email,
                'env'   => $env,
                'hid'   => $hid,
                'pc'    => $pc,
                'cl'    => $cl,
                'aid'   => $aid,
                'exp'   => $exp,
                'sig'   => $sig,
            ]);

            wp_redirect(home_url('/maintenance-link-invalid/'));
            exit;
        }

        // Step 3: Signature validation
        $expected_sig = md5($env . $email . $exp . DMG_MAINT_SECRET);

        if (!hash_equals($expected_sig, $sig)) {
            Logger::log('Invalid maintenance request signature', [
                'email' => $email,
                'env'   => $env,
                'hid'   => $hid,
                'pc'    => $pc,
                'cl'    => $cl,
                'aid'   => $aid,
                'exp'   => $exp,
                'sig'   => $sig,
            ]);

            wp_redirect(home_url('/maintenance-link-invalid/'));
            exit;
        }

        // SUCCESS!!
        // Page is valid — Elementor renders the page and shows the form.
    }

    /**
     * Handles Elementor form submissions.
     *
     * @param \ElementorPro\Modules\Forms\Classes\Record $record
     * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
     */
    public static function handle_form_submission($record, $ajax_handler): void
    {
        $form_name = $record->get_form_settings('form_name');

        // Only handle the maintenance confirmation form
        if (strtolower($form_name) !== 'site maintenance request') {
            return;
        }

        $raw_fields = $record->get('fields');
        $fields = [];
        foreach ($raw_fields as $key => $field) {
            $fields[$key] = $field['value'];
        }

        Logger::log('Elementor form submitted', $fields);

        $email = $fields['email'] ?? '';
        $env   = sanitize_text_field($fields['env'] ?? '');
        $exp   = intval($fields['exp'] ?? 0);
        $sig   = sanitize_text_field($fields['sig'] ?? '');
        $hid   = sanitize_text_field($fields['hid'] ?? '');
        $pc    = sanitize_text_field($fields['pc'] ?? '');
        $cl    = sanitize_text_field($fields['cl'] ?? '');
        $aid   = sanitize_text_field($fields['aid'] ?? '');

        $expected = md5($env . $email . $exp . DMG_MAINT_SECRET);
        if (!hash_equals($expected, $sig)) {
            $ajax_handler->add_error_message('Invalid or expired link. Please request a new maintenance link or <a style="font-weight: 600;" href="/contact">contact us directly</a>.');
            Logger::log('Invalid signature on form submit', compact('email', 'env', 'sig', 'expected'));
            return;
        }

        // Idempotency check - prevent duplicate submissions
        if (Idempotency::checkAndRecord($email, $env, $sig)) {
            $ajax_handler->add_error_message('This maintenance request has already been submitted.');
            Logger::log('Duplicate submission blocked', compact('email', 'env', 'sig'));
            return;
        }

        // Validate required fields
        if (empty($pc) || empty($cl)) {
            $ajax_handler->add_error_message('Required information is missing. Please request a new maintenance link or <a style="font-weight: 600;" href="/contact">contact us directly</a>.');
            Logger::log('Missing required fields on form submit', compact('email', 'env', 'pc', 'cl'));
            Idempotency::markStatus($sig, 'failed');
            return;
        }

        $payload = [
            'env'        => $env,
            'email'      => $email,
            'exp'        => $exp,
            'sig'        => $sig,
            'hid'        => $hid,
            'pc'         => $pc,
            'cl'         => $cl,
            'aid'        => $aid,
            'timestamp'  => time(),
            'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];

        $url = getenv('DMG_N8N_WEBHOOK_URL') ?: ($_ENV['DMG_N8N_WEBHOOK_URL'] ?? get_option('dmg_n8n_webhook_url', ''));
        $key = getenv('DMG_AUTOMATION_KEY')  ?: ($_ENV['DMG_AUTOMATION_KEY']  ?? get_option('dmg_automation_key', ''));

        if (empty($url)) {
            $ajax_handler->add_error_message('Internal configuration error — webhook URL missing.');
            Idempotency::markStatus($sig, 'failed');
            Logger::log('Missing webhook URL', compact('email', 'env'));
            return;
        }

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type'     => 'application/json',
                'X-Automation-Key' => $key,
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            $ajax_handler->add_error_message('Could not contact automation server. Please try again later.');
            Idempotency::markStatus($sig, 'failed');
            Logger::log('Webhook POST error', [
                'email' => $email,
                'env'   => $env,
                'error' => $response->get_error_message(),
            ]);
            return;
        }

        $status = wp_remote_retrieve_response_code($response);
        $body   = wp_remote_retrieve_body($response);

        if ($status < 200 || $status >= 300) {
            $ajax_handler->add_error_message('Automation service returned an error. Please try again later.');
            Idempotency::markStatus($sig, 'failed');
            Logger::log('Webhook response status issue', [
                'email'  => $email,
                'env'    => $env,
                'status' => $status,
                'body'   => $body,
            ]);
            return;
        }

        Logger::log('Webhook submitted successfully', [
            'email'  => $email,
            'env'    => $env,
            'status' => $status,
        ]);

        // Mark request as processed
        Idempotency::markStatus($sig, 'success');
    }
}

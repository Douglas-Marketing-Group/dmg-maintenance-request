<?php

namespace DMG\DMGMaintenanceRequest;

class DMGMaintenanceRequest
{
    public static function check_signature()
    {
        $page = get_page_by_path('site-maintenance-request'); // ← your Elementor page slug
        if (! $page) return;

        // Only guard this page
        if (! is_page($page->ID)) return;

        // Step 1: Check for required query params
        $email = isset($_GET['email']) ? rawurldecode($_GET['email']) : '';
        $env   = isset($_GET['env']) ? sanitize_text_field($_GET['env']) : '';
        $exp   = isset($_GET['exp']) ? intval($_GET['exp']) : 0;
        $sig   = isset($_GET['sig']) ? sanitize_text_field($_GET['sig']) : '';

        if (! $email || ! $env || ! $exp || ! $sig) {
            wp_die('Missing or incomplete request parameters.', 'Invalid Request', 403);
        }

        // Step 2: Expiration check
        if (time() > $exp) {
            wp_die('This maintenance request link has expired.', 'Link Expired', 403);

            // wp_redirect(home_url('/maintenance-link-invalid/'));
            // exit;
        }

        // Step 3: Signature validation
        $expected_sig = md5($env . $email . $exp . DMG_MAINT_SECRET);

        if (!hash_equals($expected_sig, $sig)) {
            wp_die('Invalid maintenance request signature.', 'Invalid Request', 403);
        }

        // Page is valid — Elementor renders the page and shows the form.
    }

    private static function process_submission()
    {
        $email = sanitize_email($_POST['email']);
        $env   = sanitize_text_field($_POST['env']);
        $exp   = intval($_POST['exp']);
        $sig   = sanitize_text_field($_POST['sig']);

        // Verify again before accepting (defense in depth)
        if (md5($env . $email . $exp . DMG_MAINT_SECRET) !== $sig) {
            wp_die('Invalid signature.', 'Invalid', 403);
        }

        // Prepare webhook payload
        $payload = [
            'env'   => $env,
            'email' => $email,
            'exp'   => $exp,
            'sig'   => $sig,
            'timestamp' => time(),
        ];

        $response = wp_remote_post('https://your-n8n-instance/webhook/maintenance-confirm', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Automation-Key' => 'your_automation_key_here',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            wp_die('Failed to contact automation server.', 'Error', 500);
        }

        echo '<p>✅ Thank you — your maintenance request has been received. Our team will begin processing it shortly.</p>';
        exit;
    }
}

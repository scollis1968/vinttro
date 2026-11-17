<?php
/**
 * Plugin Name: SuiteCRM Integration for CF7
 * Description: Drops raw form submissions dynamically into visp_data_staging via hidden form fields.
 */

add_action('wpcf7_before_send_mail', 'suitecrm_forward_to_staging');

function suitecrm_forward_to_staging($contact_form) {
    error_log("SuiteCRM Integration: Form submission hook triggered.");

    $submission = WPCF7_Submission::get_instance();
    if (!$submission) {
        return;
    }

    $form_data = $submission->get_posted_data();

    // 1. Extract the data_type dynamically from the form submission or global $_POST
    $entry_type = !empty($form_data['data_type']) ? $form_data['data_type'] : ($_POST['data_type'] ?? '');
    $entry_type = sanitize_text_field($entry_type);

    // If there is no data_type hidden field, this form is not meant for SuiteCRM. Exit early.
    if (empty($entry_type)) {
        error_log("SuiteCRM Integration: Skipping form submission. No 'data_type' field found.");
        return;
    }

    error_log("SuiteCRM Integration: Processing dynamic entry type: " . $entry_type);

    // 2. Generate a clean, dynamic record name for SuiteCRM based on the data payload
    $readable_type = ucwords(str_replace('_', ' ', $entry_type));
    if (!empty($form_data['last-name'])) {
        $entry_name = "{$readable_type} - " . sanitize_text_field($form_data['last-name']);
    } elseif (!empty($form_data['vehicle-reg__1'])) {
        $entry_name = "{$readable_type} - " . sanitize_text_field($form_data['vehicle-reg__1']);
    } else {
        $entry_name = "{$readable_type} Submission - " . current_time('mysql');
    }

    // 3. Fetch Cached OAuth Access Token
    $token = suitecrm_get_access_token();
    if (!$token) {
        error_log("SuiteCRM Integration CRITICAL: Failed to retrieve API access token.");
        return;
    }

    // 4. Verify Configuration Constant
    if (defined('SUITECRM_URL')) {
        $url = rtrim(SUITECRM_URL, '/') . '/V8/module';
    } else {
        error_log("SuiteCRM Integration CRITICAL: SUITECRM_URL constant is not defined.");
        return;
    }

    // 5. Build payload matching exact visp_data_staging fields
    $payload = [
        'data' => [
            'type' => 'visp_data_staging',
            'attributes' => [
                'name'          => $entry_name,
                'status'        => 'pending',
                'data_type'     => $entry_type,
                'raw_data'      => json_encode($form_data),
                'source_system' => 'WordPress'
            ]
        ]
    ];

    // 6. POST payload to SuiteCRM
    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/vnd.api+json',
            'Accept'        => 'application/vnd.api+json'
        ],
        'body'    => json_encode($payload),
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        error_log('SuiteCRM Integration Staging Error: ' . $response->get_error_message());
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        error_log("SuiteCRM Integration Success: Dynamic data type '{$entry_type}' forwarded to staging. HTTP Status: {$status_code}");
    }
}

/**
 * Dynamically fetches and caches the SuiteCRM API Token using WP Transients
 */
function suitecrm_get_access_token() {
    $cached_token = get_transient('suitecrm_api_token');
    if ($cached_token) {
        return $cached_token;
    }

    if (!defined('SUITECRM_CLIENT_ID') || !defined('SUITECRM_CLIENT_SECRET') || !defined('SUITECRM_URL') || !defined('SUITECRM_USERNAME') || !defined('SUITECRM_PASSWORD')) { 
        error_log(__FUNCTION__ . ': Missing SuiteCRM configuration constants in wp-config.php');
        return null;
    }

    $api_url = rtrim(SUITECRM_URL, '/') . '/access_token';

    $response = wp_remote_post($api_url, [
        'body' => [
            'grant_type'    => 'password',
            'client_id'     => SUITECRM_CLIENT_ID,
            'client_secret' => SUITECRM_CLIENT_SECRET,
            'username'      => SUITECRM_USERNAME,
            'password'      => SUITECRM_PASSWORD
        ],
        'timeout'   => 15,
        'sslverify' => false
    ]);

    if (is_wp_error($response)) {
        error_log('SuiteCRM API Auth Error: ' . $response->get_error_message());
        return null;
    }

    $body  = json_decode(wp_remote_retrieve_body($response), true);
    $token = $body['access_token'] ?? null;

    if ($token) {
        set_transient('suitecrm_api_token', $token, 3300);
    } else {
        error_log('SuiteCRM API Auth Error: No Token returned in body ' . json_encode($body));
    }

    return $token;
}
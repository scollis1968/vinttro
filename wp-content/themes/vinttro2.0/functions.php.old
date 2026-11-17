<?php
/**
 * VINTTRO Custom Theme Functions
 */

// 1. LINK TO PARENT THEME (Twenty Twenty-Five)
add_action( 'wp_enqueue_scripts', 'vinttro_enqueue_styles' );
function vinttro_enqueue_styles() {
    // This pulls in the CSS from the Twenty Twenty-Five folder
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
}

// 2. SUITECRM V8 API INTEGRATION
// Step 1: Store user ID after registration
add_action('user_register', 'suitecrm_store_user_id_after_registration', 10, 1);

function suitecrm_store_user_id_after_registration($user_id) {
    set_transient("suitecrm_user_$user_id", $user_id, 60); 
}

// Step 2: Push to SuiteCRM on profile update
add_action('profile_update', 'sync_new_user_to_suitecrm', 10, 2);

function suitecrm_get_access_token() {
    $client_id     = 'c915aea8-a713-354f-1ad7-68b5bba1d31e';
    $client_secret = 'szzdfjkhksdjhfkdjOwvBIEDrUd6drEyFdG72nWR2';
    $api_url       = 'https://localhost/legacy/Api/access_token';

    $response = wp_remote_post($api_url, [
        'body' => [
            'grant_type'    => 'client_credentials',
            'client_id'     => $client_id,
            'client_secret' => $client_secret
        ],
        'timeout'   => 15,
        'sslverify' => false
    ]);

    if (is_wp_error($response)) {
        error_log('SuiteCRM API Auth Error: ' . $response->get_error_message());
        return null;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['access_token'] ?? null;
}

function sync_new_user_to_suitecrm($user_id, $old_user_data) {
    if (!get_transient("suitecrm_user_$user_id")) {
        return; 
    }
    delete_transient("suitecrm_user_$user_id");

    $user_info = get_userdata($user_id);
    $user_roles = (array) $user_info->roles;
    
    // Sync all roles for now
    $should_sync = $user_roles;

    if (empty($should_sync)) {
        return;
    }

    $token = suitecrm_get_access_token();
    if (!$token) {
        error_log('SuiteCRM API Authentication Failed.');
        return;
    }

    $post_url = 'https://localhost/legacy/Api/V8/module';
    $lead_data = [
        'data' => [
            'type' => 'Leads',
            'attributes' => [
                'first_name'   => get_user_meta($user_id, 'first_name', true) ?: 'Unknown',
                'last_name'    => get_user_meta($user_id, 'last_name', true) ?: $user_info->user_login,
                'email1'       => $user_info->user_email,
                'phone_work'   => get_user_meta($user_id, 'billing_phone', true) ?: '',
                'account_name' => get_user_meta($user_id, 'billing_company', true) ?: '',
                'lead_source'  => 'Web Site',
                'status'       => 'New',
                'assigned_user_id' => '1'
            ]
        ]
    ];

    wp_remote_post($post_url, [
        'headers' => [
            'Content-Type'  => 'application/vnd.api+json',
            'Authorization' => 'Bearer ' . $token
        ],
        'body'      => json_encode($lead_data),
        'timeout'   => 15,
        'sslverify' => false
    ]);
}

// 3. COMPLIANZ ACCESSIBILITY FIX
add_filter('cmplz_banner_html', function($html) {
    $search = '/(<summary.*?>)(.*?)(<input.*?class="cmplz-consent-checkbox".*?>)(.*?)(<\/summary>)/s';
    $replace = '$1$2$5$3$4'; 
    return preg_replace($search, $replace, $html);
}, 10, 1);
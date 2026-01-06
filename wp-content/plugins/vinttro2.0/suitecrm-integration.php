<?php
/**
 * Plugin Name: SuiteCRM Integration for CF7
 */

add_action('wpcf7_before_send_mail', 'sync_cf7_to_suitecrm');

function sync_cf7_to_suitecrm($contact_form) {
    error_log("suitecrm-integration wpcf7_before_send_mail triggered.");

    $submission = WPCF7_Submission::get_instance();
    if (!$submission) return;

    $data = $submission->get_posted_data();

    // Check Form ID
    if ($contact_form->id() != 2303) return;

    // 1. Get Access Token - Note: We pass the CONSTANTS here directly
    $token = get_token(
        SUITECRM_URL, 
        SUITECRM_USERNAME, 
        SUITECRM_PASSWORD, 
        SUITECRM_CLIENT_ID, 
        SUITECRM_CLIENT_SECRET
    );
    
    error_log("suitecrm-integration token received.");

    // 2. Send Data to SuiteCRM
    if ($token) {
        create_suitecrm_record($token, $data);
        error_log("suitecrm-integration after create_suitecrm_record");
    }
}

function get_token($url, $username, $password, $client_id, $client_secret) {


    $ch = curl_init();
    
    // SuiteCRM V8 uses /Api/access_token
    // We'll clean up the URL logic here to ensure it hits the right endpoint
    $token_url = rtrim($url, '/') . '/access_token'; 

    $login_data = json_encode([
        'grant_type'    => 'password',
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'username'      => $username,
        'password'      => $password,
    ]);

    curl_setopt_array($ch, [
        CURLOPT_URL            => $token_url,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $login_data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/vnd.api+json',
            'Accept: application/vnd.api+json'
        ],
        CURLOPT_SSL_VERIFYPEER => false, // Only for debugging
    ]);

    $response = curl_exec($ch);
    $data = json_decode($response, true);
    curl_close($ch);

    if (isset($data['access_token'])) {
        return $data['access_token'];
    } else {
        error_log("SuiteCRM Auth Failed: " . $response);
        return false;
    }
}

function create_suitecrm_record($token, $form_data) {
    // Again, use the Constant for the base URL
    $url = rtrim(SUITECRM_URL, '/') . '/V8/module';
    
    $payload = [
        'data' => [
            'type' => 'Leads',
            'attributes' => [
                'last_name'   => $form_data['last-name'],
                'email'      => $form_data['email'],
                'description' => $form_data['additional-information'],
            ]
        ]
    ];

    wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/vnd.api+json',
            'Accept'        => 'application/vnd.api+json'
        ],
        'body' => json_encode($payload)
    ]);
}
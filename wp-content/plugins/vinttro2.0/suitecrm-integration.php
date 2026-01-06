<?php

$suitecrm_url = SUITECRM_URL;
$username = SUITECRM_USERNAME;
$password = SUITECRM_PASSWORD;
$client_id = SUITECRM_CLIENT_ID;
$client_secret = SUITECRM_CLIENT_SECRET;

add_action('wpcf7_before_send_mail', 'sync_cf7_to_suitecrm');

function sync_cf7_to_suitecrm($contact_form) {
    error_log("suitecrm-integration wpcf7_before_send_mail triggered.");

    // Get the submission instance
    $submission = WPCF7_Submission::get_instance();
    if (!$submission) return;

    // Get the posted data
    $data = $submission->get_posted_data();

    // Only run for a specific form (replace 123 with your Form ID)
    error_log("suitecrm-integration - Contact_form.id = " . $contact_form->id());
    if ($contact_form->id() != 123) return;

    // 1. Get Access Token from SuiteCRM
    $token = get_token($suitecrm_url, $username, $password,$client_id,$client_secret);
    echo "Successfully authenticated. Starting import...\n";

    // 2. Send Data to SuiteCRM
    if ($token) {
        create_suitecrm_record($token, $data);
    }
}
function get_token($url, $username, $password, $client_id, $client_secret ) {
    $ch = curl_init();
    $login_data = json_encode([
        'grant_type' => 'password',
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'username' => $username,
        'password' => $password,
    ]);

    curl_setopt_array($ch, [
        CURLOPT_URL => str_replace('/V8/module', '/access_token', $url),
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $login_data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/vnd.api+json',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($ch);
    $data = json_decode($response, true);
    curl_close($ch);

    if (isset($data['access_token'])) {
        return $data['access_token'];
    } else {
        die("Failed to get access token: " . $response);
    }
}

function create_suitecrm_record($token, $form_data) {
    $url = 'https://your-crm-link.com/Api/V8/module';
    $payload = [
        'data' => [
            'type' => 'Leads', // or 'AOS_Quotes' for Quote requests
            'attributes' => [
                'last_name' => $form_data['your-name'],
                'email1' => $form_data['your-email'],
                'description' => $form_data['your-message'],
            ]
        ]
    ];

    wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($payload)
    ]);
}
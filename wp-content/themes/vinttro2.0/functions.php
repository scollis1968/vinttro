// **************************************************************************************** SC20250902
// Custom Edits for SuiteCRM V8 API
// Step 1: Store user ID after registration
add_action('user_register', 'suitecrm_store_user_id_after_registration', 10, 1);

function suitecrm_store_user_id_after_registration($user_id) {
    set_transient("suitecrm_user_$user_id", $user_id, 60); // Store user ID for 60 seconds
}

// Step 2: Wait for meta to be updated before pushing to SuiteCRM
add_action('profile_update', 'sync_new_user_to_suitecrm', 10, 2);

function suitecrm_get_access_token() {
    $client_id = 'c915aea8-a713-354f-1ad7-68b5bba1d31e';
    $client_secret = 'szzdfjkhksdjhfkdjOwvBIEDrUd6drEyFdG72nWR2';
    $api_url = 'https://localhost/legacy/Api/access_token';

    $response = wp_remote_post($api_url, [
        'body' => [
            'grant_type' => 'client_credentials',
            'client_id' => $client_id,
            'client_secret' => $client_secret
        ],
        'timeout' => 15,
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
    // Check if this is a newly registered user by looking up the transient
    if (!get_transient("suitecrm_user_$user_id")) {
        return; // Exit if this is not a new user
    }
    delete_transient("suitecrm_user_$user_id"); // Remove transient after use

    $user_info = get_userdata($user_id);
    $user_roles = (array) $user_info->roles; // Get user's roles

    // Allowed roles for SuiteCRM sync
    //$allowed_roles = suitecrm_get_allowed_roles(); // Function to return allowed roles
    $allowed_roles =  $user_roles;
    $should_sync = array_intersect($user_roles, $allowed_roles);

    // Log user details for debugging
    //error_log('User Info Retrieved: ' . print_r($user_info, true));
    //error_log('First Name: ' . get_user_meta($user_id, 'first_name', true));
    //error_log('Last Name: ' . get_user_meta($user_id, 'last_name', true));
    //error_log('User Roles: ' . print_r($user_roles, true));

    // Skip if user role is not allowed
    if (empty($should_sync)) {
        error_log('SuiteCRM Sync Skipped: User role not in allowed list.');
        return;
    }

   // error_log('SuiteCRM Sync Function Triggered for User ID: ' . $user_id);

    // Get API token
    $token = suitecrm_get_access_token();
    if (!$token) {
        error_log('SuiteCRM API Authentication Failed - Could not push lead.');
        return;
    }

    // Prepare Lead Data
    //$post_url = trailingslashit(get_option('https://localhost')) . 'legacy/Api/V8/module';
    $post_url = 'https://localhost/legacy/Api/V8/module';
    $lead_data = [
        'data' => [
            'type' => 'Leads',
            'attributes' => [
                'first_name'  => get_user_meta($user_id, 'first_name', true) ?: 'Unknown',
                'last_name'   => get_user_meta($user_id, 'last_name', true) ?: $user_info->user_login,
                'email1'      => $user_info->user_email,
                'phone_work'  => get_user_meta($user_id, 'billing_phone', true) ?: '',
                'account_name'=> get_user_meta($user_id, 'billing_company', true) ?: '',
                'lead_source' => 'Web Site',  //Assign lead source
                'status'      => 'New',     // Assign lead status
                            'assigned_user_id' => '1' // Assign to user ID 1 (pablostevens)
            ]
        ]
    ];

    // Send API Request
    $response = wp_remote_post($post_url, [
        'headers' => [
            'Content-Type'  => 'application/vnd.api+json',
            'Authorization' => 'Bearer ' . $token
        ],
        'body' => json_encode($lead_data),
        'timeout' => 15,
                'sslverify' => false // <-- Add this line here
    ]);

    // Log for debugging
    if (is_wp_error($response)) {
        error_log('SuiteCRM Lead Creation Failed: ' . $response->get_error_message());
    } else {
        $response_body = wp_remote_retrieve_body($response);
      //  error_log('SuiteCRM Lead Creation Response: ' . $response_body);
    }
}
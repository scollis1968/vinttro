<?php

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

function suitecrm_conditional_menu_stub( $items, $args ) {
/**
 * Custom function to modify menu items based on complex user data (e.g., SuiteCRM).
 * * @param string $items The HTML list items of the current menu.
 * @param object $args  The arguments of the current menu.
 * @return string The modified HTML list items.
 */

    // --- 1. TARGET THE RIGHT MENU (Crucial Step) ---
    // Change 'primary' to the slug of the menu location you are modifying.
    // If you're unsure, you can remove this check to test all menus, 
    // but it's best practice to target one.
    if ( $args->theme_location == 'primary' ) {
        
        // --- 2. CHECK LOGIN STATUS ---
        if ( is_user_logged_in() ) {
            
            // Get the current user ID
            $user_id = get_current_user_id();

            // --- 3. CUSTOM LOGIC STUB (Replace this with SuiteCRM API Call) ---
            
            // *** DEMO CONDITION STUB ***
            // Replace this block with your SuiteCRM API calls to check subscription status.
            // For the demo, we'll check if the User ID is an even number.
            
            $is_subscribed_to_premium = ( $user_id % 2 == 0 );
            // --- END DEMO CONDITION STUB ---


            // --- 4. INSERT MENU ITEM HTML ---
            if ( $is_subscribed_to_premium ) {
                // If the user meets the condition (e.g., subscribed via SuiteCRM data)
                
                // Note: The HTML must be a standard <li> element.
                $custom_item_html = '<li class="menu-item menu-item-suitecrm-special">';
                $custom_item_html .= '<a href="/premium-dashboard/">🔥 Premium Dashboard</a>';
                $custom_item_html .= '</li>';

                // Append the custom item to the existing menu items
                $items .= $custom_item_html;
            }
            
        } else {
            // Optional: You could add a specific "Log In" link here if one doesn't exist.
            // But often, this is better handled by adding it directly in the WP Menu Editor.
        }
    }

    return $items;
}
add_filter( 'wp_nav_menu_items', 'suitecrm_conditional_menu_stub', 10, 2 );

// ---
// Optional: If you need to REMOVE a menu item, you would use str_replace or regex 
// within the function to strip the HTML of the unwanted item before returning $items.
// ---

?>
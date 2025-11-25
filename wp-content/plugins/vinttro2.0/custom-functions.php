<?php
/**
 * Plugin Name:       VINTTRO2.0 Custom Functions
 * Description:       A collection of my custom functions.
 * Version:           1.0.1a
 * Author:            Stephen Collis
 */



// ---------------------------------------------------
// Start of custom code
add_filter( 'wpcf7_special_mail_tags', 'my_custom_email_tags', 10, 2 );

function my_custom_email_tags( $output, $name ) {
    // Log the name of the tag being processed
    //error_log('Contact Form 7 mail tag: ' . $name);

    switch ( $name ) {
        case '_ACCOUNTS_EMAIL':
            // Check if the constant is defined
            if ( defined('ACCOUNTS_EMAIL_ADDRESS') ) {
                $output = ACCOUNTS_EMAIL_ADDRESS;
            } else {
                error_log('Constant ' . $name . '_ADDRESS is NOT defined.');
            }
            break;
        case '_INSURANCE_EMAIL':
            // Check if the constant is defined
            if ( defined('INSURANCE_EMAIL_ADDRESS') ) {
                $output = INSURANCE_EMAIL_ADDRESS;
            } else {
                error_log('Constant ' . $name . '_ADDRESS is NOT defined.');
            }
            break;
        // ... other cases
    }

    return $output;
}


function redirect_cf7_subscribers_only() {
    // Check if Contact Form 7 is active
    if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
        return;
    }

    // Get the page ID of the page with the subscribers-only form
    $subscribers_form_page_id = 75; // Replace with your actual page ID

    // Check if we are on the correct page
    if ( is_page( $subscribers_form_page_id ) && ! is_user_logged_in() ) {
        // Redirect to the guest form page
        wp_redirect( 'https://wordpress.uat.vinttro.co.uk/register/' );
        exit;
    }
}
add_action( 'wp', 'redirect_cf7_subscribers_only' );

// End of custom code.
// -----------------------------------------------------------------------------------------------------------------------------------------------



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
    if ( ! defined('CRM_CLIENT_ID') ) { 
        error_log(__FUNCTION__ . ': No CRM_CLIENT_ID defined! ( Check your wp-config.php ).' );
        return null;
    }
    $client_id = CRM_CLIENT_ID;

    if ( ! defined('CRM_CLIENT_SECRET') ) { 
        error_log(__FUNCTION__ . ': No CRM_CLIENT_SECRET defined! ( Check your wp-config.php ).' );
        return null;
    }
    $client_secret = CRM_CLIENT_SECRET;

    if ( ! defined('CRM_URL') ) { 
        error_log(__FUNCTION__ . ': No CRM_URL defined ( Check your wp-config.php ).' );
        return null;
    }
    $api_url = CRM_URL . '/Api/access_token';

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
    $post_url = CRM_URL . '/Api/V8/module';
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

document.addEventListener('DOMContentLoaded', function() {
    // 1. Get references to the file input and the preview container
    const fileInput = document.querySelector('input[type="file"][name="quote-image"]');
    const previewContainer = document.getElementById('quote-image-preview-container');

    if (fileInput && previewContainer) {
        fileInput.addEventListener('change', function(event) {
            const file = event.target.files[0];

            // Clear any previous preview
            previewContainer.innerHTML = '';

            // Check if a file was selected and it's an image
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();

                // 2. Define what happens once the file is read
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result; // The data URL of the image

                    // 3. Apply basic styling for the preview
                    img.style.maxWidth = '100%'; 
                    img.style.height = 'auto';
                    img.style.borderRadius = '5px';
                    img.style.border = '1px solid #ccc';
                    img.style.maxHeight = '200px'; // Limit preview size

                    // 4. Insert the image into the container
                    previewContainer.appendChild(img);
                };

                // 5. Read the file as a Data URL
                reader.readAsDataURL(file);
            }
        });
    }
});

add_filter( 'wp_nav_menu_items', 'suitecrm_conditional_menu_stub', 10, 2 );

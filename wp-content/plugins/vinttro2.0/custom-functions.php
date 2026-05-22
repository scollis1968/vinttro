<?php
/**
 * Plugin Name:       VINTTRO2.0 Custom Functions
 * Description:       A collection of my custom functions.
 * Version:           1.0.1a
 * Author:            Stephen Collis
 */



// Start of custom code
// ---------------------------------------------------

require_once 'includes/modal-form-functions.php';
require_once 'includes/conditional-menus.php';
require_once 'includes/suitecrm-integration.php';
require_once 'includes/deployment-functions.php';
require_once 'includes/auto-listings-functions.php';
require_once 'includes/visp-api-functions.php';
require_once 'includes/membership-functions.php';
require_once 'includes/twilio-rtc.php';


function my_plugin_load_styles() {
    // 1. Enqueue your custom CSS
    wp_enqueue_style( 'my-custom-form-styles', plugins_url( 'custom-styles.css', __FILE__ ), array(), '1.0.2' );
    
    // 2. Force a tiny bit of "Late" CSS directly into the header to kill the background scroll
    // This is safer than a file for the 'body:has' rule
    $custom_css = "
        body:has(#sgpb-popup-dialog-main-div-wrapper) { overflow: hidden !important; }
        .sgpb-popup-dialog-main-div-theme-wrapper-3 { left: 0 !important; right: 0 !important; display: flex !important; justify-content: center !important; }
    ";
    wp_add_inline_style( 'my-custom-form-styles', $custom_css );
}
// Use priority 99 to ensure it fires after other plugins
add_action( 'wp_enqueue_scripts', 'my_plugin_load_styles', 99 );

//--------------------------------------------------------
function my_plugin_load_scripts() {
    // 1. Enqueue your existing global form preview script
    wp_enqueue_script( 'my-form-preview-script', plugins_url( 'custom-scripts.js', __FILE__ ), array('jquery'), '1.6', true );

    // ==========================================================
    // 📞 2. TWILIO WEBRTC ENGINE: TARGET ANY PAGE UNDER /visp
    // ==========================================================
    // Checks if the current URL contains '/visp' anywhere (case-insensitive wildcard)
    if ( stripos( $_SERVER['REQUEST_URI'], '/visp' ) !== false ) {
        
    // Load the explicit, un-blocked release version from Twilio's cloud
    wp_enqueue_script(
        'twilio-video-cdn-pinned', 
        'https://sdk.twilio.com/js/video/releases/2.35.0/twilio-video.min.js', 
        array(), 
        '2.35.0', 
        true
    );

        // B. Load your modular WebRTC layout tracking code
        wp_enqueue_script(
            'vinttro-rtc-client', 
            plugins_url( 'js/rtc-client.js', __FILE__ ), // Looks for /js/rtc-client.js inside your plugin
            array('twilio-video-cdn', 'jquery'),         // Ensures Twilio & jQuery load first
            '1.0.0', 
            true
        );

        // C. Inject security Nonces and local API routing paths into the script context
        wp_localize_script( 'vinttro-rtc-client', 'vinttroSettings', array(
            'root'  => esc_url_raw( rest_url() ),
            'nonce' => wp_create_nonce( 'wp_rest' )
        ));
    }
}
add_action( 'wp_enqueue_scripts', 'my_plugin_load_scripts' );
//--------------------------------------------------------


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
        $redirect_url = home_url( '/register/' );
        wp_redirect( $redirect_url );
        exit;
    }
}
add_action( 'wp', 'redirect_cf7_subscribers_only' );

// End of custom code.
// -----------------------------------------------------------------------------------------------------------------------------------------------



// **************************************************************************************** SC20250902
// Custom Edits for SuiteCRM V8 API
// Step 1: Store user ID after registration
/*
add_action('user_register', 'suitecrm_store_user_id_after_registration', 10, 1);

function suitecrm_store_user_id_after_registration($user_id) {
    set_transient("suitecrm_user_$user_id", $user_id, 60); // Store user ID for 60 seconds
}
*/

// Step 2: Wait for meta to be updated before pushing to SuiteCRM
/*
add_action('profile_update', 'sync_new_user_to_suitecrm', 10, 2);



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
*/

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


/**
 * Add GA4 Tracking Code to Head, EXCLUDING all Administrators
 */
add_action('wp_head', 'vinttro_add_analytics_head');
function vinttro_add_analytics_head() {
    if ( !current_user_can( 'manage_options' ) ) {
        ?>
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','GTM-MBKX7FRM');</script>
        <?php
    }
}

// 2. The Body Script (The part GTM is asking for now)
add_action('wp_body_open', 'vinttro_add_analytics_body');
function vinttro_add_analytics_body() {
    if ( !current_user_can( 'manage_options' ) ) {
        ?>
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-MBKX7FRM"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <?php
    }
}
add_action( 'show_user_profile', 'display_custom_user_meta' );
add_action( 'edit_user_profile', 'display_custom_user_meta' );

function display_custom_user_meta( $user ) {
    ?>
    <h3>Custom User Meta Data</h3>
    <table class="form-table">
        <?php 
        $meta_data = get_user_meta( $user->ID ); 
        foreach ( $meta_data as $key => $value ) {
            // We usually want to hide internal WP keys starting with an underscore
            if ( strpos( $key, '_' ) !== 0 ) {
                echo '<tr><th>' . esc_html( $key ) . '</th><td>' . esc_html( $value[0] ) . '</td></tr>';
            }
        }
        ?>
    </table>
    <?php
}

// Inside wp-content/plugins/vinttro2.0/custom-functions.php

/**
 * THE NUCLEAR OPTION: Global HTML Output Buffer Filter
 * Intercepts the final page rendering for both Frontend and Backend,
 * automatically converting all internal HTTP links to HTTPS on the fly.
 */
add_action('init', 'vinttro_force_global_ssl_buffer');
function vinttro_force_global_ssl_buffer() {
    // Only trigger this if we aren't handling a raw file or command line action
    if (!is_admin() && !defined('DOING_AJAX') && !defined('DOING_CRON')) {
        ob_start('vinttro_global_http_rewrite_callback');
    }
}

// Separate hook specifically for the admin backend dashboard
add_action('admin_init', 'vinttro_force_admin_ssl_buffer');
function vinttro_force_admin_ssl_buffer() {
    ob_start('vinttro_global_http_rewrite_callback');
}

// The clean-up execution worker
function vinttro_global_http_rewrite_callback($output) {
    if (empty($output)) return $output;
    // Swap your exact staging URL strings perfectly before the browser can see them
    return str_replace('http://uat.vinttro.co.uk', 'https://uat.vinttro.co.uk', $output);
}
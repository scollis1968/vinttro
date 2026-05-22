<?php
/**
 * Vinttro Plugin Module: WebRTC Client & Token Infrastructure
 */

// 1. CONDITIONALLY ENQUEUE CLIENT SCRIPTS
add_action('wp_enqueue_scripts', 'vinttro_enqueue_plugin_rtc_assets');
function vinttro_enqueue_plugin_rtc_assets() {
    // Keep it restricted to your specific page
    if ( is_page('visp') ) {
        
        // Load Twilio Video SDK from CDN
        wp_enqueue_script(
            'twilio-video-cdn', 
            'https://sdk.twilio.com/js/video/v2/twilio-video.min.js', 
            array(), 
            '2.0', 
            true
        );

        // Load your custom frontend script from the plugin's JS folder
        // (Assumes you have a 'js' folder inside your plugin)
        wp_enqueue_script(
            'vinttro-rtc-client', 
            plugin_dir_url( dirname(__FILE__) ) . 'js/rtc-client.js', 
            array('twilio-video-cdn'), 
            '1.0', 
            true
        );

        wp_localize_script('vinttro-rtc-client', 'vinttroSettings', array(
            'root'  => esc_url_raw( rest_url() ),
            'nonce' => wp_create_nonce( 'wp_rest' )
        ));
    }
}

// 2. REGISTER THE SECURE WP-REST ENDPOINT
add_action('rest_api_init', 'vinttro_register_plugin_rtc_route');
function vinttro_register_plugin_rtc_route() {
    register_rest_route('vinttro/v1', '/rtc-token', array(
        'methods'             => 'POST',
        'callback'            => 'vinttro_generate_plugin_rtc_token',
        'permission_callback' => 'vinttro_check_plugin_employee_access',
    ));
}

// 3. SECURITY FIREWALL: Employee Check
function vinttro_check_plugin_employee_access() {
    if ( ! is_user_logged_in() ) {
        return false;
    }
    $current_user = wp_get_current_user();
    return str_ends_with( strtolower($current_user->user_email), '@vinttro.co.uk' );
}

// 4. GENERATE TOKENS USING PLUGIN PATHS
function vinttro_generate_plugin_rtc_token( WP_REST_Request $request ) {
    
    // 🚀 FIX: Step cleanly out of the 'includes' folder into the plugin root
    $autoload_path = dirname( __DIR__ ) . '/vendor/autoload.php';
    
    if ( file_exists( $autoload_path ) ) {
        require_once $autoload_path;
    } else {
        return new WP_Error('missing_sdk', 'Twilio SDK not found in plugin', array('status' => 500));
    }

    $current_user = wp_get_current_user();
    
    $accountSid   = defined('TWILIO_ACCOUNT_SID') ? TWILIO_ACCOUNT_SID : 'ACxxxxxx';
    $apiKeySid    = defined('TWILIO_API_KEY_SID') ? TWILIO_API_KEY_SID : 'SKxxxxxx';
    $apiKeySecret = defined('TWILIO_API_KEY_SECRET') ? TWILIO_API_KEY_SECRET : 'secretxxxx';

    $identity = $current_user->user_login;
    $roomName = $request->get_param('roomName') ? sanitize_text_field($request->get_param('roomName')) : 'vinttro-hq';

    $token = new Twilio\Jwt\AccessToken($accountSid, $apiKeySid, $apiKeySecret, 3600, $identity);
    
    $videoGrant = new Twilio\Jwt\Grants\VideoGrant();
    $videoGrant->setRoom($roomName);
    $token->addGrant($videoGrant);

    return new WP_REST_Response( array( 'token' => $token->toJWT() ), 200 );
}
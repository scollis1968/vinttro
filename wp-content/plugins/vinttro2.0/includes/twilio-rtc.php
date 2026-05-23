<?php
/**
 * Vinttro 2.0 - Twilio Infrastructure Real-Time Communication Node
 * Handles token distribution grants and active telecom call intercepts.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent direct file execution access
}

// ==========================================================
// 🛰️ 1. CENTRAL ROUTE COMPILATION INDEX REGISTER
// ==========================================================
add_action( 'rest_api_init', 'vinttro_register_all_rtc_routes' );
function vinttro_register_all_rtc_routes() {
    
    // Endpoint A: Token Distribution Gateway
    register_rest_route( 'vinttro/v1', '/rtc-token', array(
        'methods'             => 'POST',
        'callback'            => 'vinttro_generate_plugin_rtc_token',
        'permission_callback' => 'vinttro_check_plugin_employee_access',
    ) );

    // Endpoint B: Landline Intercept Call Bridge Routing Channel
    register_rest_route( 'vinttro/v1', '/accept-call', array(
        'methods'             => 'POST',
        'callback'            => 'vinttro_agent_bridge_call',
        'permission_callback' => 'vinttro_check_plugin_employee_access',
    ) );
}

// ==========================================================
// 🔐 2. CENTRAL FIREWALL ACCESS SECURITY HANDSHAKE
// ==========================================================
function vinttro_check_plugin_employee_access() {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    $current_user = wp_get_current_user();
    $email        = strtolower( trim( $current_user->user_email ) );

    // Authorize matching employee domains exclusively
    if ( str_ends_with( $email, '@vinttro.co.uk' ) || str_ends_with( $email, '@vinttro.com' ) ) {
        return true; 
    }

    return false;
}

// ==========================================================
// 🛠️ 3. HELPER: STANDARDIZED SDK INITIALIZER
// ==========================================================
function vinttro_get_twilio_sdk_client() {
    $autoload_path = dirname( __DIR__ ) . '/vendor/autoload.php';
    if ( file_exists( $autoload_path ) ) {
        require_once $autoload_path;
    } else {
        throw new Exception('Composer autoloader framework missing at: ' . $autoload_path);
    }

    $accountSid = defined('TWILIO_ACCOUNT_SID') ? TWILIO_ACCOUNT_SID : '';
    $apiKeySid  = defined('TWILIO_API_KEY_SID') ? TWILIO_API_KEY_SID : '';
    $secret     = defined('TWILIO_API_KEY_SECRET') ? TWILIO_API_KEY_SECRET : '';

    return new \Twilio\Rest\Client($apiKeySid, $secret, $accountSid);
}

// ==========================================================
// 🎟️ 4. CALLBACK A: GENERATE VIDEO & SYNC ACCESS TOKENS
// ==========================================================
function vinttro_generate_plugin_rtc_token( WP_REST_Request $request ) {
    $autoload_path = dirname( __DIR__ ) . '/vendor/autoload.php';
    if ( file_exists( $autoload_path ) ) {
        require_once $autoload_path;
    } else {
        return new WP_Error('missing_sdk', 'Twilio SDK framework missing.', array('status' => 500));
    }

    $accountSid   = defined('TWILIO_ACCOUNT_SID') ? TWILIO_ACCOUNT_SID : '';
    $apiKeySid    = defined('TWILIO_API_KEY_SID') ? TWILIO_API_KEY_SID : '';
    $apiKeySecret = defined('TWILIO_API_KEY_SECRET') ? TWILIO_API_KEY_SECRET : '';

    $current_user = wp_get_current_user();
    $identity     = $current_user->user_login;

    // Compile secure Twilio Token framework instance
    $token = new \Twilio\Jwt\AccessToken($accountSid, $apiKeySid, $apiKeySecret, 3600, $identity);

    // Inject WebRTC Video Channel allocation permission grant
    $videoGrant = new \Twilio\Jwt\Grants\VideoGrant();
    $token->addGrant($videoGrant);

    // Inject WebSocket Real-time Sync broadcast network access grant
    if ( defined('TWILIO_SYNC_SERVICE_SID') && !empty(TWILIO_SYNC_SERVICE_SID) ) {
        $syncGrant = new \Twilio\Jwt\Grants\SyncGrant();
        $syncGrant->setServiceSid(TWILIO_SYNC_SERVICE_SID);
        $token->addGrant($syncGrant);
    }

    return new WP_REST_Response(array(
        'token'    => $token->toJWT(),
        'identity' => $identity
    ), 200);
}

// ==========================================================
// 📞 5. CALLBACK B: REDIRECT TELEPHONE OUT OF HOLD LOOP
// ==========================================================
function vinttro_agent_bridge_call( WP_REST_Request $request ) {
    $call_sid = $request->get_param('callSid');
    $room_id  = $request->get_param('roomId');

    if ( empty($call_sid) || empty($room_id) ) {
        return new WP_Error('missing_parameters', 'Required parameters missing.', array('status' => 400));
    }

    try {
        $sdk = vinttro_get_twilio_sdk_client();
        
        // Intercept target PSTN channel stream and swap hold music with WebRTC Room Canvas
        $sdk->calls($call_sid)->update([
            "twiml" => '<?xml version="1.0" encoding="UTF-8"?><Response><Connect><Room>' . $room_id . '</Room></Connect></Response>'
        ]);

        return new WP_REST_Response(array('success' => true), 200);

    } catch (Exception $e) {
        return new WP_Error('bridge_execution_failure', $e->getMessage(), array('status' => 500));
    }
}
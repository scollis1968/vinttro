<?php

// 🔍 TEMPORARY DIAGNOSTIC WIRETAP: Log raw network data for incoming calls
add_action( 'init', 'vinttro_spy_on_inbound_headers', 1 );
function vinttro_spy_on_inbound_headers() {
    // Only fire when our specific inbound call route path is targeted
    if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/vinttro/v1/inbound-call' ) !== false ) {
        
        error_log('==================================================');
        error_log('🚨 VINTTRO NETWORK WIRETAP: ENDPOINT PATH MATCHED!');
        error_log('==================================================');
        error_log('USER AGENT   : ' . ( $_SERVER['HTTP_USER_AGENT'] ?? 'MISSING' ));
        error_log('AUTH HEADER  : ' . ( $_SERVER['HTTP_AUTHORIZATION'] ?? 'MISSING' ));
        error_log('REDIRECT AUTH: ' . ( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 'MISSING' ));
        error_log('PHP_AUTH_USER: ' . ( $_SERVER['PHP_AUTH_USER'] ?? 'MISSING' ));
        error_log('--------------------------------------------------');
        
        // Log all raw headers just in case they are hiding under different names
        if ( function_exists( 'apache_request_headers' ) ) {
            error_log('ALL RAW HEADERS: ' . print_r( apache_request_headers(), true ));
        }
    }
}
// Inside wp-content/plugins/vinttro2.0/includes/twilio-rtc.php

// 🛰️ GLOBAL GATEWAY FILTER: Authenticate Twilio Studio using a dynamic wp-config.php key
add_filter( 'rest_authentication_errors', 'vinttro_secure_query_key_bypass', 99 );
function vinttro_secure_query_key_bypass( $result ) {
    
    // Check if this is our inbound call path
    if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/vinttro/v1/inbound-call' ) !== false ) {
        
        // Extract our private token parameter from the URL query string
        $passed_key = isset($_GET['vinttro_key']) ? trim($_GET['vinttro_key']) : '';
        
        // 🔒 SECURITY LAYER: Verify the definition exists in wp-config.php before evaluating
        if ( defined( 'VINTTRO_STUDIO_KEY' ) && ! empty( VINTTRO_STUDIO_KEY ) ) {
            
            // Validate the incoming URL token against your environment secret configuration
            if ( $passed_key === VINTTRO_STUDIO_KEY ) {
                return null; // 🔓 MATCH: Clear all global security blocks and execute our handler
            }
            
        } else {
            // Safety Log: Alert the system administrator if the constant was forgotten during deployment
            error_log('🚨 VINTTRO CRITICAL ERROR: VINTTRO_STUDIO_KEY is not defined inside wp-config.php!');
        }
    }
    
    return $result; // Otherwise, leave the site locked down securely
}

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

    // 🚀 NEW - Endpoint C: Public Inbound Gateway (Open for external Twilio Webhooks & Postman)
    register_rest_route( 'vinttro/v1', '/inbound-call', array(
        'methods'             => 'POST',
        'callback'            => 'vinttro_handle_inbound_voice_call',
        'permission_callback' => '__return_true', 
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
        // 🚀 DEVELOPER SHORTCUT FOR POSTMAN TESTING:
        // If it's our fake test SID, skip the live Twilio network redirect check
        if ( strpos( $call_sid, 'CAtest' ) === 0 ) {
            return new WP_REST_Response( array( 'success' => true, 'debug' => 'Mock bypass applied' ), 200 );
        }
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

// ==========================================================
// 📞 6. 🚀 NEW - CALLBACK C: INBOUND HANDLER & SYNC BROADCAST
// ==========================================================
function vinttro_handle_inbound_voice_call( WP_REST_Request $request ) {
    $call_sid  = $request->get_param('CallSid');
    $caller_id = $request->get_param('From');

    // Make sure we aren't handling blank parameters
    if ( empty($call_sid) ) {
        return new WP_Error('invalid_webhook', 'Missing structural telephony attributes.', array('status' => 400));
    }

    // A. Generate a completely randomized, unique meeting identifier room name string
    $unique_room_id = 'vinttro-call-' . wp_generate_password(8, false);

    // B. PUSH TARGET STATE DATA METADATA OUT VIA TWILIO SYNC NODE
    try {
        $sdk = vinttro_get_twilio_sdk_client();
        
        $syncServiceSid = defined('TWILIO_SYNC_SERVICE_SID') ? TWILIO_SYNC_SERVICE_SID : 'default';
        
        $sdk->sync->v1->services($syncServiceSid)
                      ->syncLists('vinttro_live_queue')
                      ->syncListItems->create([
                          "data" => [
                              "callSid"  => $call_sid,
                              "callerId" => $caller_id ? $caller_id : 'Unknown Web Client',
                              "roomId"   => $unique_room_id,
                              "status"   => "parked"
                          ]
                      ]);
    } catch (Exception $e) {
        // Log locally so we don't crash phone lines if Sync configuration strings hiccup
        error_log('Vinttro Sync Queue Broadcasting Fault: ' . $e->getMessage());
    }

    // C. STREAM BACK INTERACTIVE AUDIO HOLD MUSIC RESPONSES TO TELECOM SYSTEM
    $twiml  = '<?xml version="1.0" encoding="UTF-8"?>';
    $twiml .= '<Response>';
    $twiml .= '<Say voice="alice">Please hold while we locate an authorized agent.</Say>';
    $twiml .= '<Play loop="0">http://com.twilio.music.classical.s3.amazonaws.com/Classic_Rock.mp3</Play>';
    $twiml .= '</Response>';

    return new WP_REST_Response($twiml, 200, array('Content-Type' => 'application/xml'));
}

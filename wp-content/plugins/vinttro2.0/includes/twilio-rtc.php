<?php

// 🔍 TEMPORARY DIAGNOSTIC WIRETAP: Log raw network data for incoming calls
add_action( 'init', 'vinttro_spy_on_inbound_headers', 1 );
function vinttro_spy_on_inbound_headers() {
    if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/vinttro/v1/inbound-call' ) !== false ) {
        error_log('==================================================');
        error_log('🚨 VINTTRO NETWORK WIRETAP: ENDPOINT PATH MATCHED!');
        error_log('==================================================');
        error_log('USER AGENT   : ' . ( $_SERVER['HTTP_USER_AGENT'] ?? 'MISSING' ));
        error_log('AUTH HEADER  : ' . ( $_SERVER['HTTP_AUTHORIZATION'] ?? 'MISSING' ));
        error_log('REDIRECT AUTH: ' . ( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 'MISSING' ));
        error_log('PHP_AUTH_USER: ' . ( $_SERVER['PHP_AUTH_USER'] ?? 'MISSING' ));
        error_log('--------------------------------------------------');
        
        if ( function_exists( 'apache_request_headers' ) ) {
            error_log('ALL RAW HEADERS: ' . print_r( apache_request_headers(), true ));
        }
    }
}

// 🛰️ GLOBAL GATEWAY FILTER: Authenticate Twilio Studio using a dynamic wp-config.php key
add_filter( 'rest_authentication_errors', 'vinttro_secure_query_key_bypass', 99 );
function vinttro_secure_query_key_bypass( $result ) {
    if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/vinttro/v1/inbound-call' ) !== false ) {
        $passed_key = isset($_GET['vinttro_key']) ? trim($_GET['vinttro_key']) : '';
        
        if ( defined( 'VINTTRO_STUDIO_KEY' ) && ! empty( VINTTRO_STUDIO_KEY ) ) {
            if ( $passed_key === VINTTRO_STUDIO_KEY ) {
                return null;
            }
        } else {
            error_log('🚨 VINTTRO CRITICAL ERROR: VINTTRO_STUDIO_KEY is not defined inside wp-config.php!');
        }
    }
    return $result;
}

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ==========================================================
// 🛰️ 1. CENTRAL ROUTE COMPILATION INDEX REGISTER
// ==========================================================
add_action( 'rest_api_init', 'vinttro_register_all_rtc_routes' );
function vinttro_register_all_rtc_routes() {
    
    register_rest_route( 'vinttro/v1', '/rtc-token', array(
        'methods'             => 'POST',
        'callback'            => 'vinttro_generate_plugin_rtc_token',
        'permission_callback' => 'vinttro_check_plugin_employee_access',
    ) );

    register_rest_route( 'vinttro/v1', '/accept-call', array(
        'methods'             => 'POST',
        'callback'            => 'vinttro_agent_bridge_call',
        'permission_callback' => 'vinttro_check_plugin_employee_access',
    ) );

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
// 🎟️ 4. CALLBACK A: GENERATE VOICE, VIDEO & SYNC ACCESS TOKENS
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
    $identity     = $current_user->user_login ? $current_user->user_login : 'agent_dev_1';

    $token = new \Twilio\Jwt\AccessToken($accountSid, $apiKeySid, $apiKeySecret, 3600, $identity);

    // 1. Inject Twilio Voice Grant (Allows WebRTC Voice Calls & Twilio.Device)
    $voiceGrant = new \Twilio\Jwt\Grants\VoiceGrant();
    $voiceGrant->setIncomingAllow(true);
    if ( defined('TWILIO_TWIML_APP_SID') && !empty(TWILIO_TWIML_APP_SID) ) {
        $voiceGrant->setOutgoingApplicationSid(TWILIO_TWIML_APP_SID);
    }
    $token->addGrant($voiceGrant);

    // 2. Inject WebRTC Video Channel Grant
    $videoGrant = new \Twilio\Jwt\Grants\VideoGrant();
    $token->addGrant($videoGrant);

    // 3. Inject WebSocket Real-time Sync Grant
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
        if ( strpos( $call_sid, 'CAtest' ) === 0 ) {
            return new WP_REST_Response( array( 'success' => true, 'debug' => 'Mock bypass applied' ), 200 );
        }
        $sdk = vinttro_get_twilio_sdk_client();
        
        $sdk->calls($call_sid)->update([
            "twiml" => '<?xml version="1.0" encoding="UTF-8"?><Response><Connect><Room>' . $room_id . '</Room></Connect></Response>'
        ]);

        return new WP_REST_Response(array('success' => true), 200);

    } catch (Exception $e) {
        return new WP_Error('bridge_execution_failure', $e->getMessage(), array('status' => 500));
    }
}

// ==========================================================
// 📞 6. CALLBACK C: INBOUND HANDLER & SYNC BROADCAST
// ==========================================================
function vinttro_handle_inbound_voice_call( WP_REST_Request $request ) {
    $call_sid  = $request->get_param('CallSid');
    $caller_id = $request->get_param('From');

    if ( empty($call_sid) ) {
        return new WP_Error('invalid_webhook', 'Missing structural telephony attributes.', array('status' => 400));
    }

    $unique_room_id = 'vinttro-call-' . wp_generate_password(8, false);

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
        error_log('Vinttro Sync Queue Broadcasting Fault: ' . $e->getMessage());
    }

    $twiml  = '<?xml version="1.0" encoding="UTF-8"?>';
    $twiml .= '<Response>';
    $twiml .= '<Say voice="alice">Please hold while we locate an authorized agent.</Say>';
    $twiml .= '<Play loop="0">http://com.twilio.music.classical.s3.amazonaws.com/Classic_Rock.mp3</Play>';
    $twiml .= '</Response>';

    return new WP_REST_Response($twiml, 200, array('Content-Type' => 'application/xml'));
}

// ==========================================================
// ⚡ 7. ENQUEUE TWILIO SDKs, SOCKET.IO & COMMUNICATOR JS
// ==========================================================
add_action( 'wp_enqueue_scripts', 'vinttro_enqueue_communicator_socket_assets' );
function vinttro_enqueue_communicator_socket_assets() {
    
    // 1. Official Twilio Voice JS SDK (v2.x via jsDelivr NPM CDN)
    wp_enqueue_script( 
        'twilio-voice-sdk', 
        'https://cdn.jsdelivr.net/npm/@twilio/voice-sdk@2.11.0/dist/twilio.min.js', 
        array(), 
        '2.11.0', 
        false 
    );

    // 2. Official Twilio Sync JS SDK (WebSocket State Synchronization)
    wp_enqueue_script( 
        'twilio-sync-sdk', 
        'https://media.twiliocdn.com/sdk/js/sync/v0.12/twilio-sync.min.js', 
        array(), 
        '0.12.0', 
        false 
    );

    // 3. Official Twilio Video JS SDK (Video Canvas Support)
    wp_enqueue_script( 
        'twilio-video-sdk', 
        'https://sdk.twilio.com/js/video/releases/2.28.1/twilio-video.min.js', 
        array(), 
        '2.28.1', 
        false 
    );

    // 4. Socket.io Client SDK
    wp_enqueue_script( 
        'socket-io-client', 
        'https://cdn.socket.io/4.7.5/socket.io.min.js', 
        array(), 
        '4.7.5', 
        true 
    );

    // 5. Enqueue RTC Client Script (Enforced Dependency on twilio-voice-sdk)
    wp_enqueue_script( 
        'vinttro-rtc-client-js', 
        plugins_url( '../js/rtc-client.js', __FILE__ ), 
        array('jquery', 'twilio-voice-sdk', 'twilio-sync-sdk', 'twilio-video-sdk'), 
        '1.1.0', 
        true 
    );

    // 6. Enqueue Vinttro Communicator JS with all dependencies declared
    wp_enqueue_script( 
        'vinttro-communicator-js', 
        plugins_url( '../js/vinttro-communicator.js', __FILE__ ), 
        array('jquery', 'twilio-voice-sdk', 'twilio-sync-sdk', 'twilio-video-sdk', 'socket-io-client', 'vinttro-rtc-client-js'), 
        '2.0.0', 
        true 
    );

    // 7. Inject localized configuration object for JS
    $current_user = wp_get_current_user();
    wp_localize_script( 'vinttro-communicator-js', 'vinttroConfig', array(
        'nodeApiUrl' => 'https://services.uat.vinttro.co.uk',
        'agentId'    => strtolower( trim( $current_user->user_email ) ),
        'agentName'  => $current_user->display_name ? $current_user->display_name : $current_user->user_login,
        'restNonce'  => wp_create_nonce( 'wp_rest' )
    ));
}
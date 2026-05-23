<?php
/**
 * Vinttro Plugin Module: WebRTC Client & Token Infrastructure
 */

// 1. Add the Sync class reference at the absolute top of the file
use Twilio\Jwt\Grants\VideoGrant;
use Twilio\Jwt\Grants\SyncGrant; // 🚀 NEW: Include the Sync grant constructor

function vinttro_generate_plugin_rtc_token( WP_REST_Request $request ) {
    // ... Keep your autoload paths and credential retrieval lines at the top exactly as they are ...

    $current_user = wp_get_current_user();
    $identity     = $current_user->user_login;

    // Initialize the root AccessToken container
    // (Ensure you are using the absolute root namespace indicator '\' if it complains)
    $token = new \Twilio\Jwt\AccessToken($accountSid, $apiKeySid, $apiKeySecret, 3600, $identity);

    // 1. Video Connection Grant (Using absolute inline namespace)
    $videoGrant = new \Twilio\Jwt\Grants\VideoGrant();
    $token->addGrant($videoGrant);

    // 2. 🛰️ Real-Time Sync WebSocket Grant (Using absolute inline namespace)
    // This safely verifies the wp-config constant exists before attempting to stamp the token
    if ( defined('TWILIO_SYNC_SERVICE_SID') && !empty(TWILIO_SYNC_SERVICE_SID) ) {
        $syncGrant = new \Twilio\Jwt\Grants\SyncGrant();
        $syncGrant->setServiceSid(TWILIO_SYNC_SERVICE_SID);
        $token->addGrant($syncGrant);
    } else {
        return new WP_Error(
            'missing_sync_sid', 
            'Backend Error: TWILIO_SYNC_SERVICE_SID is not defined in your wp-config.php file!', 
            array( 'status' => 500 )
        );
    }

    // Return clean authorized payload string
    return new WP_REST_Response(array(
        'token'    => $token->toJWT(),
        'identity' => $identity
    ), 200);
}

// 2. CONDITIONALLY ENQUEUE CLIENT SCRIPTS
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

// 3. REGISTER THE SECURE WP-REST ENDPOINT
add_action('rest_api_init', 'vinttro_register_plugin_rtc_route');
function vinttro_register_plugin_rtc_route() {
    register_rest_route('vinttro/v1', '/rtc-token', array(
        'methods'             => 'POST',
        'callback'            => 'vinttro_generate_plugin_rtc_token',
        'permission_callback' => 'vinttro_check_plugin_employee_access',
    ));
}

// 4. SECURITY FIREWALL: Employee Check
function vinttro_check_plugin_employee_access() {
    if ( ! is_user_logged_in() ) {
        return false;
    }
    $current_user = wp_get_current_user();
    return str_ends_with( strtolower($current_user->user_email), '@vinttro.co.uk' );
}


// 5. Inbound Call Handling (Optional - Requires Twilio Webhooks)
/**
 * REST API Entry Point: Handle Incoming PSTN Calls
 * Endpoint: POST /wp-json/vinttro/v1/inbound-call
 */
function vinttro_handle_inbound_voice_call( WP_REST_Request $request ) {
    $call_sid = $request->get_param('CallSid'); // Unique Asterisk-style channel ID
    $caller_id = $request->get_param('From');   // Customer's phone number

    // 1. Generate a completely unique, randomized meeting ID string
    $unique_room_id = 'vinttro-call-' . wp_generate_password(8, false);

    // 2. PUSH TO TWILIO SYNC LIST (Acts as your real-time cloud Redis store)
    // This instantly triggers the WebSocket event layer down to all open agent browsers
    try {
        $sdk = vinttro_get_twilio_sdk_client(); // Instantiates your Composer Twilio Client
        $sdk->sync->v1->services(TWILIO_SYNC_SERVICE_SID)
                      ->syncLists('vinttro_live_queue')
                      ->syncListItems->create([
                          "data" => [
                              "callSid" => $call_sid,
                              "callerId" => $caller_id,
                              "roomId"   => $unique_room_id,
                              "status"   => "parked"
                          ]
                      ]);
    } catch (Exception $e) {
        error_log('Sync Push Failure: ' . $e->getMessage());
    }

    // 3. RESPOND WITH TWIML HOLD MUSIC ORBIT
    // This tells Twilio to park the caller and loop audio indefinitely until interrupted
    $twiml = '<?xml version="1.0" encoding="UTF-8"?>';
    $twiml .= '<Response>';
    $twiml .= '<Say voice="alice">Please hold while we locate an authorized agent.</Say>';
    $twiml .= '<Play loop="0">http://com.twilio.music.classical.s3.amazonaws.com/Classic_Rock.mp3</Play>';
    $twiml .= '</Response>';

    return new WP_REST_Response($twiml, 200, ['Content-Type' => 'application/xml']);
}
/**
 * REST API Endpoint: Agent Connects Media Handshake
 * Endpoint: POST /wp-json/vinttro/v1/accept-call
 */
function vinttro_agent_bridge_call( WP_REST_Request $request ) {
    $call_sid = $request->get_param('callSid');
    $room_id  = $request->get_param('roomId');

    try {
        $sdk = vinttro_get_twilio_sdk_client();

        // 🚀 THE INTERCEPT: Live-redirect the active telephone channel string!
        // This tears the caller out of the hold music and drops them into the WebRTC Room
        $sdk->calls($call_sid)->update([
            "twiml" => '<?xml version="1.0" encoding="UTF-8"?><Response><Connect><Room>' . $room_id . '</Room></Connect></Response>'
        ]);

        return array('success' => true);

    } catch (Exception $e) {
        return new WP_Error('bridge_error', $e->getMessage(), array('status' => 500));
    }
}
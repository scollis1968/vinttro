<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function visp_is_cti_allowed() {
    if ( is_admin() || ! is_user_logged_in() ) {
        return false;
    }

    if ( function_exists( 'vinttro_check_plugin_employee_access' ) ) {
        if ( ! vinttro_check_plugin_employee_access() ) {
            return false;
        }
    } else {
        $user = wp_get_current_user();
        $email = strtolower( trim( $user->user_email ) );
        if ( ! str_ends_with( $email, '@vinttro.co.uk' ) && ! str_ends_with( $email, '@vinttro.com' ) ) {
            return false;
        }
    }

    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $allowed_paths = array( '/visp', '/client-communicator' );

    foreach ( $allowed_paths as $path ) {
        if ( strpos( $request_uri, $path ) !== false ) {
            return true;
        }
    }

    return false;
}

function visp_render_cti_header() {
    if ( ! visp_is_cti_allowed() ) {
        return;
    }

    $current_user = wp_get_current_user();
    $avatar_url   = get_avatar_url( $current_user->ID, array( 'size' => 28 ) );
    $display_name = $current_user->display_name ?: $current_user->user_login;
    ?>
    <div id="vinttro-header-cti-container" class="visp-cti-header-bar">
        <!-- 1. Left: Brand Logo -->
        <div class="vinttro-hdr-brand">
            <img style="max-width: 40px;" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/visp_logo.png' ); ?>" alt="VISP" class="visp-hdr-logo" />
        </div>

        <!-- 2. Center: Floating Call Controls & WebRTC Health -->
        <div class="vinttro-header-call-bar state-idle" id="vinttro-header-call-bar">
            <!-- Idle State -->
            <div class="vinttro-call-segment segment-idle">
                <!-- Health status pill (Clicking also launches phone) -->
                <div class="vinttro-webrtc-health" id="vinttro-webrtc-health-btn" title="Click to launch or focus softphone" style="cursor: pointer;">
                    <span class="vinttro-pulse-dot health-offline" id="vinttro-health-dot"></span>
                    <span class="vinttro-health-text" id="vinttro-health-text">Phone Off</span>
                    <button class="vinttro-icon-btn" id="vinttro-open-devices" title="Audio Device Settings">⚙️</button>
                </div>
                
                <!-- Explicit Launch Softphone Button -->
                <button type="button" class="vinttro-hdr-btn btn-launch-phone" id="vinttro-btn-launch-phone" title="Launch softphone audio window" style="display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:600; padding:4px 10px; background:#2563eb; color:#ffffff; border:none; border-radius:4px; cursor:pointer;">
                    📞 Softphone
                </button>

                <span class="vinttro-idle-text" id="vinttro-idle-text">Ready for calls</span>
            </div>

            <!-- Ringing State -->
            <div class="vinttro-call-segment segment-ringing" style="display:none;">
                <span class="vinttro-call-pulse"></span>
                <span class="vinttro-caller-info" id="vinttro-hdr-caller-id">Unknown Caller</span>
                <button class="vinttro-hdr-btn btn-accept" id="vinttro-hdr-accept">📞 Answer</button>
                <button class="vinttro-hdr-btn btn-reject" id="vinttro-hdr-reject">❌ Dismiss</button>
            </div>

            <!-- Active Call State -->
            <div class="vinttro-call-segment segment-active" style="display:none;">
                <span class="vinttro-timer" id="vinttro-hdr-timer">00:00</span>
                <span class="vinttro-active-party" id="vinttro-hdr-active-party">In Call</span>
                <button class="vinttro-hdr-btn btn-control" id="vinttro-hdr-mute" title="Mute Microphone">🎙️ Mute</button>
                <button class="vinttro-hdr-btn btn-control" id="vinttro-hdr-hold" title="Place on Hold">⏸️ Hold</button>
                <button class="vinttro-hdr-btn btn-control" id="vinttro-hdr-transfer" title="Transfer Call">🔀 Transfer</button>
                <button class="vinttro-hdr-btn btn-end" id="vinttro-hdr-end" title="Hang Up">🔴 End</button>
            </div>
        </div>

        <!-- 3. Right: Presence & Account -->
        <div class="vinttro-hdr-right-actions">
            <div class="vinttro-presence-pill" id="vinttro-presence-toggle" title="Click to change state or view profile options">
                <div class="vinttro-avatar-wrap">
                    <img src="<?php echo esc_url( $avatar_url ); ?>" class="vinttro-agent-avatar" alt="Avatar" />
                    <span class="vinttro-status-badge status-available" id="vinttro-presence-badge"></span>
                </div>
                <span class="vinttro-agent-name"><?php echo esc_html( $display_name ); ?></span>
                <span class="vinttro-dropdown-arrow">▼</span>

                <div class="vinttro-presence-dropdown" id="vinttro-presence-menu">
                    <div class="vinttro-menu-section-label">PRESENCE</div>
                    <div class="vinttro-presence-option" data-status="available">
                        <span class="dot dot-available"></span> Available
                    </div>
                    <div class="vinttro-presence-option" data-status="away">
                        <span class="dot dot-away"></span> Away / Break
                    </div>
                    <div class="vinttro-presence-option" data-status="dnd">
                        <span class="dot dot-dnd"></span> Do Not Disturb
                    </div>

                    <div class="vinttro-dropdown-divider"></div>

                    <div class="vinttro-menu-section-label">ACCOUNT</div>
                    <a href="/visp/dashboard/" class="vinttro-dropdown-item">👤 Dashboard / Profile</a>
                    <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="vinttro-dropdown-item logout-link">🚪 Log Out</a>
                </div>
            </div>

            <button class="vinttro-burger-trigger mobmenu-trigger-action" data-panel-target="mobmenu-right-panel" aria-label="Toggle Navigation Menu">
                <svg class="vinttro-burger-icon" viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
        </div>
    </div>
    <?php
}

add_shortcode( 'visp_cti_header', function() {
    ob_start();
    visp_render_cti_header();
    return ob_get_clean();
} );

// Enqueue CTI Styles & Scripts
add_action( 'wp_enqueue_scripts', function() {
    if ( ! visp_is_cti_allowed() ) {
        return;
    }

    // 1. CTI Bar Styles

    add_action( 'wp_enqueue_scripts', function() {
        wp_enqueue_style(
            'visp-header-cti',
            plugins_url( 'css/visp-header-cti.css', __FILE__ ),
            array( 'wp-block-library' ), // Ensures it waits for block library styles
            '1.0.0'
        );
    }, 999 ); // Priority 999 ensures it loads after the theme and core block styles

    // 2. Socket.io Client
    wp_enqueue_script(
        'socket-io-cdn',
        'https://cdn.socket.io/4.7.5/socket.io.min.js',
        array(),
        '4.7.5',
        true
    );

    wp_add_inline_script(
        'socket-io-cdn',
        'window.vinttroSocketIO = (typeof io === "function") ? io : null;',
        'after'
    );

    // 3. Communicator Controller Script (No twilio dependency needed in main tab!)
    wp_enqueue_script(
        'vinttro-communicator',
        plugins_url( '../js/vinttro-communicator.js', __FILE__ ),
        array( 'jquery', 'socket-io-cdn' ),
        time(), // Dev cache-busting
        true
    );

    // 4. Shared CTI & Node API Configuration
    $current_user = wp_get_current_user();
    $agent_email  = ! empty( $current_user->user_email ) ? sanitize_email( $current_user->user_email ) : 'agent_dev_1';

    $default_node = ( defined('WP_ENVIRONMENT_TYPE') && wp_get_environment_type() === 'development' )
        ? 'http://localhost:3005'
        : 'https://services.uat.vinttro.co.uk';

    $node_url = defined('VINTTRO_NODE_URL') ? VINTTRO_NODE_URL : $default_node;

    wp_localize_script( 'vinttro-communicator', 'vinttroConfig', array(
        'nodeApiUrl'    => untrailingslashit( esc_url_raw( $node_url ) ),
        'apiBase'       => untrailingslashit( esc_url_raw( $node_url ) ) . '/api',
        'agentId'       => strtolower( trim( $agent_email ) ),
        'agentPhoneUrl' => home_url( '/visp/agent-phone' ),
        'nonce'         => wp_create_nonce( 'wp_rest' ),
    ) );
} );
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
            <img style="max-width: 100px;" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/images/vinttro_visp_black.png' ); ?>" alt="Vinttro VISP" class="visp-hdr-logo" />
        </div>

        <!-- 2. Center: Floating Call Controls & WebRTC Health -->
        <div class="vinttro-header-call-bar state-idle" id="vinttro-header-call-bar">
            <!-- Idle State (WebRTC Health Status) -->
            <div class="vinttro-call-segment segment-idle">
                <div class="vinttro-webrtc-health" id="vinttro-webrtc-health-btn">
                    <span class="vinttro-pulse-dot health-connecting" id="vinttro-health-dot"></span>
                    <span class="vinttro-health-text" id="vinttro-health-text">RTC Off</span>
                    <button class="vinttro-icon-btn" id="vinttro-open-devices" title="Audio Device Settings">⚙️</button>
                </div>
                <span class="vinttro-idle-text">Ready for calls</span>
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

        <!-- 3. Right: Combined Presence & Mobile Menu Trigger -->
        <div class="vinttro-hdr-right-actions">
            <!-- Agent Presence Pill + Account Menu -->
            <div class="vinttro-presence-pill" id="vinttro-presence-toggle" title="Click to change state or view profile options">
                <div class="vinttro-avatar-wrap">
                    <img src="<?php echo esc_url( $avatar_url ); ?>" class="vinttro-agent-avatar" alt="Avatar" />
                    <span class="vinttro-status-badge status-available" id="vinttro-presence-badge"></span>
                </div>
                <span class="vinttro-agent-name"><?php echo esc_html( $display_name ); ?></span>
                <span class="vinttro-dropdown-arrow">▼</span>

                <!-- Presence & Account Dropdown Menu -->
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

            <!-- Hamburger Button (Triggers WP Mobile Menu Slideout Panel) -->
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

add_action( 'wp_enqueue_scripts', function() {
    if ( visp_is_cti_allowed() ) {
        wp_enqueue_style(
            'visp-header-cti-css',
            plugins_url( '../css/visp-header-cti.css', __FILE__ ),
            array(),
            '2.0.0'
        );
    }
} );

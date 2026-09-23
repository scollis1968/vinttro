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
require_once 'includes/visp-cover-functions.php';
require_once 'includes/twilio-rtc.php';
require_once 'includes/call-controller-functions.php';


function my_plugin_load_styles() {
    // 1. Enqueue your custom CSS
    wp_enqueue_style( 'my-custom-form-styles', plugins_url( 'custom-styles.css', __FILE__ ), array(), '1.0.2' );
    
    // 2. Force a tiny bit of "Late" CSS directly into the header to kill the background scroll
    $custom_css = "
        body:has(#sgpb-popup-dialog-main-div-wrapper) { overflow: hidden !important; }
        .sgpb-popup-dialog-main-div-theme-wrapper-3 { left: 0 !important; right: 0 !important; display: flex !important; justify-content: center !important; }
    ";
    wp_add_inline_style( 'my-custom-form-styles', $custom_css );
}
add_action( 'wp_enqueue_scripts', 'my_plugin_load_styles', 99 );

//--------------------------------------------------------
function my_plugin_load_scripts() {
    // 1. Enqueue your existing global form preview script
    wp_enqueue_script( 'my-form-preview-script', plugins_url( 'custom-scripts.js', __FILE__ ), array('jquery'), '1.6', true );
    
    $localized_settings = array(
        'root'             => esc_url_raw( rest_url() ),
        'nonce'            => wp_create_nonce( 'wp_rest' ),
        'currentUserEmail' => wp_get_current_user()->user_email
    );

    // ==========================================================
    // 📞 2. TARGET ANY PAGE UNDER /visp
    // ==========================================================
    if ( stripos( $_SERVER['REQUEST_URI'], '/visp' ) !== false ) {
        
        // A. Video media asset
        wp_enqueue_script(
            'twilio-video-cdn-pinned', 
            'https://sdk.twilio.com/js/video/releases/2.35.0/twilio-video.min.js', 
            array(), '2.35.0', true
        );

        // B. Enqueue dedicated pipeline/lead rendering engine
        wp_enqueue_script(
            'vinttro-leads-manager', 
            plugins_url( 'js/vinttro-leads.js', __FILE__ ), 
            array('jquery'), 
            '1.0.0', 
            true
        );
        wp_localize_script( 'vinttro-leads-manager', 'vinttroSettings', $localized_settings );
    }
}
add_action( 'wp_enqueue_scripts', 'my_plugin_load_scripts' );
//--------------------------------------------------------


add_filter( 'wpcf7_special_mail_tags', 'my_custom_email_tags', 10, 2 );

function my_custom_email_tags( $output, $name ) {
    switch ( $name ) {
        case '_ACCOUNTS_EMAIL':
            if ( defined('ACCOUNTS_EMAIL_ADDRESS') ) {
                $output = ACCOUNTS_EMAIL_ADDRESS;
            } else {
                error_log('Constant ' . $name . '_ADDRESS is NOT defined.');
            }
            break;
        case '_INSURANCE_EMAIL':
            if ( defined('INSURANCE_EMAIL_ADDRESS') ) {
                $output = INSURANCE_EMAIL_ADDRESS;
            } else {
                error_log('Constant ' . $name . '_ADDRESS is NOT defined.');
            }
            break;
    }

    return $output;
}


function redirect_cf7_subscribers_only() {
    if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
        return;
    }

    $subscribers_form_page_id = 75;

    if ( is_page( $subscribers_form_page_id ) && ! is_user_logged_in() ) {
        $redirect_url = home_url( '/register/' );
        wp_redirect( $redirect_url );
        exit;
    }
}
add_action( 'wp', 'redirect_cf7_subscribers_only' );

// End of custom code.
// -----------------------------------------------------------------------------------------------------------------------------------------------

function suitecrm_conditional_menu_stub( $items, $args ) {
    if ( $args->theme_location == 'primary' ) {
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $is_subscribed_to_premium = ( $user_id % 2 == 0 );

            if ( $is_subscribed_to_premium ) {
                $custom_item_html = '<li class="menu-item menu-item-suitecrm-special">';
                $custom_item_html .= '<a href="/premium-dashboard/">🔥 Premium Dashboard</a>';
                $custom_item_html .= '</li>';

                $items .= $custom_item_html;
            }
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
            if ( strpos( $key, '_' ) !== 0 ) {
                echo '<tr><th>' . esc_html( $key ) . '</th><td>' . esc_html( $value[0] ) . '</td></tr>';
            }
        }
        ?>
    </table>
    <?php
}

/**
 * THE NUCLEAR OPTION: Global HTML Output Buffer Filter
 */
add_action('init', 'vinttro_force_global_ssl_buffer');
function vinttro_force_global_ssl_buffer() {
    if (!is_admin() && !defined('DOING_AJAX') && !defined('DOING_CRON')) {
        ob_start('vinttro_global_http_rewrite_callback');
    }
}

add_action('admin_init', 'vinttro_force_admin_ssl_buffer');
function vinttro_force_admin_ssl_buffer() {
    ob_start('vinttro_global_http_rewrite_callback');
}

function vinttro_global_http_rewrite_callback($output) {
    if (empty($output)) return $output;
    return str_replace('http://uat.vinttro.co.uk', 'https://uat.vinttro.co.uk', $output);
}
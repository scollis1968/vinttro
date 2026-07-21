<?php
/**
 * VINTTRO Custom Theme Functions
 */

// 1. LINK TO PARENT THEME (Twenty Twenty-Five)
add_action( 'wp_enqueue_scripts', 'vinttro_enqueue_styles' );
function vinttro_enqueue_styles() {
    // This pulls in the CSS from the Twenty Twenty-Five folder
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
}

// 2. SUITECRM V8 API INTEGRATION
// Step 1: Store user ID after registration
add_action('user_register', 'suitecrm_store_user_id_after_registration', 10, 1);

function suitecrm_store_user_id_after_registration($user_id) {
    set_transient("suitecrm_user_$user_id", $user_id, 60); 
}


// 3. COMPLIANZ ACCESSIBILITY FIX
add_filter('cmplz_banner_html', function($html) {
    $search = '/(<summary.*?>)(.*?)(<input.*?class="cmplz-consent-checkbox".*?>)(.*?)(<\/summary>)/s';
    $replace = '$1$2$5$3$4'; 
    return preg_replace($search, $replace, $html);
}, 10, 1);


// 4. REST API AUTHENTICATION FIX   
add_filter('rest_authentication_errors', function($result) {
    // Whitelist your custom API namespace
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($request_uri, '/wp-json/vinttro/v1/') !== false) {
        return true; // Allow access to your custom endpoints
    }
    return $result;
}, 99);
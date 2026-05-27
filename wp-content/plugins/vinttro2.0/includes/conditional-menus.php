<?php

// ... other functions (like the meta tag function) ...

// --- Configuration ---
// Defined globally for clean, unified access across functions.
define( 'B2B_PARENT_ID', 1825 );   // Your actual B2B parent ID
define( 'VISP_PARENT_ID', 6089 ); // *** REPLACE 9999 with your actual VISP parent ID ***

/**
 * Function to conditionally swap the primary menu to B2B or VISP menus.
 * @param array $args The arguments for the menu.
 * @return array The filtered arguments.
 */
function custom_swap_mobile_menu_on_b2b( $args ) {

    // --- Menu Names Configuration ---
    $default_menu_name = 'main_menu'; 
    $b2b_menu_name     = 'b2b';
    $visp_menu_name    = 'visp'; // The registration name of your new VISP menu
    
    // Check if the current menu being processed is our default menu.
    if ( isset( $args['menu'] ) && $args['menu'] === $default_menu_name ) {
        
        $ancestors = get_post_ancestors( get_the_ID() );
        
        // Check B2B status
        $is_b2b_page = is_page( B2B_PARENT_ID ) || in_array( B2B_PARENT_ID, $ancestors );
        
        // Check VISP status
        $is_visp_page = is_page( VISP_PARENT_ID ) || in_array( VISP_PARENT_ID, $ancestors );

        // Cascade through conditions
        if ( $is_b2b_page ) {
            // Swap to B2B
            $args['menu'] = $b2b_menu_name;
        } elseif ( $is_visp_page ) {
            // Swap to VISP
            $args['menu'] = $visp_menu_name;
        }
    }

    return $args;
}
// Hook the function into the menu arguments filter.
add_filter( 'wp_nav_menu_args', 'custom_swap_mobile_menu_on_b2b' );


/**
 * Passes the current menu type to JavaScript.
 */
function custom_set_mobile_menu_state() {
    
    // Check if we are on an admin page or not on the front-end.
    if ( is_admin() ) {
        return;
    }
    
    $ancestors = get_post_ancestors( get_the_ID() );
    
    // Determine the layout states
    $is_b2b_page  = is_page( B2B_PARENT_ID ) || in_array( B2B_PARENT_ID, $ancestors );
    $is_visp_page = is_page( VISP_PARENT_ID ) || in_array( VISP_PARENT_ID, $ancestors );

    // Prepare data for JavaScript
    $menu_data = array(
        'isB2B'       => $is_b2b_page,
        'isVISP'      => $is_visp_page,
        'b2cHomeUrl'  => esc_url( home_url( '/' ) ),
        'b2bHomeUrl'  => esc_url( home_url( '/b2b/' ) ),
        'vispHomeUrl' => esc_url( home_url( '/visp/' ) ),
    );

    // Localize the script data attached to jQuery
    wp_localize_script( 
        'jquery', 
        'MenuState', 
        $menu_data 
    );
}
add_action( 'wp_enqueue_scripts', 'custom_set_mobile_menu_state' );

/**
 * Injects JavaScript to add menu switch links/labels to the mobile menu.
 */
function custom_inject_mobile_menu_links() {
    ?>
    <script type="text/javascript">
    (function($) {
        if (typeof MenuState === 'undefined') { return; }

        var attempts = 0;
        var maxAttempts = 40; 
        var checkIntervalTime = 500; 

        var checkInterval = setInterval(function() {
            attempts++;
            var $targetContainer = $('.mobmenu-content'); 
            var $searchContainer = $targetContainer.find('.rightmtop');
            
            // If found OR if we hit the max attempts
            if ($searchContainer.length > 0 || attempts >= maxAttempts) {
                clearInterval(checkInterval); 

                if ($searchContainer.length > 0) {
                    
                    var b2cLink  = '<a href="' + MenuState.b2cHomeUrl + '" class="menu-switch-link menu-switch-b2c">LifeStyle Home</a>';
                    var b2bLink  = '<a href="' + MenuState.b2bHomeUrl + '" class="menu-switch-link menu-switch-b2b">B2B Home</a>';
                    var vispLink = '<a href="' + MenuState.vispHomeUrl + '" class="menu-switch-link menu-switch-visp">VISP Home</a>';
                    
                    var currentLabel;
                    var switchLink;

                    // Evaluate 3 possible states for UI rendering
                    if (MenuState.isB2B) {
                        currentLabel = '<span class="menu-label menu-label-b2b">Business to Business</span>';
                        switchLink = b2cLink;
                    } else if (MenuState.isVISP) {
                        currentLabel = '<span class="menu-label menu-label-visp">VISP</span>';
                        switchLink = b2cLink; // Defaults back to lifestyle home, change if needed
                    } else {
                        currentLabel = '<span class="menu-label menu-label-b2c">LifeStyle</span>';
                        switchLink = b2bLink;
                    }

                    // Inject Label at the TOP (Aligned with Close Button)
                    var $topPanel = $('.mobmenu-right-alignment'); 
                    var $closeButton = $topPanel.find('.mobmenu-right-bt');

                    if ($closeButton.length > 0) {
                        $closeButton.after(currentLabel); 
                    }
                    
                } else {
                    console.error('Failed to find mobile menu elements after ' + (maxAttempts * checkIntervalTime / 1000) + ' seconds.');
                }
            }
        }, checkIntervalTime); 

    })(jQuery);
    </script>
    <?php
}
add_action( 'wp_footer', 'custom_inject_mobile_menu_links' );

/**
 * Redirect users to specific dashboards after login based on email domain.
 *
 * @param string           $redirect_to The redirect destination URL.
 * @param string           $request     The requested redirect destination URL passed as a parameter.
 * @param WP_User|WP_Error $user        WP_User object if login was successful, WP_Error object otherwise.
 * @return string Filtered redirect URL.
 */
function custom_login_redirect( $redirect_to, $request, $user ) {
    
    // 1. If there's a login error or the user object isn't valid, bail early.
    if ( is_wp_error( $user ) || ! isset( $user->user_email ) ) {
        return $redirect_to;
    }

    // 2. Safely allow administrators to go to the back-end instead of being forced to a front-end dashboard.
    if ( isset( $user->roles ) && in_array( 'administrator', (array) $user->roles ) ) {
        return $redirect_to; 
    }

    // 3. Extract the user's email domain check.
    if ( strpos( $user->user_email, '@vinttro.co.uk' ) !== false ) {
        // Redirect .vinttro.co.uk email holders
        return home_url( '/visp/dashboard/' );
    } else {
        // Redirect all other standard users
        return home_url( '/dashboard/' );
    }
}
add_filter( 'login_redirect', 'custom_login_redirect', 10, 3 );
<?php

// ... other functions (like the meta tag function) ...

/**
 * Function to conditionally swap the primary menu to the B2B menu.
 * @param array $args The arguments for the menu.
 * @return array The filtered arguments.
 */
function custom_swap_mobile_menu_on_b2b( $args ) {

    // --- Configuration ---
    
    // 1. Enter the EXACT name of your current Default Menu here (e.g., 'Main Menu', 'Primary Nav').
    $default_menu_name = 'main_menu'; 
    
    // 2. Enter the EXACT name of the B2B Menu you created.
    $b2b_menu_name = 'b2b';
    
    // 3. Enter the Post ID of your main B2B parent page (e.g., the page with the slug /b2b/).
    $b2b_parent_id = 1825; // *** REPLACE with your actual B2B parent ID ***
    
    // ---------------------

    // Check if the current menu being processed is our default menu (by name).
    if ( $args['menu'] === $default_menu_name || ( isset( $args['menu'] ) && $args['menu'] === $default_menu_name ) ) {
        
        // --- B2B Conditional Logic ---
        
        // is_page() with an array of IDs checks if the current page is one of those IDs.
        // We use get_post_ancestors to check if the current page is a child of the B2B page.
        $ancestors = get_post_ancestors( get_the_ID() );
        $is_b2b_page = is_page( $b2b_parent_id ) || in_array( $b2b_parent_id, $ancestors );

        if ( $is_b2b_page ) {
            
            // Swap the menu to the B2B version
            $args['menu'] = $b2b_menu_name;
        }
    }

    return $args;
}
// Hook the function into the menu arguments filter.
add_filter( 'wp_nav_menu_args', 'custom_swap_mobile_menu_on_b2b' );



// --- Configuration ---
// Define these constants outside the function for clean access.
define( 'B2B_PARENT_ID', 1825 ); // *** REPLACE with your actual B2B parent ID ***
define( 'B2B_HOME_URL', site_url('/b2b/' );

/**
 * Passes the current menu type to JavaScript.
 */
function custom_set_mobile_menu_state() {
    
    // Check if we are on an admin page or not on the front-end.
    if ( is_admin() ) {
        return;
    }
    
    // 1. Determine the B2B state
    $ancestors = get_post_ancestors( get_the_ID() );
    $is_b2b_page = is_page( B2B_PARENT_ID ) || in_array( B2B_PARENT_ID, $ancestors );

    // 2. Prepare data for JavaScript
    $menu_data = array(
        'isB2B'         => $is_b2b_page,
        'b2cHomeUrl'    => esc_url( home_url( '/' ) ),
        'b2bHomeUrl'    => esc_url( B2B_HOME_URL ),
    );

    // 3. Localize the script data
    // We attach the MenuState object to the standard 'jquery' script handle.
    // This ensures MenuState is available as soon as jQuery loads.
    wp_localize_script( 
        'jquery',                 // Handle of the script to attach data to
        'MenuState',              // Name of the JavaScript object (this is what you console.log)
        $menu_data                // The PHP array data
    );
    
    // NOTE: You must also ensure your injection script is loaded after this. 
    // If your injection script is in the wp_footer hook (Step 2 in previous response), 
    // it will run after this data is created.
}
add_action( 'wp_enqueue_scripts', 'custom_set_mobile_menu_state' );

/**
 * Injects JavaScript to add B2B/B2C switch links to the mobile menu.
 */
function custom_inject_mobile_menu_links() {
    ?>
    <script type="text/javascript">
    (function($) {
        if (typeof MenuState === 'undefined') { return; }

        var attempts = 0;
        var maxAttempts = 40; // Increased attempts to 40 (20 seconds) just in case
        var checkIntervalTime = 500; // Check every 0.5 seconds

        var checkInterval = setInterval(function() {
            attempts++;
            var $targetContainer = $('.mobmenu-content'); 
            var $searchContainer = $targetContainer.find('.rightmtop');
            
            // Log for debugging timing (optional, can be removed later)
            console.log('Attempt ' + attempts + ': checking for menu...');

            // If found OR if we hit the max attempts
            if ($searchContainer.length > 0 || attempts >= maxAttempts) {
                clearInterval(checkInterval); // Stop trying

                if ($searchContainer.length > 0) {
                    // --- START OF FINAL INJECTION CODE ---

                    console.log('Mobile menu ready. Injecting links.');
                    
                    var b2cLink = '<a href="' + MenuState.b2cHomeUrl + '" class="menu-switch-link menu-switch-b2c">LifeStyle Home</a>'; // Renamed B2C to LifeStyle
                    var b2bLink = '<a href="' + MenuState.b2bHomeUrl + '" class="menu-switch-link menu-switch-b2b">B2B Home</a>';
                    var currentLabel;
                    var switchLink;

                    if (MenuState.isB2B) {
                        currentLabel = '<span class="menu-label menu-label-b2b">Business to Business</span>';
                        switchLink = b2cLink;
                    } else {
                        currentLabel = '<span class="menu-label menu-label-b2c">LifeStyle</span>'; // Use LifeStyle for B2C label
                        switchLink = b2bLink;
                    }

                    // 2. Inject Label at the TOP (Aligned with Close Button)
                    var $topPanel = $('.mobmenu-right-alignment'); // Target the highest parent container
                    var $closeButton = $topPanel.find('.mobmenu-right-bt'); // Target the close button anchor

                    if ($closeButton.length > 0) {
                        // Inject the label *after* the close button, within the top panel
                        $closeButton.after(currentLabel); 
                    }
                    
                    // --- END OF FINAL INJECTION CODE ---
                    
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
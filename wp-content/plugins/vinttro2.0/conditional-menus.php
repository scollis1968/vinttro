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
define( 'B2B_HOME_URL', 'https://wordpress.uat.vinttro.co.uk/b2b/' ); // *** REPLACE with your actual B2B home URL ***

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
        // Ensure the MenuState data is available
        // Check for MenuState is correct here
        if (typeof MenuState === 'undefined') {
            return;
        }
        
        // *** DEBUGGING STEP 1: Check if the function starts ***
        console.log('JS Injection function started.'); 

        var $targetContainer = $('.mobmenu-content'); 
        
        // *** DEBUGGING STEP 2: Check if the main container is found ***
        if ($targetContainer.length === 0) {
            console.error('.mobmenu-content container not found!');
            return;
        } else {
            console.log('.mobmenu-content found!'); 
        }
        // The target element: the container where the links should go
        // Based on your HTML, the best spot is just inside the mobmenu-content div,
        // right before the search form in the .rightmtop ul.
        var $targetContainer = $('.mobmenu-content'); 

        // Check if the mobile menu content exists
        if ($targetContainer.length === 0) {
            return;
        }

        // 1. Define the HTML for the links
        var b2cLink = '<a href="' + MenuState.b2cHomeUrl + '" class="menu-switch-link menu-switch-b2c">B2C Home</a>';
        var b2bLink = '<a href="' + MenuState.b2bHomeUrl + '" class="menu-switch-link menu-switch-b2b">B2B Home</a>';
        
        var $linkContainer = $('<div class="menu-switch-container"></div>');

        if (MenuState.isB2B) {
            // Currently viewing B2B menu: show B2B label (not clickable) and B2C link (clickable)
            $linkContainer.append('<span class="menu-label menu-label-b2b">B2B Area</span>');
            $linkContainer.append(b2cLink);
        } else {
            // Currently viewing B2C menu: show B2C label (not clickable) and B2B link (clickable)
            $linkContainer.append('<span class="menu-label menu-label-b2c">B2C Area</span>');
            $linkContainer.append(b2bLink);
        }
        
        // 2. Inject the links just above the search form
        // We target the immediate child of .mobmenu-content which is the .rightmtop ul.
        var $searchContainer = $targetContainer.find('.rightmtop');
        if ($searchContainer.length > 0) {
             $linkContainer.insertBefore($searchContainer);
        } else {
             // Fallback insertion point if the search container isn't found
             $targetContainer.prepend($linkContainer);
        }

    })(jQuery);
    </script>
    <?php
}
add_action( 'wp_footer', 'custom_inject_mobile_menu_links' );
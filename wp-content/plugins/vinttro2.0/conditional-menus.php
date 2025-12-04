<?php
/**
 * Function to conditionally swap the primary menu.
 * @param array $args The arguments for the menu.
 * @return array The filtered arguments.
 */
function custom_swap_mobile_menu_on_b2b( $args ) {

    // IMPORTANT: Replace 'primary' with the theme location used by your Mobile Menu plugin.
    // If your Mobile Menu plugin uses the theme's default location, 'primary' is often correct.
    $target_theme_location = 'primary'; 

    // --- Conditional Check ---
    
    // Check 1: Ensure we are targeting the correct menu location.
    // This prevents affecting other menus (like a footer menu).
    if ( $args['theme_location'] == $target_theme_location ) {

        // Check 2: Define your B2B condition.
        // Option A: Check if the current page is a child of the B2B Parent Page (ID: 123)
        // You MUST replace 123 with the actual ID of your B2B Parent Page.
        $b2b_parent_id = 1825; 

        // is_page() also works for child pages.
        if ( is_page( $b2b_parent_id ) || is_page( get_post_ancestors( get_the_ID() ) ) ) {
            
            // Check 3: If the condition is met, swap the menu.
            // Replace 'B2B Menu' with the EXACT NAME of your B2B menu in Appearance -> Menus.
            $args['menu'] = 'B2B Menu';
        }
    }

    return $args;
}

// Hook the function into the menu arguments filter.
add_filter( 'wp_nav_menu_args', 'custom_swap_mobile_menu_on_b2b' );
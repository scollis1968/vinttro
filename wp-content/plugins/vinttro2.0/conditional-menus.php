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
    $b2b_parent_id = 1829; // *** REPLACE with your actual B2B parent ID ***
    
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
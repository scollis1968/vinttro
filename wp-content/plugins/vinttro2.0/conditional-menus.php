function custom_menu_swap_by_url( $args ) {
    // 1. Check if the menu location is the one used by your main site navigation.
    // If your theme uses 'main-menu' or 'header-menu', replace 'primary' below.
    if ( 'primary' === $args['theme_location'] ) {

        // 2. Check if the current URL contains the /b2b/ slug.
        if ( false !== strpos( $_SERVER['REQUEST_URI'], '/b2b/' ) ) {
            // If B2B slug found: Use the B2B menu.
            $args['menu'] = 'b2b'; // **Use the EXACT name of your B2B menu**
        } else {
            // If B2B slug NOT found: Use the B2C menu.
            $args['menu'] = 'main_menu'; // **Use the EXACT name of your B2C menu**
        }
    }
    return $args;
}
add_filter( 'wp_nav_menu_args', 'custom_menu_swap_by_url' );
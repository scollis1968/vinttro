function custom_conditional_menu_items( $items, $args ) {
    // Target only the main menu location we are using (replace 'primary' with your slug)
    if ( 'main_menu' === $args->theme_location ) { 
        
        $my_account_link = '<li><a href="/my-account/">My Account</a></li>';
        $login_link = '<li><a href="/login/">Login</a></li>';

        if ( is_user_logged_in() ) {
            // User is logged in: Remove the Login link, Add the My Account link
            $items = str_replace( $login_link, '', $items );
            $items .= $my_account_link; 
        } else {
            // User is logged out: Remove the My Account link, Add the Login link
            $items = str_replace( $my_account_link, '', $items );
            $items .= $login_link;
        }
    }
    return $items;
}
add_filter( 'wp_nav_menu_items', 'custom_conditional_menu_items', 10, 2 );
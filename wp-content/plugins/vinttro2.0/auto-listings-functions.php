<?php
/**
 * Force the Auto Listings shortcode to respect URL parameters
 */
die('DEBUG: auto_listings Plugin File is Active');
add_filter( 'auto_listings_query_args', function( $args ) {
    // DEBUG: This will stop the site and print "HELLO FROM PHP"
    // If you see this message when you load the page, the hook is working!
    die('<h1>HELLO FROM PHP</h1>');
    
    // 1. Filter by Make (The taxonomy name is usually 'al_make')
    if ( ! empty( $_GET['make'] ) ) {
        $args['tax_query'][] = array(
            'taxonomy' => 'al_make', 
            'field'    => 'slug',
            'terms'    => sanitize_text_field( $_GET['make'] ),
        );
    }

    // 2. Filter by Fuel Type (Usually 'al_fuel_type')
    if ( ! empty( $_GET['fuel_type'] ) ) {
        $args['tax_query'][] = array(
            'taxonomy' => 'al_fuel_type',
            'field'    => 'slug',
            'terms'    => sanitize_text_field( $_GET['fuel_type'] ),
        );
    }

    // Ensure the tax_query relationship is correct if multiple filters are used
    if ( isset($args['tax_query']) && count($args['tax_query']) > 1 ) {
        $args['tax_query']['relation'] = 'AND';
    }

    return $args;
});
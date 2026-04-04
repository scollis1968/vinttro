<?php
/**
 * Force the Auto Listings shortcode to respect URL parameters
 */
add_filter( 'auto_listings_shortcode_listings_query', function( $query_args, $atts ) {
    
    // STEP 1: PROOF OF LIFE
    // Uncomment the line below, refresh the page. 
    // If it dies, we have finally caught the right hook!
    //die('<h1>SUCCESS: We hit the Shortcode Filter!</h1>');

    // STEP 2: Logic to apply the filters from the URL
    
    // Filter by Make
    if ( ! empty( $_GET['make'] ) ) {
        // We use 'make' here, but check if it should be 'al_make'
        $query_args['tax_query'][] = array(
            'taxonomy' => 'al_make', 
            'field'    => 'slug',
            'terms'    => strtolower( sanitize_text_field( $_GET['make'] ) ),
        );
    }

    // Filter by Fuel Type
    if ( ! empty( $_GET['fuel_type'] ) ) {
        $query_args['tax_query'][] = array(
            'taxonomy' => 'al_fuel_type',
            'field'    => 'slug',
            'terms'    => strtolower( sanitize_text_field( $_GET['fuel_type'] ) ),
        );
    }

    // Set the relation if more than one filter is present
    if ( isset( $query_args['tax_query'] ) && count( $query_args['tax_query'] ) > 1 ) {
        $query_args['tax_query']['relation'] = 'AND';
    }

    return $query_args;
}, 10, 2 );
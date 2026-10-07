<?php
/**
 * Force the Auto Listings shortcode to respect URL parameters
 */
add_filter( 'auto_listings_shortcode_listings_query', function( $query_args, $atts ) {
    
    // Initialize meta_query if it's not already there
    if ( ! isset( $query_args['meta_query'] ) ) {
        $query_args['meta_query'] = array();
    }

    // 1. Filter by Make (using the key we found: _al_listing_make_display)
    if ( ! empty( $_GET['make'] ) ) {
        $query_args['meta_query'][] = array(
            'key'     => '_al_listing_make_display',
            'value'   => sanitize_text_field( $_GET['make'] ),
            'compare' => '=', // Change to 'LIKE' if exact match fails
        );
    }

    // 2. Filter by Fuel Type (using the key we found: _al_listing_model_engine_fuel)
    if ( ! empty( $_GET['fuel_type'] ) ) {
        $query_args['meta_query'][] = array(
            'key'     => '_al_listing_model_engine_fuel',
            'value'   => sanitize_text_field( $_GET['fuel_type'] ),
            'compare' => '=',
        );
    }

    // If both are used, ensure they both must match
    if ( count( $query_args['meta_query'] ) > 1 ) {
        $query_args['meta_query']['relation'] = 'AND';
    }

    return $query_args;
}, 10, 2 );
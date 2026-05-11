<?php
/**
 * The template for displaying listing content within loops
 */
if ( ! defined( 'ABSPATH' ) ) exit; 

// Identify the vehicle type
$vehicle_type = auto_listings_get_listing_type(); // Returns the slug (car, motorbike, watches, etc.)

// Load a specific template part based on the type
switch ( $vehicle_type ) {
    case 'motorbike':
        // Looks for auto-listings/content-listing-bike.php in your child theme
        auto_listings_get_template( 'content-listing-bike.php' );
        break;

    case 'watches':
        // Looks for auto-listings/content-listing-watch.php in your child theme
        auto_listings_get_template( 'content-listing-watch.php' );
        break;

    case 'daily':
        auto_listings_get_template( 'content-listing-daily.php' );
        break;

    default:
        // Fallback to the standard car layout
        auto_listings_get_template( 'content-listing-car.php' );
        break;
}
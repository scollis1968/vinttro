<?php
/**
 * The template for displaying listing content within loops.
 * This version detects the type via the URL to choose the layout.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Identify the type from the URL (same logic as your search script)
$current_url = $_SERVER['REQUEST_URI'];

if ( strpos($current_url, '/bikes') !== false ) {
    $template_to_load = 'content-listing-bike.php';
} elseif ( strpos($current_url, '/watches') !== false ) {
    $template_to_load = 'content-listing-watch.php';
} elseif ( strpos($current_url, '/daily') !== false ) {
    $template_to_load = 'content-listing-daily.php';
} else {
    $template_to_load = 'content-listing-car.php';
}

// 2. Find the file path
// Note: Your error log shows you are using the 'listings' folder in your theme
$template_path = locate_template( 'listings/' . $template_to_load );

// 3. Load the design or fallback
?>
<li <?php post_class('auto-listing-item-wrap'); ?>>
    <?php
    if ( $template_path ) {
        include( $template_path );
    } else {
        // Fallback to car if specific file missing
        $fallback = locate_template( 'listings/content-listing-car.php' );
        if ( $fallback ) include( $fallback );
    }
    ?>
</li>
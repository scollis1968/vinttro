<?php
/**
 * The Template for displaying listing content in the single-listing.php template
 *
 * This template can be overridden by copying it to yourtheme/listings/content-single-listing.php.
 *
 * @package Auto Listings.
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}
?>

<?php

do_action( 'auto_listings_before_single_listing' );

if ( post_password_required() ) {
        echo get_the_password_form(); // wpcs xss: ok.
        return;
}
?>

<div id="listing-<?php the_ID(); ?>" <?php post_class( 'auto-listings-single listing' ); ?>>

    <header class="vinttro-header">
        <h1 class="title"><?php the_title(); ?></h1>
    </header>

    <div class="vinttro-main-grid" style="display: flex; gap: 40px; align-items: flex-start;">

        <div class="vinttro-content-area" style="flex: 0 0 65%; min-width: 0;">
            <div class="vinttro-gallery-box">
                <?php auto_listings_template_single_image(); ?>
            </div>
            
            <div class="vinttro-description-text" style="margin-top: 30px;">
                <h3 style="border-bottom: 1px solid #eee; padding-bottom: 10px;">The Story</h3>
                <?php the_content(); ?>
            </div>
        </div>

        <div class="vinttro-sidebar-area" style="flex: 1; background: #fafafa; padding: 25px; border-radius: 15px; border: 1px solid #eee;">
            <div class="vinttro-price-box" style="margin-bottom: 20px;">
                <?php auto_listings_template_single_price(); ?>
            </div>
            
            <div class="vinttro-specs-table">
                <?php auto_listings_template_single_data(); ?>
            </div>

            <div class="vinttro-enquiry-form" style="margin-top: 30px;">
                <h4 style="margin-bottom: 15px;">Enquire Now</h4>
                <?php // The plugin enquiry form usually hooks in here ?>
            </div>
        </div>

    </div>
</div>

<?php do_action( 'auto_listings_after_single_listing' ); ?>
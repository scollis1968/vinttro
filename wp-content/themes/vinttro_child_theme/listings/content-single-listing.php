<?php
/**
 * The Template for displaying listing content in the single-listing.php template..
 * Override for Vinttro Child Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'auto_listings_before_single_listing' );

if ( post_password_required() ) {
    echo get_the_password_form();
    return;
}
?>

<div id="listing-<?php the_ID(); ?>" <?php post_class( 'auto-listings-single listing' ); ?>>

    <header class="vinttro-header" style="margin-bottom: 30px;">
        <?php do_action( 'auto_listings_single_upper_full_width' ); ?>
    </header>

    <div class="vinttro-main-grid" style="display: flex; gap: 40px; align-items: flex-start; flex-wrap: wrap;">

        <div class="vinttro-content-area" style="flex: 1 1 600px; min-width: 0;">
            <div class="vinttro-gallery-box">
                <?php do_action( 'auto_listings_single_gallery' ); ?>
            </div>
            
            <div class="vinttro-description-text" style="margin-top: 40px;">
                <?php the_content(); // This displays only the description ?>
            </div>
        </div>

        <div class="vinttro-sidebar-area" style="flex: 1 1 300px; background: #fafafa; padding: 30px; border-radius: 20px; border: 1px solid #eee;">
            
            <?php 
            // 1. Manually show Price & At-A-Glance (skipping the form)
            if ( function_exists( 'auto_listings_template_single_price' ) ) auto_listings_template_single_price();
            if ( function_exists( 'auto_listings_template_single_at_a_glance' ) ) auto_listings_template_single_at_a_glance();
            ?>

            <div class="vinttro-cta-wrapper" style="margin: 20px 0;">
                <a href="#your-popup-id" class="vinttro-button popup-trigger" style="display: block; background: #cc0000; color: #fff; text-align: center; padding: 15px; border-radius: 10px; text-decoration: none; font-weight: bold;">
                    ENQUIRE ABOUT THIS VEHICLE
                </a>
            </div>

            <div class="vinttro-sidebar-tabs" style="margin-top: 30px;">
                <?php 
                if ( function_exists( 'auto_listings' ) ) {
                    // This reaches into the plugin core to grab the tabs specifically
                    $al_template = auto_listings()->template_listing;
                    if ( method_exists( $al_template, 'output_tabs' ) ) {
                        $al_template->output_tabs();
                    }
                }
                ?>
            </div>

        </div>
    </div>

    <div class="vinttro-footer-area" style="margin-top: 40px;">
        <?php do_action( 'auto_listings_single_lower_full_width' ); ?>
    </div>
</div>

<?php do_action( 'auto_listings_after_single_listing' ); ?>
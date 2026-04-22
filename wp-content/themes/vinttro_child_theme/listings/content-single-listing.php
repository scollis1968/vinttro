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
                <?php the_content(); ?>
            </div>
        </div>

        <div class="vinttro-sidebar-area" style="flex: 1 1 300px; background: #fafafa; padding: 30px; border-radius: 20px; border: 1px solid #eee;">
            <?php do_action( 'auto_listings_single_sidebar' ); ?>

            <div class="vinttro-sidebar-tabs" style="margin-top: 30px;">
                <?php 
                // Manually trigger the tabs here
                if ( function_exists( 'auto_listings_output_tabs' ) ) {
                    auto_listings_output_tabs();
                } elseif ( function_exists( 'auto_listings' ) ) {
                    // If the plugin uses the class method (common in newer versions)
                    auto_listings()->template_listing->output_tabs();
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
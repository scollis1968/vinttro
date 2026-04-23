


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

<div class="vinttro-main-grid" style="display: flex; gap: 40px; align-items: flex-start; flex-wrap: wrap;">

    <div class="vinttro-content-area" style="flex: 1 1 600px; min-width: 0;">
        
        <div class="vinttro-gallery-box">
            <?php do_action( 'auto_listings_single_gallery' ); ?>
        </div>
        
        <div class="vinttro-description-text" style="margin-top: 40px;">
            <?php 
            // From your debug: Priority 10 & 20
            if ( function_exists( 'auto_listings_template_single_tagline' ) ) auto_listings_template_single_tagline();
            if ( function_exists( 'auto_listings_template_single_description' ) ) auto_listings_template_single_description();
            ?>
        </div>
    </div>

    <div class="vinttro-sidebar-area" style="flex: 1 1 300px; background: #fafafa; padding: 30px; border-radius: 20px; border: 1px solid #eee;">
        <h3 class="vinttro-sidebar-title"><?php the_title(); ?></h3>
        
        <?php 
        // Manually call the price and at-a-glance info
        if ( function_exists( 'auto_listings_template_single_vehicle' ) ) auto_listings_template_single_vehicle();
        if ( function_exists( 'auto_listings_template_single_price' ) ) auto_listings_template_single_price();
        if ( function_exists( 'auto_listings_template_single_at_a_glance' ) ) auto_listings_template_single_at_a_glance();
        ?>

        <div class="vinttro-cta-wrapper" data-listing-id="<?php the_ID(); ?>">
            <?php echo do_blocks( '<!-- wp:block {"ref":6496} /-->'); ?>
        </div>

        <div class="vinttro-sidebar-tabs" style="margin-top: 30px;">
            <?php 
            // THE "GOLDEN NUGGET" FUNCTION from your debug
            if ( function_exists( 'auto_listings_output_listing_tabs' ) ) {
                auto_listings_output_listing_tabs();
            }
            ?>
        </div>
 
    </div>
 </div>

<?php do_action( 'auto_listings_after_single_listing' ); ?>
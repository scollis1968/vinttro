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

// Get the URL for the main listings page
// Note: 'auto-listing' is the standard post type for this plugin
$listings_archive_url = get_post_type_archive_link( 'auto-listing' );
?>

<div class="vinttro-listing-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2 style="margin: 0;"><?php the_title(); ?></h2>
    
    <?php if ( $listings_archive_url ) : ?>
        <a href="<?php echo esc_url( $listings_archive_url ); ?>" class="button return-to-listings">
            <span class="dashicons dashicons-arrow-left-alt"></span> Return to Results
        </a>
    <?php endif; ?>
</div>

<div class="vinttro-main-grid" style="display: flex; gap: 40px; align-items: flex-start; flex-wrap: wrap;">
    <div class="vinttro-content-area" style="flex: 1 1 600px; min-width: 0;">
        
        <div class="vinttro-gallery-box">
            <?php do_action( 'auto_listings_single_gallery' ); ?>
        </div>
        
        <div class="vinttro-description-text" style="margin-top: 40px;">
            <?php 
            if ( function_exists( 'auto_listings_template_single_tagline' ) ) auto_listings_template_single_tagline();
            if ( function_exists( 'auto_listings_template_single_description' ) ) auto_listings_template_single_description();
            ?>
        </div>
    </div>

    <div class="vinttro-sidebar-area" style="flex: 1 1 300px; background: #fafafa; padding: 30px; border-radius: 20px; border: 1px solid #eee;">
                
        <?php 
        if ( function_exists( 'auto_listings_template_single_vehicle' ) ) auto_listings_template_single_vehicle();
        if ( function_exists( 'auto_listings_template_single_price' ) ) auto_listings_template_single_price();
        if ( function_exists( 'auto_listings_template_single_at_a_glance' ) ) auto_listings_template_single_at_a_glance();
        ?>

        <div class="vinttro-cta-wrapper" data-listing-id="<?php the_ID(); ?>">
            <?php echo do_blocks( ''); ?>
        </div>
    </div>
 </div>

<?php do_action( 'auto_listings_after_single_listing' ); ?>
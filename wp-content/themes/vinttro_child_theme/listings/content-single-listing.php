<?php
/**
 * The Template for displaying listing content in the single-listing.php template
 *
 * @package Auto Listings.
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
        <h1 class="title"><?php the_title(); ?></h1>
    </header>

    <div class="vinttro-main-grid" style="display: flex; gap: 40px; align-items: flex-start; flex-wrap: wrap;">

        <div class="vinttro-content-area" style="flex: 1 1 600px; min-width: 0;">
            
            <div class="vinttro-gallery-box">
                <?php auto_listings_get_template( 'single-listing/image.php' ); ?>
            </div>
            
            <div class="vinttro-description-text" style="margin-top: 40px;">
                <h3 style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">The Story</h3>
                <div class="entry-content">
                    <?php the_content(); ?>
                </div>
            </div>

        </div>

        <div class="vinttro-sidebar-area" style="flex: 1 1 300px; background: #fafafa; padding: 30px; border-radius: 20px; border: 1px solid #eee;">
            
            <div class="vinttro-price-box" style="margin-bottom: 25px;">
                <?php auto_listings_get_template( 'single-listing/price.php' ); ?>
            </div>
            
            <div class="vinttro-specs-table">
                <?php auto_listings_get_template( 'single-listing/data.php' ); ?>
            </div>

            <div class="vinttro-enquiry-form" style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">
                <?php do_action( 'auto_listings_single_summary' ); ?>
            </div>
        </div>

    </div>
</div>

<?php do_action( 'auto_listings_after_single_listing' ); ?>
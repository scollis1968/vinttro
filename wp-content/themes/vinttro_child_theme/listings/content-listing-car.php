<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Fetch the data from Auto Listings meta keys
$year    = get_post_meta( get_the_ID(), '_al_listing_year', true );
$mileage = get_post_meta( get_the_ID(), '_al_listing_mileage', true );
$bhp     = get_post_meta( get_the_ID(), '_al_listing_engine_power', true ); // Auto Listings uses engine_power for BHP
$make    = get_post_meta( get_the_ID(), '_al_listing_make_display', true );
?>

<div class="vinttro-car-card">
    <div class="car-image">
        <?php auto_listings_template_loop_image(); ?>
    </div>
    
    <div class="car-details">
        <h1><?php the_title(); ?></h1>
        
        <span class="price"><?php auto_listings_template_loop_price(); ?></span>

        <div class="vinttro-specs-row">
            <?php if ( $year ) : ?>
                <div class="spec-item">
                    <span class="spec-label">Year</span>
                    <span class="spec-value"><?php echo esc_html( $year ); ?></span>
                </div>
            <?php endif; ?>

            <?php if ( $mileage ) : ?>
                <div class="spec-item">
                    <span class="spec-label">Mileage</span>
                    <span class="spec-value"><?php echo number_format( $mileage ); ?></span>
                </div>
            <?php endif; ?>

            <?php if ( $bhp ) : ?>
                <div class="spec-item">
                    <span class="spec-label">BHP</span>
                    <span class="spec-value"><?php echo esc_html( $bhp ); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="car-meta">
            Make: <?php echo esc_html( $make ); ?>
        </div>
        
        <div class="car-actions">
             <a href="<?php the_permalink(); ?>" class="al-button">View Details</a>
        </div>
    </div>
</div>
<div class="vinttro-car-card">
    <div class="watch-image">
        <?php auto_listings_template_loop_image(); ?>
    </div>
    <div class="car-details">
        <h3><?php the_title(); ?></h3>
        <span class="price"><?php auto_listings_template_loop_price(); ?></span>
        
        <div class="car-meta">
            <?php 
               $brand = get_post_meta( get_the_ID(), '_al_listing_make_display', true );
               echo "Make: " . esc_html($brand);
            ?>
        </div>
    </div>
</div>
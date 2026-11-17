<div class="vinttro-bike-card">
    <div class="bike-image">
        <?php auto_listings_template_loop_image(); ?>
    </div>
    <div class="bike-details">
        <h3><?php the_title(); ?></h3>
        <span class="price"><?php auto_listings_template_loop_price(); ?></span>
        
        <div class="bike-meta">
            <?php 
               $brand = get_post_meta( get_the_ID(), '_al_listing_make_display', true );
               echo "Make: " . esc_html($brand);
            ?>
        </div>
    </div>
</div>
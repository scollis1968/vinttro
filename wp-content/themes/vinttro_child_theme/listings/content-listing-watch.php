<div class="vinttro-watch-card">
    <div class="watch-image">
        <?php auto_listings_template_loop_image(); ?>
    </div>
    <div class="watch-details">
        <h3><?php the_title(); ?></h3>
        <span class="price"><?php auto_listings_template_loop_price(); ?></span>
        
        <div class="watch-meta">
            <?php 
               $brand = get_post_meta( get_the_ID(), '_al_listing_make_display', true );
               echo "Brand: " . esc_html($brand);
            ?>
        </div>
    </div>
</div>
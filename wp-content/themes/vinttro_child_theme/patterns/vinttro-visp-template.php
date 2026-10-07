<?php
/**
 * Title: vinttro-visp-template
 * Slug: vinttro_child_theme/vinttro-visp-template
 * Inserter: no
 */
?>
<!-- wp:group {"style":{"spacing":{"blockGap":"0","padding":{"top":"0px"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"center"}} -->
<div class="wp-block-group" style="margin-top:0;margin-bottom:0;padding-top:0px"><!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"vinttro-logo"} -->
<figure class="wp-block-image size-full vinttro-logo"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/images/vinttro_visp_black.png" alt="" class=""/></figure>
<!-- /wp:image --></div>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"header","area":"header"} /-->

<!-- wp:group {"tagName":"main","style":{"spacing":{"margin":{"top":"var:preset|spacing|60"},"padding":{"top":"0px","bottom":"0px"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group" style="margin-top:var(--wp--preset--spacing--60);padding-top:0px;padding-bottom:0px"><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="padding-top:0;padding-bottom:0"><!-- wp:post-content {"align":"full","layout":{"type":"constrained"}} /--></div>
<!-- /wp:group --></main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer"} /-->

<!-- wp:paragraph -->
<p></p>
<!-- /wp:paragraph -->
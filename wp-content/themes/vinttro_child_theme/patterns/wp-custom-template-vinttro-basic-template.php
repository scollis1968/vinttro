<?php
/**
 * Title: wp-custom-template-vinttro-basic-template
 * Slug: vinttro_child_theme/wp-custom-template-vinttro-basic-template
 * Inserter: no
 */
?>
<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"center"}} -->
<div class="wp-block-group"><!-- wp:image {"width":"300px","sizeSlug":"full","linkDestination":"none","className":"vinttro-logo","style":{"spacing":{"margin":{"top":"-20px"}}}} -->
<figure class="wp-block-image size-full is-resized vinttro-logo" style="margin-top:-20px"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/images/vinttro_black.png" alt="VINTTRO Logo" class="" style="width:300px"/></figure>
<!-- /wp:image --></div>
<!-- /wp:group -->

<!-- wp:group {"tagName":"main","style":{"spacing":{"margin":{"top":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group" style="margin-top:var(--wp--preset--spacing--60)"><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)"><!-- wp:post-content {"align":"full","layout":{"type":"constrained"}} /--></div>
<!-- /wp:group --></main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer"} /-->
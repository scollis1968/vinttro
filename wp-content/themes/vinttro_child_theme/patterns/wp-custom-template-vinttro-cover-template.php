<?php
/**
 * Title: wp-custom-template-vinttro-cover-template
 * Slug: vinttro_child_theme/wp-custom-template-vinttro-cover-template
 * Inserter: no
 */
?>
<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
<div class="wp-block-group"><!-- wp:image {"scale":"cover","sizeSlug":"full","linkDestination":"none","align":"center","className":"vinttro-logo","style":{"spacing":{"margin":{"left":"0","top":"-30px"}},"layout":{"selfStretch":"fit","flexSize":null}}} -->
<figure class="wp-block-image aligncenter size-full vinttro-logo" style="margin-top:-30px;margin-left:0"><img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/images/vinttro_cover_black.png" alt="" class="" style="object-fit:cover"/></figure>
<!-- /wp:image --></div>
<!-- /wp:group -->

<!-- wp:group {"tagName":"main","style":{"spacing":{"margin":{"top":"var:preset|spacing|60"},"padding":{"top":"0px","bottom":"0px"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group" style="margin-top:var(--wp--preset--spacing--60);padding-top:0px;padding-bottom:0px"><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="padding-top:0;padding-bottom:0"><!-- wp:post-content {"align":"full","layout":{"type":"constrained"}} /--></div>
<!-- /wp:group --></main>
<!-- /wp:group -->

<!-- wp:paragraph {"align":"center","fontSize":"small"} -->
<p class="has-text-align-center has-small-font-size">VINTTRO Cover is a trading style of Sona Insurance Solutions Ltd. VINTTRO Limited is an Appointed Representative of Sona Insurance Solutions Limited who is authorised and regulated by the <a href="https://www.fca.org.uk/">FCA</a> under reference number 927990.</p>
<!-- /wp:paragraph -->

<!-- wp:template-part {"slug":"footer"} /-->

<!-- wp:paragraph -->
<p></p>
<!-- /wp:paragraph -->
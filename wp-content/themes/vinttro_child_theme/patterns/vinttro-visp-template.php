<?php
/**
 * Title: vinttro-visp-template
 * Slug: vinttro_child_theme/vinttro-visp-template
 * Inserter: no
 */

// 1. Render Persistent CTI Header Bar at top
if ( function_exists( 'visp_render_cti_header' ) ) {
    visp_render_cti_header();
}
?>

<!-- 2. Main VISP Content Area -->
<!-- wp:group {"tagName":"main","style":{"spacing":{"margin":{"top":"0px"},"padding":{"top":"20px","bottom":"0px"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group" style="margin-top:0px;padding-top:20px;padding-bottom:0px">
    <!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"0","bottom":"0"}}},"layout":{"type":"constrained"}} -->
    <div class="wp-block-group alignfull" style="padding-top:0;padding-bottom:0">
        <!-- wp:post-content {"align":"full","layout":{"type":"constrained"}} /-->
    </div>
    <!-- /wp:group -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer"} /-->
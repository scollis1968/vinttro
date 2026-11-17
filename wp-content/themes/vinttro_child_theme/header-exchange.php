<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); // Vital for styles and "Show More" script ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="vinttro-exchange-header wp-block-template-part">
    <div class="exchange-branding-bar">
        <figure class="wp-block-image aligncenter size-full vinttro-logo">
            <img src="https://uat.vinttro.co.uk/wp-content/themes/twentytwentyfive/assets/images/vinttro_exchange_black.png"
                 alt=""
                 style="object-fit:cover">
        </figure>
    </div>

    <?php 
    /**
     * This pulls in your site's standard navigation menu 
     * so people can still find their way home.
     */
    block_template_part( 'header' ); 
    ?>
</header>
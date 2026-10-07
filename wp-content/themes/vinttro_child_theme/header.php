
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); // This loads all your CSS, JS, and Fonts ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="wp-block-template-part">
    <?php 
    /* * This line tells WordPress to grab the modern 
     * Twenty Twenty-Five header you built in the Site Editor.
     */
    block_template_part( 'header' ); 
    ?>
</header>
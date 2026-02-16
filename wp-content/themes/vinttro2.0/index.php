<?php
// index.php
/* This file is the main template index for the theme.
It tells WordPress how to display content by default.
*/
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
    <h1>Hello World - Theme Placeholder</h1>
    <?php wp_footer(); ?>
</body>
</html>
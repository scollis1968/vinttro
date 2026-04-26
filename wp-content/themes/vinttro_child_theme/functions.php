<?php
/**
 * VINTTRO Custom Theme Functions
 */

// 1. LINK TO PARENT THEME (Twenty Twenty-Five)
add_action( 'wp_enqueue_scripts', 'vinttro_child_enqueue_styles' );
function vinttro_child_enqueue_styles() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
    wp_enqueue_style( 'child-style', get_stylesheet_uri(), array( 'parent-style' ), wp_get_theme()->get('Version') );
}

// 2. SUITECRM V8 API INTEGRATION
add_action('user_register', 'suitecrm_store_user_id_after_registration', 10, 1);
function suitecrm_store_user_id_after_registration($user_id) {
    set_transient("suitecrm_user_$user_id", $user_id, 60); 
}

// 3. COMPLIANZ ACCESSIBILITY FIX
add_filter('cmplz_banner_html', function($html) {
    $search = '/(<summary.*?>)(.*?)(<input.*?class="cmplz-consent-checkbox".*?>)(.*?)(<\/summary>)/s';
    $replace = '$1$2$5$3$4'; 
    return preg_replace($search, $replace, $html);
}, 10, 1);

// 4. BRUTE FORCE LOGO OVERLAP FIX
add_action( 'wp_head', function() {
    ?>
    <style id="vinttro-logo-force-fix">
        .vinttro-logo, figure.vinttro-logo, .wp-block-image.vinttro-logo {
            position: relative !important;
            z-index: 99999 !important;
            overflow: visible !important;
            display: block !important;
            margin-left: auto !important;
            margin-right: auto !important;
            text-align: center !important;
            margin-top: -40px !important; 
            margin-bottom: 0 !important;
        }
        .vinttro-logo img {
            max-width: 160px !important;
            width: 160px !important;
            height: auto !important;
            object-fit: contain !important; 
        }
        .wp-block-group.is-vertical, .wp-block-group.is-layout-flex, header.wp-block-template-part {
            overflow: visible !important;
            gap: 0 !important; 
        }
        #mobmenu-sticky-header, .mob_menu_header, .mobmenu-sticky-wrapper {
            z-index: 99990 !important;
            overflow: visible !important;
        }
    </style>
    <?php
}, 9999 );

// 5. AUTO LISTING - DATABASE/QUERY FILTER (PHP)
// This was previously inside the script tag - now it is in the correct PHP area.
add_action( 'pre_get_posts', function( $query ) {
    // 1. Ignore admin area
    if ( is_admin() ) return;

    // 2. Identify what we are looking at
    $requested_url = $_SERVER['REQUEST_URI'];
    $post_type = $query->get('post_type');

    // 3. Only log if the URL contains 'make=' or 'model=' 
    // (This filters out the Contact Form 7 noise)
    if ( isset($_GET['make']) || isset($_GET['model']) ) {
        error_log("--- TRAP TRIGGERED ---");
        error_log("URL: " . $requested_url);
        error_log("Query Post Type: " . (is_array($post_type) ? implode(',', $post_type) : $post_type));
        error_log("Is Main Query? " . ($query->is_main_query() ? 'YES' : 'NO'));
        
        // 4. Force the filter if it looks like an Auto Listing query
        // Even if it's NOT the main query
        if ( $post_type === 'auto-listing' || $post_type === 'listing' ) {
            error_log("APPLYING FILTERS NOW...");
            
            $tax_query = array('relation' => 'AND');

            if ( ! empty( $_GET['make'] ) ) {
                $tax_query[] = array(
                    'taxonomy' => 'make',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field( $_GET['make'] ),
                );
            }

            if ( ! empty( $_GET['model'] ) ) {
                $tax_query[] = array(
                    'taxonomy' => 'model',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field( $_GET['model'] ),
                );
            }

            $query->set( 'tax_query', $tax_query );
            error_log("Tax Query Set: " . print_r($tax_query, true));
        }
    }
});

// 6. AUTO LISTING - FRONT END UI FIXES (JavaScript)
add_action( 'wp_footer', function() {
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Merge UL lists
        const lists = document.querySelectorAll('ul.auto-listings-items');
        if (lists.length > 1) {
            const firstList = lists[0];
            lists.forEach((list, index) => {
                if (index > 0) {
                    while (list.firstChild) {
                        firstList.appendChild(list.firstChild);
                    }
                    list.remove();
                }
            });
        }
    });

    // Dependent Dropdown Fix
    jQuery(document).on('change', 'select[name="make"]', function() {
        var make_id = jQuery(this).val();
        var $model_select = jQuery('select[name="model"]');
        if (!make_id) {
            $model_select.val('').prop('disabled', true);
            if ($model_select[0].sumo) $model_select[0].sumo.reload();
            return;
        }
        setTimeout(function() {
            if ($model_select[0].sumo) {
                $model_select.prop('disabled', false);
                $model_select[0].sumo.unHighlightAll();
                $model_select[0].sumo.reload();
            }
        }, 100);
    });

    // Remove empty search
    jQuery('form.als').on('submit', function() {
        if (!jQuery(this).find('input[name="s"]').val()) {
            jQuery(this).find('input[name="s"]').remove();
        }
    });
    </script>
    <?php
}, 100 );
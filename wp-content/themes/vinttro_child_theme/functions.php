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
    if ( is_admin() ) return;
    if ( !isset($_GET['make']) && !isset($_GET['model']) ) return;

    $post_type = $query->get('post_type');
    if ( $post_type === 'auto-listing' || $post_type === 'listing' ) {
        
        // 1. Clear the locks
        $query->set('p', '');
        $query->set('page_id', '');
        $query->set('name', '');
        $query->set('tax_query', ''); 

        $meta_query = array('relation' => 'AND');

        // 2. Make Filter (Keep using = since this is working)
        if ( ! empty( $_GET['make'] ) ) {
            $meta_query[] = array(
                'key'     => '_al_listing_make_display',
                'value'   => sanitize_text_field( $_GET['make'] ),
                'compare' => '=',
            );
        }

        // 3. Model Filter (Using LIKE for partial matches)
        if ( ! empty( $_GET['model'] ) ) {
            $model_val = sanitize_text_field( $_GET['model'] );
            $meta_query[] = array(
                'key'     => '_al_listing_model_display', // Try this first
                'value'   => '%' . $model_val . '%',      // Match 308 within "308 GTB"
                'compare' => 'LIKE',
            );
        }

        $query->set( 'meta_query', $meta_query );
        
        // 4. Debug: Log what we are actually doing
        error_log("DEBUG: Filtering for Make: " . $_GET['make'] . " and Model LIKE: " . $_GET['model']);

        $query->is_single = false;
        $query->is_page = false;
        $query->is_archive = true;
    }
});


// THE LOUDER SQL LOGGER
add_filter( 'posts_request', function( $sql ) {
    // We only want to see the SQL if it's trying to find auto-listings
    if ( strpos($sql, "post_type = 'auto-listing'") !== false ) {
        error_log("!! SQL CHECK !!: " . $sql);
    }
    return $sql;
}, 10, 1 );



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

// TEMPORARY: View the internal data of the first car found
add_action('wp_head', function() {
    if (isset($_GET['make']) && current_user_can('administrator')) {
        $test_car = get_posts(array('post_type' => 'auto-listing', 'posts_per_page' => 1));
        if ($test_car) {
            $meta = get_post_meta($test_car[0]->ID);
            echo '<pre style="background:#fff; color:#000; padding:50px; position:fixed; top:0; z-index:99999; height:50vh; overflow:scroll; border:5px solid red;">';
            echo "--- CAR META DATA INSPECTOR ---\n";
            print_r($meta);
            echo '</pre>';
        }
    }
});
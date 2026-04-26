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

    // Only run if we have car filters in the URL
    if ( !isset($_GET['make']) && !isset($_GET['model']) ) return;

    // TARGETING: Only apply to the car listing query
    $post_type = $query->get('post_type');
    
    if ( $post_type === 'auto-listing' || $post_type === 'listing' ) {
        
        error_log("--- UNBINDING PAGE ID AND APPLYING FILTERS ---");

        // 1. THE FIX: Clear the specific ID restriction (6245) 
        // that is forcing "No Listings Found"
        $query->set('p', '');
        $query->set('page_id', '');
        $query->set('name', ''); 

        // 2. APPLY TAXONOMY FILTERS
        $tax_query = array('relation' => 'AND');

        // Make Filter - We'll try to match whatever casing is in the database
        if ( ! empty( $_GET['make'] ) ) {
            $make_slug = sanitize_text_field( $_GET['make'] );
            $tax_query[] = array(
                'taxonomy' => 'make',
                'field'    => 'slug',
                'terms'    => array( strtolower($make_slug), $make_slug ), // Checks both Ferrari and ferrari
            );
        }

        // Model Filter
        if ( ! empty( $_GET['model'] ) ) {
            $model_slug = sanitize_text_field( $_GET['model'] );
            $tax_query[] = array(
                'taxonomy' => 'model',
                'field'    => 'slug',
                'terms'    => array( strtolower($model_slug), $model_slug ),
            );
        }

        if ( count($tax_query) > 1 ) {
            $query->set( 'tax_query', $tax_query );
        }
        
        // 3. Ensure we aren't looking for a single page template
        $query->is_single = false;
        $query->is_page = false;
        $query->is_archive = true;
    }
});

// Update the SQL logger to be more specific
add_filter( 'posts_request', function( $sql ) {
    if ( isset($_GET['make']) && strpos($sql, 'auto-listing') !== false && strpos($sql, 'term_relationships') !== false ) {
        error_log("!! THE WINNING SQL !!: " . $sql);
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
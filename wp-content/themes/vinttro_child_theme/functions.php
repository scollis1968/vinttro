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

    // 1. Only run if we have car filters in the URL
    if ( !isset($_GET['make']) && !isset($_GET['model']) && !isset($_GET['max_price']) ) return;

    // 2. TARGETING: Match the auto-listing query
    $post_type = $query->get('post_type');
    if ( $post_type === 'auto-listing' || $post_type === 'listing' ) {
        
        // 3. UNBIND THE PAGE ID
        $query->set('p', '');
        $query->set('page_id', '');
        $query->set('name', '');
        $query->set('tax_query', ''); 

        // 4. APPLY META FILTERS
        $meta_query = array('relation' => 'AND');

        // Make Filter
        if ( ! empty( $_GET['make'] ) ) {
            $meta_query[] = array(
                'key'     => '_al_listing_make_display',
                'value'   => sanitize_text_field( $_GET['make'] ),
                'compare' => '=',
            );
        }

        // Model Filter (Fixed Key: _al_listing_model_name)
        if ( ! empty( $_GET['model'] ) ) {
            $meta_query[] = array(
                'key'     => '_al_listing_model_name',
                'value'   => sanitize_text_field( $_GET['model'] ),
                'compare' => '=',
            );
        }

        // Price Filter (Fixed Key: _al_listing_price)
        if ( ! empty( $_GET['max_price'] ) ) {
            $meta_query[] = array(
                'key'     => '_al_listing_price',
                'value'   => intval( $_GET['max_price'] ),
                'type'    => 'numeric',
                'compare' => '<=',
            );
        }

        $query->set( 'meta_query', $meta_query );

        // 5. Final State cleanup
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
   
    // Rename the button from "reset" to "Clear"
    const resetBtn = document.querySelector('.als-reset');
    if (resetBtn) {
        resetBtn.textContent = 'Clear Filters';
    }
    </script>
    <?php
}, 100 );


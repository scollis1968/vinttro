<?php
/**
 * VINTTRO Custom Theme Functions - REPAIRED
 */

// 1. LINK TO PARENT THEME
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

// 4. LOGO OVERLAP FIX
add_action( 'wp_head', function() {
    ?>
    <style id="vinttro-logo-force-fix">
        .vinttro-logo, figure.vinttro-logo, .wp-block-image.vinttro-logo {
            position: relative !important;
            z-index: 99999 !important;
            display: block !important;
            margin: -40px auto 0 auto !important;
            text-align: center !important;
        }
        .vinttro-logo img {
            max-width: 160px !important;
            width: 160px !important;
            height: auto !important;
        }
    </style>
    <?php
}, 9999 );

// 5. AUTO LISTING - SEARCH & SORT FILTER (REPAIRED)
// This version ONLY filters the cars and DOES NOT break the page layout.
add_action( 'pre_get_posts', function( $query ) {
    if ( is_admin() ) return;

    // We only want to intercept the query when it's looking for cars
    if ( $query->get('post_type') === 'auto-listing' || $query->get('post_type') === 'listing' ) {
        
        $meta_query = array('relation' => 'AND');
        //if ( is_page('cars') || is_page('exchange/cars') ) {
            $meta_query[] = array('key' => '_al_listing_vehicle_type', 'value' => 'car', 'compare' => '=');
        //}
        //if ( is_page('bikes') || is_page('exchange/bikes') ) {
            $meta_query[] = array('key' => '_al_listing_v_type', 'value' => 'motorbike', 'compare' => '=');
        //}
        if ( ! empty( $_GET['make'] ) ) {
            $meta_query[] = array('key' => '_al_listing_make_display', 'value' => sanitize_text_field( $_GET['make'] ), 'compare' => '=');
        }
        if ( ! empty( $_GET['model'] ) ) {
            $meta_query[] = array('key' => '_al_listing_model_name', 'value' => sanitize_text_field( $_GET['model'] ), 'compare' => '=');
        }
        if ( ! empty( $_GET['max_price'] ) ) {
            $meta_query[] = array('key' => '_al_listing_price', 'value' => intval( $_GET['max_price'] ), 'type' => 'numeric', 'compare' => '<=');
        }

        if ( count($meta_query) > 1 ) {
            $query->set( 'meta_query', $meta_query );
        }

        // Handle Sorting
        if ( ! empty( $_GET['orderby'] ) ) {
            $orderby = sanitize_text_field( $_GET['orderby'] );
            if ( $orderby == 'price' ) {
                $query->set( 'meta_key', '_al_listing_price' );
                $query->set( 'orderby', 'meta_value_num' );
                $query->set( 'order', 'ASC' );
            } elseif ( $orderby == 'price-high' ) {
                $query->set( 'meta_key', '_al_listing_price' );
                $query->set( 'orderby', 'meta_value_num' );
                $query->set( 'order', 'DESC' );
            }
        }
        // Vehicle Type Filter (Taxonomy)
        
        if ( ! empty( $_GET['v_type'] ) ) {
            $query->set( 'tax_query', array(
                array(
                    'taxonomy' => 'vehicle_type',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field( $_GET['v_type'] ),
                ),
            ));
        }       
        // IMPORTANT: We removed the lines that forced is_archive = true.
    }
});

// 6. UI FIXES (JavaScript)
add_action( 'wp_footer', function() {
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Clear Filters Logic
        const resetBtn = document.querySelector('.als-reset');
        if (resetBtn) {
            resetBtn.textContent = 'Clear filter';
            resetBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const form = this.closest('form.als');
                if (!form) return;
                form.querySelectorAll('select').forEach(select => {
                    select.value = '';
                    if (select.sumo) { select.sumo.unSelectAll(); select.sumo.reload(); }
                });
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) { submitBtn.click(); } else { form.submit(); }
            });
        }
    });

    // Dependent Dropdown Fix
    jQuery(document).on('change', 'select[name="make"]', function() {
        var $model_select = jQuery('select[name="model"]');
        setTimeout(function() {
            if ($model_select[0] && $model_select[0].sumo) {
                $model_select.prop('disabled', false);
                $model_select[0].sumo.reload();
            }
        }, 150);
    });
    </script>
    <?php
}, 100 );

// 7. ENVIRONMENT GATEKEEPER
add_action( 'template_redirect', function() {
    $is_production = ( strpos( $_SERVER['HTTP_HOST'], 'uat.' ) === false );
    if ( ! $is_production || current_user_can('manage_options') ) return;
    $restricted_paths = ['/exchange', '/dashboard', '/sell-your-car'];
    $current_path = $_SERVER['REQUEST_URI'];
    if ( strpos($current_path, '/under-construction') !== false ) return;
    foreach ( $restricted_paths as $path ) {
        if ( strpos( $current_path, $path ) !== false ) {
            wp_safe_redirect( home_url( '/under-construction/' ) );
            exit;
        }
    }
});

// 9. REGISTER VEHICLE TYPE TAXONOMY
add_action( 'init', function() {
    register_taxonomy( 'vehicle_type', 'auto-listing', array(
        'label'        => __( 'Vehicle Type' ),
        'rewrite'      => array( 'slug' => 'vehicle-type' ),
        'hierarchical' => true, // Acts like a category (checkboxes)
        'show_ui'      => true,
        'show_in_rest' => true,
    ));
});
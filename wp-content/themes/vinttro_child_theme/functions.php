<?php
error_log('VINTTRO: functions.php is definitely loading!');
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
    // Never run in admin
    if ( is_admin() ) return;

    // Only target the auto-listing post type
    // We REMOVED is_main_query() because shortcodes run sub-queries
    if ( $query->get('post_type') === 'auto-listing' || $query->get('post_type') === 'listing' ) {
        
        $current_url = $_SERVER['REQUEST_URI'];
        $tax_query = $query->get('tax_query') ?: array();
        if ( ! is_array($tax_query) ) $tax_query = array();

        // 1. HARD-CODED TAXONOMY LOGIC (Based on URL Path)
        if ( strpos($current_url, '/exchange/cars') !== false ) {
            $tax_query[] = array(
                'taxonomy' => 'vehicle_type',
                'field'    => 'slug',
                'terms'    => 'car', // Double check this slug in Admin -> Auto Listings -> Vehicle Type
                'operator' => 'IN',
            );
        } elseif ( strpos($current_url, '/exchange/bikes') !== false ) {
            $tax_query[] = array(
                'taxonomy' => 'vehicle_type',
                'field'    => 'slug',
                'terms'    => 'motorbike', // Double check this slug in Admin -> Auto Listings -> Vehicle Type
                'operator' => 'IN',
            );
        }

        // Apply the tax_query if we added something
        if ( ! empty( $tax_query ) ) {
            $query->set( 'tax_query', $tax_query );
        }

        // 2. DYNAMIC FILTERS (Meta Query)
        $meta_query = $query->get('meta_query') ?: array();
        if ( ! is_array($meta_query) ) $meta_query = array('relation' => 'AND');

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
    }
});

// ==========================================================
// 🔍 0. THE SNIFFER (Run this once to find the real names)
// ==========================================================
add_action( 'init', function() {
    if ( is_admin() || !isset($_GET['vinttro_debug']) ) return;
    
    error_log("--- VINTTRO CLASS SNIFFER START ---");
    $classes = get_declared_classes();
    foreach($classes as $class) {
        if ( stripos($class, 'Listing') !== false || stripos($class, 'ALS_') !== false ) {
            error_log("FOUND: " . $class);
        }
    }
    error_log("--- VINTTRO CLASS SNIFFER END ---");
}, 999 );

// ==========================================================
// 🚗 5a. AUTO LISTING - LOW-LEVEL FIELD OVERRIDE
// ==========================================================
add_filter( 'auto_listings_search_form_fields', function( $fields, $form_id ) {
    // Target your specific forms
    error_logs("auto_listings_search_form_fields - 1");

    $target_forms = [6619 => 'car', 6625 => 'motorbike'];
    if ( ! isset( $target_forms[$form_id] ) ) {
        error_logs("auto_listings_search_form_fields - 1");
        return $fields;
    }

    $type = $target_forms[$form_id];
    error_log("VINTTRO ATTEMPT: Overriding Fields for Form $form_id as $type");

    global $wpdb;
    foreach ( $fields as &$field ) {
        if ( $field['name'] === 'make' || $field['name'] === 'model' ) {
            $meta_key = ( $field['name'] === 'make' ) ? '_al_listing_make_display' : '_al_listing_model_name';
            
            $results = $wpdb->get_col( $wpdb->prepare( "
                SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
                JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE pm.meta_key = %s AND t.slug = %s AND tt.taxonomy = 'vehicle_type' AND p.post_status = 'publish'
            ", $meta_key, $type ) );

            if ( ! empty( $results ) ) {
                $new_options = [ '' => $field['placeholder'] ];
                foreach ( $results as $val ) { $new_options[$val] = $val; }
                $field['options'] = $new_options;
                error_log("VINTTRO SUCCESS: Injected " . count($results) . " options into $form_id");
            }
        }
    }
    return $fields;
}, 9999, 2 );

// ==========================================================
// 🔄 10. THE VIRTUAL CACHE KILLER (BYPASSING REDIS/SQL)
// ==========================================================

// 1. Force the plugin to think the HTML cache for Form 6619 and 6625 is ALWAYS empty
add_filter( 'pre_transient_auto_listings_search_form_6619', '__return_false', 9999 );
add_filter( 'pre_transient_auto_listings_search_form_6625', '__return_false', 9999 );

// 2. Force the plugin to think the Global Search Index is ALWAYS empty (Forces a rebuild)
add_filter( 'pre_option_auto_listings_search_data', '__return_false', 9999 );
add_filter( 'pre_transient_auto_listings_search_data', '__return_false', 9999 );

// 3. Log that the bypass is active
add_action( 'template_redirect', function() {
    if ( is_admin() || strpos($_SERVER['REQUEST_URI'], '/exchange/') === false ) return;
    error_log("VINTTRO: Virtual Cache Bypass Active for Exchange Page.");
    wp_cache_flush(); // Final nudge to the RAM cache
}, 1 );


// ==========================================================
// 6. UI FIXES (JavaScript)
// ==========================================================
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

    // Force the dropdowns to hide options that don't belong
    document.addEventListener("DOMContentLoaded", function() {
        const isCarPage = window.location.pathname.includes('/cars/');
        const isBikePage = window.location.pathname.includes('/bikes/');
        
        // Check the 'Make' dropdown for signs of the "Ghost"
        const makeSelect = document.querySelector('select[name="make"]');
        if (!makeSelect) return;

        const htmlContent = makeSelect.innerHTML;
        let cacheIsCorrupt = false;

        if (isCarPage && (htmlContent.includes('MOTO GUZZI') || htmlContent.includes('Yamaha'))) {
            console.warn("VINTTRO: Stale BIKE data detected on CAR page. Purging UI...");
            cacheIsCorrupt = true;
        } 
        else if (isBikePage && (htmlContent.includes('Porsche') || htmlContent.includes('Ford'))) {
            console.warn("VINTTRO: Stale CAR data detected on BIKE page. Purging UI...");
            cacheIsCorrupt = true;
        }

        if (cacheIsCorrupt) {
            // 1. Clear browser session storage
            sessionStorage.clear();
            localStorage.clear();

            // 2. Force the plugin's Reset button to click
            const resetBtn = document.querySelector('.als-reset');
            if (resetBtn) {
                console.log("VINTTRO: Triggering plugin reset...");
                resetBtn.click();
            } else {
                // Fallback: Reload the page with a cache-buster
                window.location.href = window.location.pathname + '?vinttro_refresh=' + Date.now();
            }
        }
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


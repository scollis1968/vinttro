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
// 🚗 5a. AUTO LISTING - DYNAMIC DROPDOWN FILTER (REPAIRED)
// ==========================================================
// We hook into BOTH possible filter names used by different versions of the plugin
add_filter( 'auto_listings_search_field_options', 'vinttro_filter_dropdowns', 999, 2 );
add_filter( 'als_search_field_options', 'vinttro_filter_dropdowns', 999, 2 );

function vinttro_filter_dropdowns( $options, $field ) {
    // Only target 'make' and 'model'
    if ( ! isset( $field['name'] ) || ( $field['name'] !== 'make' && $field['name'] !== 'model' ) ) {
        return $options;
    }

    // This log MUST appear if the filter is working
    error_log("VINTTRO: Filter 5a is triggering for: " . $field['name']);

    $current_url = $_SERVER['REQUEST_URI'];
    $target_type = '';

    if ( strpos($current_url, '/cars') !== false ) {
        $target_type = 'car'; 
    } elseif ( strpos($current_url, '/bikes') !== false ) {
        $target_type = 'motorbike'; 
    }

    if ( empty( $target_type ) ) return $options;

    global $wpdb;
    $meta_key = ( $field['name'] === 'make' ) ? '_al_listing_make_display' : '_al_listing_model_name';

    $results = $wpdb->get_col( $wpdb->prepare( "
        SELECT DISTINCT pm.meta_value 
        FROM {$wpdb->postmeta} pm
        JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
        JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
        WHERE pm.meta_key = %s 
        AND t.slug = %s 
        AND tt.taxonomy = 'vehicle_type'
        AND p.post_status = 'publish'
        ORDER BY pm.meta_value ASC
    ", $meta_key, $target_type ) );

    if ( ! empty( $results ) ) {
        error_log("VINTTRO: SQL Found " . count($results) . " items for $target_type");
        $new_options = array();
        
        // Preserve the first "All Makes" / "All Models" option
        $placeholder_label = reset($options);
        $new_options[''] = $placeholder_label;

        foreach ( $results as $value ) {
            $new_options[$value] = $value;
        }
        return $new_options;
    }

    return $options;
}

// ==========================================================
// 🔄 10. THE "UPDATE BUTTON" AUTOMATOR (SQL VERSION)
// ==========================================================
add_action( 'template_redirect', 'vinttro_brute_force_sync', 1 );
function vinttro_brute_force_sync() {
    if ( is_admin() || strpos($_SERVER['REQUEST_URI'], '/exchange/') === false ) return;

    global $wpdb;
    error_log("VINTTRO: Exchange detected. Running Brute Force Sync...");

    // 1. Kill the Search Form "Hash" caches
    // These are the specific transients that store the HTML of the form
    $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_als_sf_%'" );
    $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_als_sf_%'" );
    $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_als_search_form_%'" );

    // 2. Kill the Data Index
    delete_transient( 'als_search_data' );
    delete_option( 'als_search_data' );

    // 3. Trigger the Plugin's "Sync" if we can find it
    // We try the most common internal hooks for Auto Listings
    do_action( 'auto_listings_sync_listings' ); 
    do_action( 'als_sync_listings' );

    // 4. Force WP to forget the cache for this specific request
    wp_cache_flush();
}

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
        const isBikePage = window.location.pathname.includes('bikes');
        const isCarPage = window.location.pathname.includes('cars');
        
        // If we are on bikes, and the dropdown is full of cars, 
        // we can't easily filter with JS without the data, 
        // BUT we can trigger a click on the 'Clear' button automatically 
        // the very first time the page loads if the 'make' is wrong.
        
        if (isBikePage && document.body.innerHTML.indexOf('BMW') > -1 && document.body.innerHTML.indexOf('Ducati') === -1) {
            console.log("VINTTRO: Detected wrong data on Bike page. Forcing refresh...");
            // You could trigger a location.reload() or click the reset button here
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


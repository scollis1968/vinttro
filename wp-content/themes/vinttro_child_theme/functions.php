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
// 🚗 5a. AUTO LISTING - DYNAMIC DROPDOWN FILTER (ULTIMATE)
// ==========================================================
add_filter( 'auto_listings_search_field_options', function( $options, $field ) {
    if ( ! isset( $field['name'] ) || ( $field['name'] !== 'make' && $field['name'] !== 'model' ) ) {
        return $options;
    }

    // IF YOU SEE THIS LOG, THE CACHE IS BROKEN AND WE HAVE WON.
    error_log("VINTTRO SUCCESS: Filter 5a is now REBUILDING data for: " . $field['name']);

    $current_url = $_SERVER['REQUEST_URI'];
    $target_type = '';
    if ( strpos($current_url, '/cars') !== false ) { $target_type = 'car'; } 
    elseif ( strpos($current_url, '/bikes') !== false ) { $target_type = 'motorbike'; }

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
        $placeholder = reset($options); 
        $new_options = array('' => $placeholder);
        foreach ( $results as $value ) { $new_options[$value] = $value; }
        return $new_options;
    }
    return $options;
}, 9999, 2 );

// ==========================================================
// 🔄 10. THE "GHOST" KILLER (TARGETING THE HTML BLOCKS)
// ==========================================================
add_action( 'template_redirect', 'vinttro_search_form_cache_killer', 1 );

function vinttro_search_form_cache_killer() {
    if ( is_admin() || strpos($_SERVER['REQUEST_URI'], '/exchange/') === false ) return;

    global $wpdb;
    error_log("VINTTRO: Exchange page detected. Executing Sledgehammer Sync...");

    // 1. Delete the "Frozen" HTML blocks from the database
    // The plugin saves the whole form HTML under these names
    $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_auto_listings_sf_%'" );
    $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_auto_listings_sf_%'" );
    $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_als_sf_%'" );

    // 2. Wipe the search data index
    delete_transient( 'auto_listings_search_data' );
    delete_option( 'auto_listings_search_data' );

    // 3. Clear the Object Cache (Redis/Memcached)
    // This is the most important step for UAT environments
    wp_cache_flush();
    
    // 4. Force a Re-index (This is the "Update Button" logic)
    if ( class_exists( '\AutoListings\SearchQuery' ) ) {
        $sq = new \AutoListings\SearchQuery();
        if ( method_exists($sq, 'update_data') ) { $sq->update_data(); }
    }
    
    error_log("VINTTRO: Sledgehammer Complete. Dropdowns should refresh.");
}

// ==========================================================
// 🧪 11. DISABLE SHORTCODE CACHING
// ==========================================================
// These filters tell the plugin NEVER to save the form HTML
add_filter( 'auto_listings_search_form_cache_form', '__return_false', 100 );
add_filter( 'auto_listings_search_form_cache_results', '__return_false', 100 );

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


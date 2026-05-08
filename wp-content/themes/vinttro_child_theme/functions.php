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

// 5a. AUTO LISTING - filtering the search dropdown options (REPAIRED)
// 5a. AUTO LISTING - filtering the search dropdown options (REPAIRED)
add_filter( 'auto_listings_search_field_options', function( $options, $field ) {
    // Only target 'make' and 'model' fields
    if ( ! isset( $field['name'] ) || ( $field['name'] !== 'make' && $field['name'] !== 'model' ) ) {
        return $options;
    }

    // 1. DETECT CONTEXT (Check both URI and Referer for AJAX support)
    $current_url = $_SERVER['REQUEST_URI'];
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    
    $target_type = '';

    if ( strpos($current_url, '/exchange/cars') !== false || strpos($referer, '/exchange/cars') !== false ) {
        $target_type = 'car';
    } elseif ( strpos($current_url, '/exchange/bikes') !== false || strpos($referer, '/exchange/bikes') !== false ) {
        $target_type = 'motorbike';
    }

    // If we aren't on a specific page, just return the original options
    if ( empty( $target_type ) ) {
        return $options;
    }

    // 2. DETERMINE META KEY
    // Note: Ensure these match the keys used in your section 5 query
    $meta_key = ( $field['name'] === 'make' ) ? '_al_listing_make_display' : '_al_listing_model_name';

    // 3. DATABASE QUERY
    global $wpdb;
    
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
        AND pm.meta_value != ''
        ORDER BY pm.meta_value ASC
    ", $meta_key, $target_type ) );

    // 4. REBUILD OPTIONS
    if ( ! empty( $results ) ) {
        // Find the "placeholder" (e.g., 'All Makes' or 'Select Model') 
        // usually the first item in the $options array
        $first_key = key($options);
        $first_val = reset($options);
        
        $new_options = array();
        $new_options[$first_key] = $first_val; // Keep the empty/default choice at the top

        foreach ( $results as $value ) {
            $new_options[$value] = $value;
        }
        return $new_options;
    }

    return $options;
}, 20, 2 ); // Higher priority to ensure it runs after plugin defaults


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

// Clear AutoListing transients when the search page loads
add_action('template_redirect', 'vinttro_clear_listing_cache');
function vinttro_clear_listing_cache() {
    if (is_page('exchange-bikes') || is_page('exchange-cars')) {
        // This is a 'sledgehammer' approach to clear common transient patterns
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_als_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_als_%'");
    }
}
// Prevent AutoListing from using cached terms for dropdowns
add_filter('pre_get_posts', 'vinttro_disable_als_caching');
function vinttro_disable_als_caching($query) {
    if (isset($query->query_vars['post_type']) && $query->query_vars['post_type'] == 'listing') {
        $query->set('cache_results', false);
        $query->set('update_post_meta_cache', false);
        $query->set('update_post_term_cache', false);
    }
    return $query;
}
/**
 * FORCE AUTO-LISTING TO CLEAR TERM CACHE ON EXCHANGE PAGES
 * This mimics the "Adding a Listing" action that fixes your dropdowns.
 */
add_action('template_redirect', 'vinttro_force_clear_term_cache');

function vinttro_force_clear_term_cache() {
    // Only run this on your search/exchange pages to save server power
    if ( is_page('exchange-cars') || is_page('exchange-bikes') || is_post_type_archive('listing') ) {
        
        // 1. Clear the standard WordPress Term Cache for Makes and Models
        clean_term_cache([], 'make');
        clean_term_cache([], 'model');
        
        // 2. If the site is using a Persistent Object Cache (like Redis), this clears it
        if ( function_exists('wp_cache_flush_group') ) {
            wp_cache_flush_group('terms');
        }

        // 3. Delete the "Sticky" Transients that AutoListing likely creates
        // We use a wildcard approach to kill any saved 'makes' or 'models' data
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_als_terms_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_als_terms_%'");
        
        error_log('VINTTRO: Term cache manually cleared for AutoListing');
    }
}
add_filter('get_terms_args', 'vinttro_force_unique_terms', 10, 2);
function vinttro_force_unique_terms($args, $taxonomies) {
    if (in_array('make', (array)$taxonomies) || in_array('model', (array)$taxonomies)) {
        // This makes the "Cache Key" unique every single second
        $args['cache_domain'] = 'vinttro_' . time();
    }
    return $args;
}
/**
 * FORCE AUTO LISTINGS SEARCH REFRESH
 * This simulates clicking "Update" on a listing every time you load the exchange pages.
 */
add_action( 'template_redirect', 'vinttro_force_als_search_sync' );

function vinttro_force_als_search_sync() {
    // Only run on the search pages to prevent slowing down the rest of the site
    if ( is_page('exchange-cars') || is_page('exchange-bikes') ) {
        
        // 1. Clear the specific Auto Listings Transients
        // The plugin uses these names specifically to store dropdown data
        delete_transient( 'als_search_data' );
        delete_transient( 'als_search_filters' );
        
        // 2. Trigger the Auto Listings internal update class if it exists
        // This is the "Update Button" logic in code form
        if ( class_exists( 'Auto_Listings_Search_Data' ) ) {
            $search_data = new Auto_Listings_Search_Data();
            $search_data->update(); 
            // error_log('VINTTRO: Auto Listings Search Data Force Updated');
        }

        // 3. Clear the WP Term Cache for the dropdown taxonomies
        clean_term_cache( '', 'make' );
        clean_term_cache( '', 'model' );
    }
}
// Disable the internal cache for the Search Form itself
add_filter( 'als_search_form_cache_filters', '__return_false' );



// THE BROAD TRAP: Catch any update to any AutoListing data
add_action('updated_option', function($option, $old_value, $value) {
    if (strpos($option, 'als_') !== false || strpos($option, 'auto_listings') !== false) {
        error_log("VINTTRO DETECTED OPTION UPDATE: " . $option);
    }
}, 10, 3);

add_action('set_transient', function($transient, $value, $expiration) {
    if (strpos($transient, 'als_') !== false || strpos($transient, 'listing') !== false) {
        error_log("VINTTRO DETECTED TRANSIENT SET: " . $transient);
    }
}, 10, 3);
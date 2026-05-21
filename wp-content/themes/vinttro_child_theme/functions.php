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

// 1.a CONDITIONAL CSS (Only load on relevant pages)
add_action( 'wp_enqueue_scripts', function() {
    $current_url = $_SERVER['REQUEST_URI'];

    // Load only if URL contains /exchange/ OR if we are on a single listing page
    if ( strpos($current_url, '/exchange') !== false || is_singular('auto-listing') ) {
        wp_enqueue_style( 
            'vinttro-auto-listings', 
            get_stylesheet_directory_uri() . '/auto-listings.css', 
            array(), 
            '1.0.1' // Increment this version number to clear browser cache when you update the CSS
        );
    }
}, 20 );

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
        } elseif ( strpos($current_url, '/exchange/daily') !== false ) {
            $tax_query[] = array(
                'taxonomy' => 'vehicle_type',
                'field'    => 'slug',
                'terms'    => 'daily', // Double check this slug in Admin -> Auto Listings -> Vehicle Type
                'operator' => 'IN',
            );
        } elseif ( strpos($current_url, '/exchange/watches') !== false ) {
            $tax_query[] = array(
                'taxonomy' => 'vehicle_type',
                'field'    => 'slug',
                'terms'    => 'watches', // Double check this slug in Admin -> Auto Listings -> Vehicle Type
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
// 🚗 5a. AUTO LISTING - SHORTCODE ATTRIBUTE HIJACK
// ==========================================================
add_filter( 'shortcode_atts_auto_listings_search', 'vinttro_shortcode_hijack', 10, 3 );
add_filter( 'shortcode_atts_als', 'vinttro_shortcode_hijack', 10, 3 );

function vinttro_shortcode_hijack( $out, $pairs, $atts ) {
    $form_id = isset($atts['id']) ? $atts['id'] : '';
    
    // Log this - if this shows up, we have finally found the door!
    error_log("VINTTRO: Shortcode Hijack triggered for Form ID: " . $form_id);

    global $wpdb;
    
    // Map IDs to Vehicle Types
    $map = ['6619' => 'car', '6625' => 'motorbike'];
    if ( !isset($map[$form_id]) ) return $out;

    $type = $map[$form_id];

    // 1. We force the plugin to forget any "Search Data" for this specific request
    // This is the "Amnesia" move
    add_filter( 'pre_option_auto_listings_search_data', '__return_false' );
    add_filter( 'pre_transient_auto_listings_search_data', '__return_false' );

    // 2. We manually clear the HTML cache for this specific form ID right now
    $wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->options WHERE option_name = %s", '_transient_auto_listings_sf_' . $form_id) );
    
    return $out;
}

// ==========================================================
// 🔄 10. THE "MANUAL SYNC" (CALLING THE ENGINE DIRECTLY)
// ==========================================================
add_action( 'wp', function() {
    if ( is_admin() || strpos($_SERVER['REQUEST_URI'], '/exchange/') === false ) return;

    // This is the exact code that runs when you hit the "Update" button
    // We run it every time the page is loaded to ensure the index is fresh
    if ( class_exists( 'AutoListings\Listing\SearchData' ) ) {
        $sd = new \AutoListings\Listing\SearchData();
        $sd->update_all_search_data();
        error_log("VINTTRO: Update Button logic triggered via SearchData class.");
    }
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

// ==========================================================
// 🚀 CUSTOM SEARCH FORM (Cars, Bikes, Daily, Watches)
// ==========================================================
add_shortcode( 'vinttro_search', function() {
    global $wpdb;

    // 1. Identify where we are based on URL
    $current_url = $_SERVER['REQUEST_URI'];
    
    if ( strpos($current_url, '/bikes') !== false ) {
        $target_type = 'motorbike';
        $placeholder = 'Bike';
        $prices      = [1000, 3000, 5000, 10000, 15000, 20000, 30000, 50000];
    } elseif ( strpos($current_url, '/daily') !== false ) {
        $target_type = 'daily'; // Ensure this matches your 'vehicle_type' slug in WP
        $placeholder = 'Daily Driver';
        $prices      = [5000, 10000, 20000, 30000, 40000, 50000, 75000, 100000];
    } elseif ( strpos($current_url, '/watches') !== false ) {
        $target_type = 'watches'; // Ensure this matches your 'vehicle_type' slug in WP
        $placeholder = 'Watch';
        $prices      = [500, 1000, 2500, 5000, 10000, 20000, 50000, 100000];
    } else {
        $target_type = 'car';
        $placeholder = 'Car';
        $prices      = [5000, 10000, 20000, 30000, 50000, 75000, 100000, 150000];
    }

    // 2. Fetch only the Makes that exist for this Specific Type
    $makes = $wpdb->get_col( $wpdb->prepare( "
        SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
        JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
        JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
        WHERE pm.meta_key = '_al_listing_make_display' 
        AND t.slug = %s 
        AND tt.taxonomy = 'vehicle_type'
        AND p.post_status = 'publish'
        AND pm.meta_value != ''
        ORDER BY pm.meta_value ASC
    ", $target_type ) );

    // 3. Start building the HTML Form
    ob_start();
    ?>
    <form class="vinttro-custom-search als" method="GET" action="<?php echo esc_url( strtok($_SERVER["REQUEST_URI"], '?') ); ?>">
        
        <div class="als-field">
            <label class="als-field__label">Make</label>
            <select name="make" class="vinttro-select">
                <option value=""><?php echo "All $placeholder Makes"; ?></option>
                <?php foreach ( $makes as $make ) : ?>
                    <option value="<?php echo esc_attr($make); ?>" <?php selected( $_GET['make'] ?? '', $make ); ?>>
                        <?php echo esc_html($make); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="als-field">
            <label class="als-field__label">Max Price</label>
            <select name="max_price" class="vinttro-select">
                <option value="">Any Price</option>
                <?php 
                foreach ($prices as $p) {
                    printf('<option value="%d" %s>£%s</option>', 
                        $p, 
                        selected($_GET['max_price'] ?? '', $p, false), 
                        number_format($p)
                    );
                }
                ?>
            </select>
        </div>

        <div class="als-actions" style="margin-top:20px; display:flex; gap:10px;">
            <button type="submit" class="als-submit">Search</button>
            <a href="<?php echo esc_url( strtok($_SERVER["REQUEST_URI"], '?') ); ?>" class="als-reset" style="text-decoration:none; line-height:40px;">Clear</a>
        </div>

    </form>
    <?php
    return ob_get_clean();
});
// Include WebRTC infrastructure for /visp pages
if ( file_exists( get_stylesheet_directory() . '/inc/twilio-rtc.php' ) ) {
    require_once get_stylesheet_directory() . '/inc/twilio-rtc.php';
}
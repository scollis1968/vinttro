<?php
/**
 * VINTTRO Custom Theme Functions
 */

// 1. LINK TO PARENT THEME (Twenty Twenty-Five)

add_action( 'wp_enqueue_scripts', 'vinttro_child_enqueue_styles' );
function vinttro_child_enqueue_styles() {
    // This loads the parent theme styles
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
    
    // This specifically tells WordPress to load the CSS file you just edited
    wp_enqueue_style( 'child-style', get_stylesheet_uri(), array( 'parent-style' ), wp_get_theme()->get('Version') );
}


// 2. SUITECRM V8 API INTEGRATION
// Step 1: Store user ID after registration
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

/**
 * BRUTE FORCE LOGO OVERLAP FIX
 * This injects CSS directly into the <head> at the last possible moment.
 */
add_action( 'wp_head', function() {
    ?>
    <style id="vinttro-logo-force-fix">
        /* 1. Target the Logo Figure specifically */
        .vinttro-logo, 
        figure.vinttro-logo,
        .wp-block-image.vinttro-logo {
            position: relative !important;
            z-index: 99999 !important;
            overflow: visible !important;

            /* Forces centering regardless of 'aligncenter' or 'flex' parent */
            display: block !important;
            margin-left: auto !important;
            margin-right: auto !important;
            text-align: center !important;

            /* The Overlap Position */
            margin-top: -40px !important; 
            margin-bottom: 0 !important;
        }

        /* 2. Fix the Image inside */
        .vinttro-logo img {
            max-width: 300px !important;
            width: 300px !important;
            height: auto !important;
            /* Some cover blocks force object-fit, we need to ensure it doesn't distort */
            object-fit: contain !important; 
        }

        /* 3. The "Anti-Guillotine" Fix for the Parent Group */
        /* This targets the specific flex group on the cover page */
        .wp-block-group.is-vertical, 
        .wp-block-group.is-layout-flex,
        header.wp-block-template-part {
            overflow: visible !important;
            /* Removing the gap if it's pushing the logo down */
            gap: 0 !important; 
        }

        /* 4. Handle the Mobile Menu Plugin Bar 
           Ensure the bar sits BELOW the logo. */
        #mobmenu-sticky-header, 
        .mob_menu_header, 
        .mobmenu-sticky-wrapper {
            z-index: 99990 !important; /* Slightly lower than logo */
            overflow: visible !important;
        }

        /* 5. Fix for the specific width issue you saw on PROD */
        .vinttro-logo img {
            max-width: 160px !important;
            width: 160px !important;
            height: auto !important;
        }
    </style>
    <?php
}, 9999 );

add_action( 'wp_footer', function() {
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const descriptions = document.querySelectorAll('.description');

        descriptions.forEach(desc => {
            // Only add 'Show More' if the content is taller than our limit (120px)
            if (desc.scrollHeight > 120) {
                const toggle = document.createElement('span');
                toggle.className = 'show-more-toggle';
                toggle.innerText = 'Show More';
                
                // Insert the toggle right after the description div
                desc.parentNode.insertBefore(toggle, desc.nextSibling);

                toggle.addEventListener('click', function() {
                    const isExpanded = desc.classList.toggle('expanded');
                    toggle.innerText = isExpanded ? 'Show Less' : 'Show More';
                });
            }
        });

        const lists = document.querySelectorAll('ul.auto-listings-items');
        if (lists.length > 1) {
            const firstList = lists[0];
            lists.forEach((list, index) => {
                if (index > 0) {
                    // Move all list items to the first list
                    while (list.firstChild) {
                        firstList.appendChild(list.firstChild);
                    }
                    // Remove the now-empty list
                    list.remove();
                }
            });
        }

    });
    </script>
    <?php
}, 100 ); // Priority 100 ensures it loads after other scripts
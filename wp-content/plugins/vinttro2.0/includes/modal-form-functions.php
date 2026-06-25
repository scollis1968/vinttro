<?php

// 1. Define the function to output the meta tag
function my_custom_viewport_meta_tag() {
    // The meta tag to prevent zooming and fix fixed-position element centering
    // todo - remove commented line after testing
    //echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=6.0">';
}

// 2. Hook the function into the <head> section of your site
add_action( 'wp_head', 'my_custom_viewport_meta_tag' );

/**
 * Retrieves the numerical ID of a Contact Form 7 form whose title contains a string.
 *
 * @param string $title_substring The substring the CF7 form title must contain (e.g., 'quote-form').
 * @return int|null The form ID on success, or null if not found.
 */
function get_cf7_id_by_title_contains( $title_substring ) {
    // 1. Set up the WordPress query arguments
    $args = array(
        'post_type'      => 'wpcf7_contact_form', // The custom post type for CF7 forms
        'post_status'    => 'publish',
        's'              => $title_substring,     // Use the 's' (search) argument for LIKE matching
        'posts_per_page' => 1,                    // Get the first matching form
        'fields'         => 'ids',                // Only return the post IDs
    );

    // 2. Execute the query
    $forms = get_posts( $args );

    // 3. Return the ID if found
    if ( ! empty( $forms ) ) {
        return (int) $forms[0]; // Return the first matching ID
    }

    // 4. Return null if not found
    return null;
}

function my_custom_cf7_scripts() {
    ?>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            const conditionalGroups = document.querySelectorAll('.conditional-field');

            conditionalGroups.forEach(group => {
                const targetName = group.getAttribute('data-depends-on');
                const targetValue = group.getAttribute('data-if-value');

                // Function to check the current value and toggle visibility
                const toggleField = () => {
                    const checkedRadio = document.querySelector(`input[name="${targetName}"]:checked`);
                    const currentValue = checkedRadio ? checkedRadio.value : null;

                    if (currentValue === targetValue) {
                        group.style.display = 'block';
                    } else {
                        group.style.display = 'none';
                        group.querySelectorAll('input, textarea, select').forEach(el => el.value = '');
                    }
                };

                const allRadios = document.querySelectorAll(`input[name="${targetName}"]`);
                
                if (allRadios.length > 0) {
                    allRadios.forEach(radio => {
                        radio.addEventListener('change', toggleField);
                    });
                    toggleField();
                }
            });
        });

        /**
         * VINTTRO Custom Modal Form Switcher
         * Safely scoped globally so popup HTML execution blocks can target it via onclick actions
         */
        window.openInsuranceForm = function(type) {
            const selectorScreen = document.getElementById('insurance-selector');
            if (selectorScreen) {
                selectorScreen.style.display = 'none';
            }
            
            const selectedForm = document.getElementById('form-container-' + type);
            if (selectedForm) {
                selectedForm.style.display = 'block';
            }
            
            // Force Popup Builder to recalculate responsive layouts and dynamic element height updates
            setTimeout(function() {
                window.dispatchEvent(new Event('resize'));
            }, 50);
        };
    </script>
    <?php
}
add_action('wp_footer', 'my_custom_cf7_scripts');
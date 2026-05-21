
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
        'posts_per_page' => 1,                   // Get the first matching form
        'fields'         => 'ids',               // Only return the post IDs
    );

    // 2. Execute the query
    $forms = get_posts( $args );

    // 3. Optional Check: Since 's' searches title *and* content,
    //    you might want to add an extra check to ensure it's truly in the title,
    //    but for CF7 forms (which usually have empty content), 's' works well on the title.

    // 4. Return the ID if found
    if ( ! empty( $forms ) ) {
        return (int) $forms[0]; // Return the first matching ID
    }

    // 5. Return null if not found
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
                    // Specifically find the checked radio button in the group
                    const checkedRadio = document.querySelector(`input[name="${targetName}"]:checked`);
                    const currentValue = checkedRadio ? checkedRadio.value : null;

                    if (currentValue === targetValue) {
                        group.style.display = 'block';
                    } else {
                        group.style.display = 'none';
                        // Optional: Clear fields if hidden
                        group.querySelectorAll('input, textarea, select').forEach(el => el.value = '');
                    }
                };

                // Find ALL radio buttons in this group and attach the listener to each
                const allRadios = document.querySelectorAll(`input[name="${targetName}"]`);
                
                if (allRadios.length > 0) {
                    allRadios.forEach(radio => {
                        radio.addEventListener('change', toggleField);
                    });
                    
                    // Run once on load to catch default values
                    toggleField();
                }
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'my_custom_cf7_scripts');   
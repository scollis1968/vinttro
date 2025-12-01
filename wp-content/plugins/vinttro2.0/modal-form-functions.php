
<?php
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

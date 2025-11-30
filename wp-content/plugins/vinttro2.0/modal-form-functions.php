
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

document.addEventListener('wpcf7mailsent', function(event) {
    // 1. Get the ID of the form that was successfully submitted
    const formId = event.detail.contactFormId;

    // --- Configuration: UPDATE THESE SELECTORS ---
    // Replace 'YOUR_MODAL_POPUP_SELECTOR' with the actual CSS selector (ID or Class)
    // of your main modal container (the one containing the form).
    const mainModalSelector = get_cf7_id_by_title_contain(quote-request-); // Example: Use an ID

    // Replace 'YOUR_CONFIRMATION_POPUP_SELECTOR' with the actual selector (ID or Class)
    // of the separate confirmation modal you want to show.
    const confirmationModalSelector = '#your-confirmation-message-modal'; // Example: Use an ID
    // ---------------------------------------------

    // 2. Locate the main form modal and the confirmation modal
    //const mainModal = document.querySelector(mainModalSelector);
    const mainModal = document.querySelector('.modal-quote-request');
    const confirmationModal = document.querySelector(confirmationModalSelector);

    // Check if both elements are found
    if (mainModal && confirmationModal) {
        // 3. Close the main modal popup (containing the form)
        // This is where you call the specific function or change the class/style
        // that hides your specific modal.
        
        // --- Option A: If your modal uses a "hidden" class (COMMON) ---
        mainModal.classList.remove('is-active'); 
        // OR
        // mainModal.style.display = 'none'; // Option B: If it uses inline styles

        // 4. Open the new confirmation message popup
        // This is where you call the specific function or change the class/style
        // that shows your specific confirmation modal.
        
        // --- Option A: If your modal uses an "active" class (COMMON) ---
        confirmationModal.classList.add('is-active');
        // OR
        // confirmationModal.style.display = 'block'; // Option B: If it uses inline styles
        
        // You may need to use a function provided by your modal plugin/theme, e.g.:
        // myModalPlugin.close(mainModalSelector);
        // myModalPlugin.open(confirmationModalSelector);

    } else {
        console.error('One or both modal selectors were not found on the page.');
    }

}, false);

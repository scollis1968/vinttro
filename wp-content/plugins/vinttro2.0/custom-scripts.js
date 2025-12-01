
// custom-scripts.js (Updated Wrapper)
jQuery(document).ready(function($) {

    // 1. Get references to the file input and the preview container
    const fileInput = document.querySelector('input[type="file"][name="quote-image"]');
    const previewContainer = document.getElementById('quote-image-preview-container');

if (fileInput && previewContainer) {
        fileInput.addEventListener('change', function(event) {
            const file = event.target.files[0];

            // Clear any previous preview
            previewContainer.innerHTML = '';

            // Check if a file was selected and it's an image
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();

                // 2. Define what happens once the file is read
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result; // The data URL of the image

                    // 3. Apply basic styling for the preview
                    img.style.maxWidth = '100%'; 
                    img.style.height = 'auto';
                    img.style.borderRadius = '5px';
                    img.style.border = '1px solid #ccc';
                    img.style.maxHeight = '200px'; // Limit preview size

                    // 4. Insert the image into the container
                    previewContainer.appendChild(img);
                };

                // 5. Read the file as a Data URL
                reader.readAsDataURL(file);
            }
        });
    }

        // Get the hidden input field using the ID we added in CF7
    const pageURLField = document.getElementById('cf7-page-url');
    const pageNameField = document.getElementById('cf7-page-name');

    // Check if the element exists on the page
    if (pageURLField) {
        pageURLField.value = window.location.href;
    }
    if (pageNameField) {
        pageNameField.value = extractLastUrlSegment(window.location.href);
    }

});

function extractLastUrlSegment(urlString) {
  // 1. Clean the string: Remove any trailing forward slash (/)
  const cleanedUrl = urlString.endsWith('/') ? urlString.slice(0, -1) : urlString;

  // 2. Split the cleaned string by the forward slash (/)
  const segments = cleanedUrl.split('/');

  // 3. Pop the last element off the array (which is the segment we want)
  return segments.pop();
}

document.addEventListener('wpcf7mailsent', function(event) {
    // 1. Get the ID of the form that was successfully submitted
    const formId = event.detail.contactFormId;

    // --- Configuration: UPDATE THESE SELECTORS ---
    // Replace 'YOUR_MODAL_POPUP_SELECTOR' with the actual CSS selector (ID or Class)
    // of your main modal container (the one containing the form).
    const mainModalSelector = '#sg-popup-content-wrapper-1726'; // Example: Use an ID

    // Replace 'YOUR_CONFIRMATION_POPUP_SELECTOR' with the actual selector (ID or Class)
    // of the separate confirmation modal you want to show.
    const confirmationModalSelector = '#sg-popup-content-wrapper-1863'; // Example: Use an ID
    // ---------------------------------------------

    // 2. Locate the main form modal and the confirmation modal
    const mainModal = document.querySelector(mainModalSelector);
    //const mainModal = document.querySelector('.modal-quote-request');
    const confirmationModal = document.querySelector(confirmationModalSelector);
    //const confirmationModal = document.querySelector('.modal-confirmation-message');

    // Check if both elements are found
    if (mainModal && confirmationModal) {
        // 3. Close the main modal popup (containing the form)
        // This is where you call the specific function or change the class/style
        // that hides your specific modal.
        
        // --- Option A: If your modal uses a "hidden" class (COMMON) ---
        //mainModal.classList.remove('is-active'); 
        // OR
        mainModal.style.display = 'none'; // Option B: If it uses inline styles

        // 4. Open the new confirmation message popup
        // This is where you call the specific function or change the class/style
        // that shows your specific confirmation modal.
        
        // --- Option A: If your modal uses an "active" class (COMMON) ---
        //confirmationModal.classList.add('is-active');
        // OR
        confirmationModal.style.display = 'block'; // Option B: If it uses inline styles
        
        // You may need to use a function provided by your modal plugin/theme, e.g.:
        // myModalPlugin.close(mainModalSelector);
        // myModalPlugin.open(confirmationModalSelector);

    } else {
        console.error('One or both modal selectors were not found on the page.');
    }

}, false);

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
    
    // --- Configuration: UPDATE THESE IDs ---
    const formModalId = 1726; 
    const confirmationModalId = 1863; 
    
    // The class the confirmation popup's trigger button MUST have.
    const confirmationTriggerClass = `sg-popup-id-${confirmationModalId}`; 
    // This resolves to 'sg-popup-id-1863'
    // ---------------------------------------------
    
    if (typeof SGPBPopup !== 'undefined') {

        // 1. Close the main form popup (Confirmed function)
        SGPBPopup.closePopupById(formModalId);
        
        // 2. Open the new confirmation message popup by finding and clicking its trigger
        setTimeout(function() {
            
            // Search the entire document for an element with the confirmation popup's trigger class
            const confirmationTrigger = document.querySelector(`.${confirmationTriggerClass}`);

            if (confirmationTrigger) {
                // Manually trigger a click event on the hidden/existing trigger element
                confirmationTrigger.click(); 
                
                console.log(`Successfully closed form ${formModalId} and triggered confirmation ${confirmationModalId} via click.`);

            } else {
                console.error(`Confirmation popup trigger element (Class: ${confirmationTriggerClass}) not found on the page.`);
            }

        }, 200); 

    } else {
        console.error('Popup Builder API (SGPBPopup) not found.');
    }

}, false);
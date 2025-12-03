
// custom-scripts.js (Updated Wrapper)
jQuery(document).ready(function($) {
 
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

document.addEventListener('DOMContentLoaded', function() {
    const customArea = document.querySelector('.custom-upload-area');
    const hiddenInput = document.querySelector('.custom-file-upload-input');
    const placeholderImage = document.querySelector('.upload-placeholder-image');
    const instructions = customArea.querySelector('p');

    // 1. Link the click on the custom area to the hidden input
    if (customArea && hiddenInput) {
        customArea.addEventListener('click', function(e) {
            // Prevent the click event if it originated from the input itself
            if (e.target !== hiddenInput) {
                hiddenInput.click();
            }
        });
    }

    // 2. Handle image preview when a file is selected
    if (hiddenInput) {
        hiddenInput.addEventListener('change', function() {
            const file = this.files[0];

            if (file) {
                const reader = new FileReader();

                reader.onload = function(e) {
                    // Clear ALL contents of the custom upload area before adding the new image
                    customArea.innerHTML = ''; 
                    
                    const previewImage = document.createElement('img');
                    previewImage.src = e.target.result;
                    
                    // Add the necessary classes for styling (we'll define these next)
                    previewImage.classList.add('uploaded-preview-image');
                    previewImage.classList.add('fill-dropzone-image'); // <--- NEW CLASS for sizing
                    
                    // We no longer need to check for/remove old placeholder/instructions 
                    // because we used customArea.innerHTML = '';
                    
                    customArea.appendChild(previewImage);
                };

                reader.readAsDataURL(file);
            }
        });
    }
});
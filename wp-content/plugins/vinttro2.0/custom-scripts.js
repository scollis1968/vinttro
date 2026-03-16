
jQuery(document).ready(function($) {
 function updatePageContext() {
        const currentURL = window.location.href;
        const sourcePage = extractLastUrlSegment(currentURL);
        
        // Use .val() and then trigger 'change' so CF7 knows it happened
        $('.cf7-page-url').val(currentURL).trigger('change');
        $('.cf7-page-name').val(sourcePage).trigger('change');

        // Update the Header Message
        const headerElement = $('#dynamic-message-header');
        let message = "";
        
        // Normalize for matching
        const pageKey = sourcePage ? sourcePage.toLowerCase().replace(/-/g, ' ').trim() : "";

        switch (sourcePage.toLowerCase()) {
            case "specialist-car-insurance":
                //message = "🚗 At VINTTRO we understand what specialist car insurance means";
                message = "Here at VINTTRO we understand what specialist car insurance means";
                break;
            case "fleet insurance":
                message = "🚛 Streamline Your Business – Multi-Vehicle Fleet Rates";
                break;
            case "prestige car insurance":
                message = "✨ Tailored Cover for Your High-Performance Vehicle";
                break;
            case "motor trade":
                message = "🛠️ Professional Cover for Your Motor Trade Business";
                break;
            default:
                message = "Custom Quote for " + sourcePage.charAt(0).toUpperCase() + sourcePage.slice(1).toLowerCase().replace(/-/g, ' ').trim() + ", please provide some basic information and our expert team will be in touch.";
        }
        headerElement.html(message + ", please provide some basic information and our expert team will be in touch.");
    }

    // Run context update immediately AND when popup opens (Popup Builder event)
    updatePageContext();
    $(document).on('sgpbDidOpen', function() {
        updatePageContext();
    })

    // ==========================================================
    // 💡 UPDATE: CHANGE LISTENER (Handles file selection)
    // ==========================================================
    $('form.wpcf7-form').on('change', 'input.custom-file-upload-input', function() {
        
        console.log("Change event fired on vehicle input: ", $(this).attr('name'));

        // 1. Get the files array from the input element
        const input = this;
        const file = input.files[0];

        // 2. Locate the parent custom-upload-area
        const $uploadArea = $(input).closest('.custom-upload-area');
        
        // 3. Find or create the image element for preview
        let $previewImage = $uploadArea.find('img.preview-image');

        if (file) {
            const reader = new FileReader();

            // Check if the selected file is an image
            if (!file.type.match('image.*')) {
                console.error("Selected file is not an image.");
                // Optionally add code here to clear the input or show an error message
                return;
            }

            // Set up the reader to execute when the file is loaded
            reader.onload = function(e) {
                // If the image element doesn't exist, create it
                if ($previewImage.length === 0) {
                    $previewImage = $('<img>')
                        .addClass('preview-image')
                        .css({
                            'max-width': '100%',
                            'height': 'auto',
                            'display': 'block',
                            'margin-bottom': '10px'
                        });
                    // Insert the new image element right after the input's paragraph wrapper
                    $uploadArea.prepend($previewImage);
                }
                
                // 4. Update the source of the preview image
                $previewImage.attr('src', e.target.result);
                
                // OPTIONAL: Hide the descriptive text after image is uploaded
                $uploadArea.find('p:not(:has(span))').hide(); 
            };

            // Read the file content as a Data URL (Base64)
            reader.readAsDataURL(file);

        } else {
            // Handle clearing the preview if the file selection is cancelled/cleared
            if ($previewImage.length > 0) {
                $previewImage.remove();
                // OPTIONAL: Show the descriptive text again
                $uploadArea.find('p:not(:has(span))').show();
            }
        }
    });
    // ==========================================================
    // (CLICK LISTENER code remains unchanged and is placed here)
    // ==========================================================
    $('form.wpcf7-form').on('click', '.custom-upload-area', function(e) {
        e.stopPropagation();
        console.log("Visual custom-upload-area Clicked.");

        const $associatedInput = $(this).find('input.custom-file-upload-input');

        if ($associatedInput.length) {
            console.log("Associated Input Name: ", $associatedInput.attr('name'));
            $associatedInput[0].click();
        } else {
            console.error("ERROR: Could not find the associated file input within the clicked upload area.");
        }
    });

    // (extractLastUrlSegment function is fine and should be placed after document.ready)
});

function extractLastUrlSegment(urlString) {
  // ... (Your original function code) ...
  const cleanedUrl = urlString.endsWith('/') ? urlString.slice(0, -1) : urlString;
  const segments = cleanedUrl.split('/');
  return segments.pop();
}
/*
// ------------------- old version of confirmation popup logic, now replaced by the more robust click trigger method below -------------------
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
*/
document.addEventListener('wpcf7mailsent', function(event) {
    
    // --- Configuration ---
    const defaultConfirmationId = 1863; // The fallback popup 0333 4042 007
    
    // Map of specific forms that need a UNIQUE confirmation popup
    // Format: 'CF7_FORM_ID': UNIQUE_POPUP_ID
    const customPopups = {
        '4626': 5940, // b2b-enquiry                      - gets Confirmation Popup 5940 - 0333 4042 008
        '5938': 1863, // b2b-insurance                    - gets Confirmation Popup 1863 - 0333 4042 007
        '5034': 5940, // b2b-legal-enquiry                - gets Confirmation Popup 5940 - 0333 4042 008
        '2293': 1863, // cover-quote-request              - gets Confirmation Popup 1863 - 0333 4042 007
        '2303': 1863, // cover-quote-request-car          - gets Confirmation Popup 1863 - 0333 4042 007
        '5020': 5940, // legal-enquiry                    - gets Confirmation Popup 5940 - 0333 4042 008
        '5195': 5940, // member-application               - gets Confirmation Popup 5940 - 0333 4042 008
        '4675': 5940, // vehicle-services-general-enquiry - gets Confirmation Popup 5940 - 0333 4042 008
        '6088': 6150  // VIsP Fleet Vehicle Check         - gets Thank You popup 150
    };
    // ---------------------

    const submittedFormId = event.detail.contactFormId;
    
    // Determine which confirmation ID to use
    const confirmationModalId = customPopups[submittedFormId] || defaultConfirmationId;
    const confirmationTriggerClass = `sg-popup-id-${confirmationModalId}`;

    if (typeof SGPBPopup !== 'undefined') {

        // 1. Close the form popup (we use the submitted ID as the reference)
        SGPBPopup.closePopupById(submittedFormId);
        
        // 2. Trigger the confirmation popup
        setTimeout(function() {
            const confirmationTrigger = document.querySelector(`.${confirmationTriggerClass}`);

            if (confirmationTrigger) {
                confirmationTrigger.click(); 
                console.log(`Form ${submittedFormId} sent. Triggering Popup: ${confirmationModalId}`);
            } else {
                console.error(`Trigger class .${confirmationTriggerClass} not found.`);
            }
        }, 200); 

    } else {
        console.error('Popup Builder API not found.');
    }

}, false);
/*
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
*/
/*-- debug -this is woking for 1st box only!
document.addEventListener('DOMContentLoaded', function() {
    // Select ALL custom upload areas (the parent container for each repeatable block)
    const allCustomAreas = document.querySelectorAll('.custom-upload-area');

    // Iterate over each custom upload area found
    allCustomAreas.forEach(customArea => {
        // Find the hidden input and other related elements *within this specific customArea*
        const hiddenInput = customArea.querySelector('.custom-file-upload-input');
        // Note: 'placeholderImage' and 'instructions' are not strictly needed for the functionality
        // but if you add them back, ensure they use customArea.querySelector()

        // 1. Link the click on the custom area to the hidden input
        if (customArea && hiddenInput) {
            customArea.addEventListener('click', function(e) {
                // Check if the click target is the hidden input itself (which prevents double click action)
                if (e.target !== hiddenInput) {
                    hiddenInput.click();
                }
            });
        }

        // 2. Handle image preview when a file is selected
        if (hiddenInput) {
            hiddenInput.addEventListener('change', function() {
                const file = this.files[0];
                const currentCustomArea = this.closest('.custom-upload-area'); // Get the specific area for this input

                if (file && currentCustomArea) {
                    const reader = new FileReader();

                    reader.onload = function(e) {
                        // Clear ALL contents of the CURRENT custom upload area
                        currentCustomArea.innerHTML = ''; 
                        
                        const previewImage = document.createElement('img');
                        previewImage.src = e.target.result;
                        
                        // Add the necessary classes for styling
                        previewImage.classList.add('uploaded-preview-image');
                        previewImage.classList.add('fill-dropzone-image');
                        
                        currentCustomArea.appendChild(previewImage);
                    };

                    reader.readAsDataURL(file);
                }
            });
        }
    });

    // *** IMPORTANT: ADD EVENT LISTENERS FOR NEWLY ADDED REPEATABLE FIELDS ***
    // The Repeatable Fields plugin should fire an event when a new field is added.
    // If you know the event it fires, you can use that.
    // Assuming the plugin fires a standard jQuery event on the form when a new field group is added:
    // This is an example, you might need to check the plugin's documentation for the exact event name.
    
    // Find the main form element (adjust selector if needed)
    const formElement = document.querySelector('.wpcf7-form'); 

    if (formElement) {
        // Listen for the Contact Form 7 event that is often triggered after a dynamic change.
        // The Repeatable Fields plugin might use a specific event, but we can try a general CF7 one.
        document.addEventListener('wpcf7-dynamically-added-item', function (event) {
            // When a new item is added, re-run the logic on the new element(s)
            
            // Re-select all custom areas, or just the new ones if the event provides them.
            // For simplicity and robustness, we can re-run the whole loop, 
            // but this is not the most efficient. 
            // A better solution: Modify the above script into a function and call it here.
            
            // Since we can't be sure of the event/new element, let's stick to the standard fix:
            // The Contact Form 7 Repeatable Fields plugin often works by triggering the 
            // "wpcf7-dynamically-added-item" event on the document when a new group is added.
            
            // To simplify, let's wrap the core logic into a reusable function:
            handleUploadArea(event.target); // Assuming 'event.target' is the newly added element
            
        }, false);
    }
    
    function handleUploadArea(container) {
        // Find all custom upload areas within the container (or just the container itself)
        const newCustomAreas = container.matches('.custom-upload-area') 
                                ? [container] 
                                : container.querySelectorAll('.custom-upload-area');
        
        newCustomAreas.forEach(customArea => {
            // Re-run the logic from above for this specific new/re-evaluated customArea
            const hiddenInput = customArea.querySelector('.custom-file-upload-input');

            // 1. Click Listener (Re-added, but might need to be careful of double listeners)
            // It's usually better to check if an event listener has already been added.
            // But since this is a new element, we assume it's safe to add.
            if (customArea && hiddenInput) {
                 customArea.addEventListener('click', function(e) {
                    if (e.target !== hiddenInput) {
                        hiddenInput.click();
                    }
                });
            }

            // 2. Change Listener
            if (hiddenInput) {
                hiddenInput.addEventListener('change', function() {
                    const file = this.files[0];
                    const currentCustomArea = this.closest('.custom-upload-area');

                    if (file && currentCustomArea) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            currentCustomArea.innerHTML = ''; 
                            const previewImage = document.createElement('img');
                            previewImage.src = e.target.result;
                            previewImage.classList.add('uploaded-preview-image', 'fill-dropzone-image');
                            currentCustomArea.appendChild(previewImage);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
        });
    }

    // After the initial loop, call the function for any existing elements as well 
    // (though the initial loop already covers this, this is a pattern for reusability):
    document.querySelectorAll('.custom-upload-area').forEach(area => handleUploadArea(area));

});
*/

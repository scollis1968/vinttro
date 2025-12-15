
// custom-scripts.js (Updated Wrapper)
jQuery(document).ready(function($) {
    const currentURL = window.location.href;
    const lastSegment = extractLastUrlSegment(currentURL);

    $('.cf7-page-url').val(currentURL); 

    // Target the field with class 'cf7-page-name'
    $('.cf7-page-name').val(lastSegment);

    $('form.wpcf7-form').on('change', 'input.custom-file-upload-input', function() {
        
        // This function will fire when the 'change' event occurs 
        // on *any* element matching 'input.custom-file-upload-input' 
        // that is *inside* the 'form.wpcf7-form' element.

        // The 'this' keyword inside here refers to the specific input that was changed 
        // (whether it's the first or the tenth cloned instance).

        // --- Insert your custom logic here ---
        console.log("Change event fired on vehicle input: ", $(this).attr('name'));

        // Example: If you need to populate a hidden field based on this click, 
        // the logic should be here.
        
    });
    // --- ADDED: CLICK LISTENER FOR THE VISUAL UPLOAD TRIGGER ---
    // This fires when the user clicks the visual element that should open the file selector.
    // **You must replace '.vehicle-upload-trigger' with the actual class/ID of your visual button/dropzone area.**
    $('form.wpcf7-form').on('click', '.custom-upload-area', function(e) {
        e.stopPropagation();
        console.log("Visual custom-upload-area Clicked.");

        // 1. Find the associated hidden input by searching DOWN from the clicked element (this).
        // The input is a child of the clicked .custom-upload-area.
        const $associatedInput = $(this).find('input.custom-file-upload-input');

        // 2. IMPORTANT: Check if the element was actually found
        if ($associatedInput.length) {
            // Now you can safely display the name
            console.log("Associated Input Name: ", $associatedInput.attr('name'));

            // 3. Trigger the click on the hidden input to open the file selection dialog.
            // $associatedInput.trigger('click');
            $associatedInput[0].click();
        } else {
            console.error("ERROR: Could not find the associated file input within the clicked upload area.");
        }
    });
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


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
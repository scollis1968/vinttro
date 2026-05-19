console.log("VINTTRO Script Initialized - Version 1.2");

jQuery(document).ready(function($) {
    console.log("DOM is ready. jQuery is active and running logic.");

    // Force clear AutoListing session storage on page load
    if (window.location.href.includes('bikes')) {
        localStorage.removeItem('als_search_params'); // Common name for storage
        sessionStorage.clear();
        console.log("Bike page detected: Attempted to clear session storage.");
    }

    // ==========================================================
    // 🚗 1. AUTOLISTING: LINK VEHICLE TYPE -> MAKE -> MODEL
    // ==========================================================
    // We target the dropdowns by their common AutoListing names
    const $vehicleTypeField = $('select[name="vehicle_type"]');
    const $makeField = $('select[name="make"]');
    const $modelField = $('select[name="model"]');

    $vehicleTypeField.on('change', function() {
        const typeValue = $(this).val();
        console.log("Vehicle Type changed to: " + typeValue);

        // Reset the Make and Model dropdowns
        $makeField.val('').trigger('change');
        $modelField.val('').trigger('change');

        // Note: If your dropdowns use Select2 (searchable boxes), 
        // we trigger the specific select2 refresh here:
        if ($makeField.hasClass('select2-hidden-accessible')) {
            $makeField.trigger('change.select2');
            $modelField.trigger('change.select2');
        }
    });

    // ==========================================================
    // 📄 2. PAGE CONTEXT LOGIC (CF7 Headers)
    // ==========================================================
    function updatePageContext() {
        const currentURL = window.location.href.toLowerCase();
        const cleanPath = window.location.pathname.replace(/\/$/, ""); 
        const sourcePage = cleanPath.split('/').pop().replace(/-/g, ' ');

        $('.cf7-page-url').val(window.location.href).trigger('change');
        $('.cf7-page-name').val(sourcePage).trigger('change');

        const headerElement = $('#dynamic-message-header');
        let message = "Custom Quote";

        if (currentURL.includes('fleet')) {
            message = "Multi-Vehicle Fleet Rates";
        } else if (currentURL.includes('motor-trade')) {
            message = "Professional Cover for Your Motor Trade Business";
        } else if (currentURL.includes('car') && (currentURL.includes('performance') || currentURL.includes('prestige'))) {
            message = "Tailored Cover for Your High-Performance Vehicle";
        } else if (currentURL.includes('car')) {
            message = "Specialist Car Insurance";
        }

        headerElement.html(`${message}, please provide some basic information.`);
    }

    updatePageContext();
    $(document).on('sgpbDidOpen', function() { updatePageContext(); });

    // ==========================================================
    // 🖼️ 3. FILE UPLOAD PREVIEW (CF7)
    // ==========================================================
    $('form.wpcf7-form').on('change', 'input.custom-file-upload-input', function() {
        const input = this;
        const file = input.files[0];
        const $uploadArea = $(input).closest('.custom-upload-area');
        let $previewImage = $uploadArea.find('img.preview-image');

        if (file && file.type.match('image.*')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                if ($previewImage.length === 0) {
                    $previewImage = $('<img>').addClass('preview-image').css({'max-width': '100%','display': 'block'});
                    $uploadArea.prepend($previewImage);
                }
                $previewImage.attr('src', e.target.result);
                $uploadArea.find('p:not(:has(span))').hide(); 
            };
            reader.readAsDataURL(file);
        }
    });

    // Click trigger for upload area
    $('form.wpcf7-form').on('click', '.custom-upload-area', function(e) {
        if (!$(e.target).is('input')) {
            $(this).find('input.custom-file-upload-input').click();
        }
    });

    // ==========================================================
    // 🔄 4. RESET BUTTON
    // ==========================================================
    $('.als-reset').on('click', function(e) {
        console.log("Reset clicked. Reloading clean page.");
        window.location.href = window.location.pathname; 
    });

    // ==========================================================
    // 🔔 5. CF7 SUCCESS: TRIGGER HIDDEN CONFIRMATION POPUP
    // ==========================================================
    document.addEventListener('wpcf7mailsent', function(event) {
        console.log("CF7 Form Sent successfully! Form ID: " + event.detail.contactFormId);
        
        // 1. Find the hidden CTA button that triggers your popup.
        // Change '.my-hidden-popup-button' to match the actual CSS class of your button.
        const $hiddenPopupBtn = $('.confirmation-popup-trigger-button'); 
        
        // 2. Trigger the click if the button exists on the page
        if ($hiddenPopupBtn.length > 0) {
            console.log("Hidden popup button found. Simulating click to launch popup...");
            $hiddenPopupBtn.click();
        } else {
            console.warn("CF7 submitted, but no hidden popup button class was found on this page.");
        }
    }, false);
    
});
// Force AJAX requests to be unique so they can't be cached
jQuery(document).ajaxSend(function(event, jqXHR, settings) {
    if (settings.url.indexOf('admin-ajax.php') !== -1) {
        settings.url += (settings.url.indexOf('?') === -1 ? '?' : '&') + 'vinttro_refresh=' + new Date().getTime();
    }
});
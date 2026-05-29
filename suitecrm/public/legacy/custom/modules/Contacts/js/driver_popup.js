$(document).ready(function() {
    var checkbox = $('#is_driver_c');
    
    // Ensure the checkbox exists on the current screen layout
    if (checkbox.length > 0) {
        
        // 1. Dynamically append the button HTML immediately following the checkbox
        checkbox.after('&nbsp;<button type="button" id="open_driver_popup_btn" class="btn btn-success" style="display:none; vertical-align: middle; padding: 2px 10px;">Manage Driver Profile</button>');
        
        // 2. Bind the click handler to extract the Contact ID and open the modal
        $('#open_driver_popup_btn').click(function() {
            var contactId = $('input[name="record"]').val(); 
            launchDriverModal(contactId);
        });

        // 3. Check the initial state on page load
        toggleDriverButton();

        // 4. Listen for user clicks/changes
        checkbox.change(function() {
            toggleDriverButton();
        });
    }
});

function toggleDriverButton() {
    if ($('#is_driver_c').is(':checked')) {
        $('#open_driver_popup_btn').show();
    } else {
        $('#open_driver_popup_btn').hide();
    }
}

function launchDriverModal(contactId) {
    // Points directly to your custom driver module package key name: visp_driver
    var url = "index.php?module=visp_driver&action=EditView&to_pdf=true&contact_id_link=" + contactId;
    
    bootbox.dialog({
        title: "Manage Supplementary Driver Details",
        message: '<iframe src="' + url + '" width="100%" height="500px" frameborder="0"></iframe>',
        size: "large"
    });
}
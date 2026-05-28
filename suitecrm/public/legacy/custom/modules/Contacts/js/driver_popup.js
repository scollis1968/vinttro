$(document).ready(function() {
    // 1. Initial State Check
    toggleDriverButton();

    // 2. Listen for changes on your custom checkbox/toggle field
    $('#is_driver_c').change(function() {
        toggleDriverButton();
    });
});

function toggleDriverButton() {
    if ($('#is_driver_c').is(':checked')) {
        $('#open_driver_popup_btn').show();
    } else {
        $('#open_driver_popup_btn').hide();
    }
}

function launchDriverModal(contactId) {
    // Open SuiteCRM's native QuickCreate or standard EditView inside a modal iframe
    var url = "index.php?module={Your_Driver_Module}&action=EditView&to_pdf=true&contact_id_link=" + contactId;
    
    // Utilizing SuiteCRM's native Bootstrap modal wrapper
    bootbox.dialog({
        title: "Manage Supplementary Driver Details",
        message: '<iframe src="' + url + '" width="100%" height="500px" frameborder="0"></iframe>',
        size: "large"
    });
}
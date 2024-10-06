jQuery(document).ready(function ($) {
    // Show the modal on page load
    $('#bassdk-login-modal').show();

    // Close the modal when the close button is clicked
    $('.bassdk-close').on('click', function () {
        $('#bassdk-login-modal').hide();
    });

    // Hide the modal when clicking outside of the modal content
    $(window).on('click', function (event) {
        if ($(event.target).is('#bassdk-login-modal')) {
            $('#bassdk-login-modal').hide();
        }
    });

    // Show modal when Login button is clicked
    $('#bassdk-login-btn').on('click', function () {
        $('#bassdk-login-modal').show();
    });
});

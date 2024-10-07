jQuery(document).ready(function ($) {
    // Show the modal on page load
    try {
        window.addEventListener("JSBridgeReady", async (event) => {
            console.log("isJSBridgeReady :", isJSBridgeReady)
            if (isJSBridgeReady) {
                $('#bassdk-login-modal').show();
                console.log("JSBridgeReady Successfully loaded ");
                await getBasAuthCode("653ed1ff-59cb-41aa-8e7f-0dc5b885a024").then((res) => {
                    if (res) {
                        console.log("Logined Successfully :", res)
                        alert("Logined Successfully ")
                    }
                }).catch((error) => {
                    console.error("ERROR on catch getBasAuthCode:", error)
                })
            }
        }, false);
    } catch (error) {
        console.error("ERROR on getBasAuthCode:", error)
    }

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

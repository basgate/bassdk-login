(function () {
    jQuery(document).ready(function () {
        console.log("===== STARTED login_footer.js");
        if (location.search.indexOf('reauth=1') >= 0) {
            location.href = location.href.replace('reauth=1', '');
        }
        // eslint-disable-next-line no-implicit-globals
        function authUpdateQuerystringParam(uri, key, value) {
            var re = new RegExp('([?&])' + key + '=.*?(&|$)', 'i');
            var separator = uri.indexOf('?') !== -1 ? '&' : '?';
            if (uri.match(re)) {
                return uri.replace(re, '$1' + key + '=' + value + '$2');
            } else {
                return uri + separator + key + '=' + value;
            }
        }
        // eslint-disable-next-line
        function signInCallback(resData) { // jshint ignore:line
            var $ = jQuery;
            console.log("signInCallback() resData:", JSON.stringify(resData))

            if (resData.hasOwnProperty('authId')) {
                // Send the authId to the server
                var ajaxurl = $("#admin_ajxurl").val();
                var nonce = $("#basgate_login_nonce").val();
                var login_redirect_url = $("#login_redirect_url").val();
                $.post(ajaxurl, {
                    action: 'process_basgate_login',
                    data: resData,
                    nonce: nonce,
                    // authId: resData.authId,
                }, function (data, textStatus) {

                    console.log("signInCallback() textStatus :", textStatus)
                    console.log("signInCallback() data :", data)

                    var newHref = login_redirect_url;
                    console.log("signInCallback() before newHref: ", newHref)
                    newHref = authUpdateQuerystringParam(newHref, 'external', 'basgate');
                    console.log("signInCallback() after newHref: ", newHref)

                    if (location.href === newHref) {
                        console.log('signInCallback location.reload() location.href:', location.href);
                        location.reload();
                    } else {
                        console.log("signInCallback() else location.href: ", location.href)
                        location.href = newHref;
                    }
                });
            } else {
                // If user denies access, reload the login page.
                if (resData.error === 'access_denied' || resData.error === 'user_signed_out') {
                    window.location.reload();
                }
            }
        }

        try {
            console.log("===== STARTED bassdk_login_form javascript")
            window.addEventListener("JSBridgeReady", async (event) => {
                document.getElementById('basgate-pg-spinner').removeAttribute('hidden');
                document.querySelector('.basgate-overlay').removeAttribute('hidden');
                //TODO: retrive Client id here
                var clientId = jQuery("#bas_client_id").val();
                console.log("JSBridgeReady Successfully loaded clientId:", clientId);
                if (!('getBasAuthCode' in window)) {
                    console.log("JSBridgeReady waiting to load getBasAuthCode...");
                }
                try {
                    await getBasAuthCode(clientId).then((res) => {
                        console.log("getBasAuthCode 111 res:", JSON.stringify(res))
                        if (res) {
                            if (res.status == "1") {
                                signInCallback(res.data);
                            } else {
                                console.error("ERROR on getBasAuthCode res:", JSON.stringify(res))
                            }
                        }
                    }).catch((error) => {
                        console.error("ERROR on catch getBasAuthCode:", error)
                    })
                } catch (error) {
                    console.error("ERROR getBasAuthCode 111:", error)
                    try {
                        await getBasAuthCode(clientId).then((res) => {
                            console.log("getBasAuthCode 222 res:", res)
                            if (res) {
                                if (res.status == "1") {
                                    signInCallback(res.data);
                                } else {
                                    console.error("ERROR on getBasAuthCode res:", res)
                                }
                            }
                        }).catch((error) => {
                            console.error("ERROR on catch getBasAuthCode:", error)
                        })
                    } catch (error) {
                        console.error("ERROR getBasAuthCode 222:", error)
                    }
                }
            }, false);
        } catch (error) {
            console.error("ERROR on getBasAuthCode:", error)
        }
    });
})();
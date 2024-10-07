<?php
/*
Plugin Name: Bassdk WP Login
Description: A popup login dialog that appears when the user opens the website.
Version: 1.12
Author: Bas Gate SDK
*/

namespace BasgateSDK;

// use 
require_once __DIR__ . '/options/abstract-class-singleton.php';
require_once __DIR__ . '/options/class-helper.php';
require_once __DIR__ . '/options/class-admin-page.php';
require_once __DIR__ . '/options/class-login-access.php';
require_once __DIR__ . '/options/class-login-form.php';
require_once __DIR__ . '/options/class-options.php';
require_once __DIR__ . '/options/class-wp-plugin-authorizer.php';

// use BasgateSDK\Options\WP_Plugin_Authorizer;

// function bassdk_enqueue_scripts()
// {
//     wp_enqueue_style('bassdk-login-styles', plugin_dir_url(__FILE__) . 'css/styles.css', array(), '1.0');
//     //TODO: Replace this script with cdn url
//     wp_enqueue_script('bassdk-login-script', plugin_dir_url(__FILE__) . 'js/script.js', array('jquery'), '1.0', true);
//     // wp_enqueue_script('bassdk-login-cdn-script', esc_url('https://pub-8bba29ca4a7a4024b100dca57bc15664.r2.dev/sdk/stage/v1/public.js'), array('jquery'), '1.0', true);
//     //TODO: Add Admin options part
// }
// add_action('wp_enqueue_scripts', 'bassdk_enqueue_scripts');

function bassdk_login_form()
{
    ob_start();
?>
    <div id="bassdk-login-modal" class="bassdk-modal">
        <div class="bassdk-modal-content" style="text-align: center;">
            <span class="bassdk-close">&times;</span>
            <h2>BAS Login</h2>
            <script type="text/javascript">
                function invokeBasLogin() {
                    try {
                        console.log("isJSBridgeReady :", isJSBridgeReady)
                    } catch (error) {
                        console.error("ERROR on isJSBridgeReady:", error)
                    }
                    try {
                        window.addEventListener("JSBridgeReady", async (event) => {
                            console.log("isJSBridgeReady :", isJSBridgeReady)
                            console.log("JSBridgeReady Successfully loaded ");
                            await getBasAuthCode("653ed1ff-59cb-41aa-8e7f-0dc5b885a024").then((res) => {
                                if (res) {
                                    console.log("Logined Successfully :", res)
                                    alert("Logined Successfully ")
                                }
                            }).catch((error) => {
                                console.error("ERROR on catch getBasAuthCode:", error)
                            })
                        }, false);
                    } catch (error) {
                        console.error("ERROR on getBasAuthCode:", error)
                    }
                }
            </script>
            <form method="post" action="<?php echo esc_url(site_url('wp-login.php', 'login_post')); ?>">
                <label for="username">Username:</label>
                <input type="text" name="log" id="username" required>

                <label for="password">Password:</label>
                <input type="password" name="pwd" id="password" required>

                <input type="submit" value="Login By BAS">
            </form>
        </div>
    </div>
    <!-- <button id="bassdk-login-btn">Login By BAS </button> -->
    <script type="application/javascript" crossorigin="anonymous" src="https://pub-8bba29ca4a7a4024b100dca57bc15664.r2.dev/sdk/merchant/v1/public.js" onload="invokeBasLogin();"></script>
<?php
    return ob_get_clean();
}

function plugin_root()
{
    return __FILE__;
}

// add_shortcode('bassdk_login', 'bassdk_login_form');

// function bassdk_add_modal()
// {
//     echo do_shortcode('[bassdk_login]');
// }
// add_action('wp_footer', 'bassdk_add_modal');


// Instantiate the plugin class.
WP_Plugin_Authorizer::get_instance();

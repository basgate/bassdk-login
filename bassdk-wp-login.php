<?php
/*
Plugin Name: Bassdk WP Login
Description: A popup login dialog that appears when the user opens the website.
Version: 1.0
Author: Bas Gate SDK
*/

function bassdk_enqueue_scripts()
{
    wp_enqueue_style('bassdk-login-styles', plugin_dir_url(__FILE__) . 'css/styles.css', array(), '1.0');
    //TODO: Replace this script with cdn url
    wp_enqueue_script('bassdk-login-script', plugin_dir_url(__FILE__) . 'js/script.js', array('jquery'), '1.0', true);
    //TODO: Add Admin options part
}
add_action('wp_enqueue_scripts', 'bassdk_enqueue_scripts');

function bassdk_login_form()
{
    ob_start();
?>
    <div id="bassdk-login-modal" class="bassdk-modal">
        <div class="bassdk-modal-content" style="text-align: center;">
            <span class="bassdk-close">&times;</span>
            <h2>BAS Login</h2>
            <form method="post" action="<?php echo esc_url(site_url('wp-login.php', 'login_post')); ?>">
                <label for="username">Username:</label>
                <input type="text" name="log" id="username" required>

                <label for="password">Password:</label>
                <input type="password" name="pwd" id="password" required>

                <input type="submit" value="Login By BAS">
            </form>
        </div>
    </div>
    <button id="bassdk-login-btn">Login By BAS </button>
<?php
    return ob_get_clean();
}
add_shortcode('bassdk_login', 'bassdk_login_form');

function bassdk_add_modal()
{
    echo do_shortcode('[bassdk_login]');
}
add_action('wp_footer', 'bassdk_add_modal');

<?php
/*
Plugin Name: Bassdk WP Login
Description: A popup login dialog that appears when the user opens the website.
Version: 1.18
Tested up to: 5.8.0
Requires PHP: 6.6.2
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
require_once __DIR__ . '/options/class-authentication.php';
require_once __DIR__ . '/lib/BasgateConstants.php';

// use BasgateSDK\Options\WP_Plugin_Basgate;

// function bassdk_enqueue_scripts()
// {
//     wp_enqueue_style('bassdk-login-styles', plugin_dir_url(__FILE__) . 'css/styles.css', array(), '1.0');
//     //TODO: Replace this script with cdn url
//     wp_enqueue_script('bassdk-login-script', plugin_dir_url(__FILE__) . 'js/script.js', array('jquery'), '1.0', true);
//     // wp_enqueue_script('bassdk-login-cdn-script', esc_url('https://pub-8bba29ca4a7a4024b100dca57bc15664.r2.dev/sdk/stage/v1/public.js'), array('jquery'), '1.0', true);
//     //TODO: Add Admin options part
// }
// add_action('wp_enqueue_scripts', 'bassdk_enqueue_scripts');


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
WP_Plugin_Basgate::get_instance();

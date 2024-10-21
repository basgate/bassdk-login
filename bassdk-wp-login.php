<?php
/*
 * Plugin Name: Bassdk WP Login
 * Plugin URI: https://github.com/Basgate/bassdk-wp-login
 * Description: هذه الاضافة تمكنك من ربط متجرك بداخل منصة بس والذي تقدم لك العديد من المحافظ المالية والبنوك المختلفة
 * Version: 0.1.80
 * Requires at least: 5.0.1
 * Tested up to: 6.6.2
 * Requires PHP: 7.4
 * Author: Basgate Super APP  
 * Author URI: https://basgate.com/
 * Tags: Basgate, BasSDK Super App, PayWithBasgate, Basgate WooCommerce, BasSDK Plugin, BasSDK Payment Gateway
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


function plugin_root()
{
    return __FILE__;
}


// Instantiate the plugin class.
WP_Plugin_Basgate::get_instance();

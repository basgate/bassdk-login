<?php
/*
 * Plugin Name: Bassdk Login
 * Plugin URI: https://github.com/Basgate/bassdk-login
 * Description: This blugin enabels you to opreat payments through Basgateway that provides you with many Local E-Wallets
 * Version: 0.2.7
 * Requires at least: 6.0.1
 * Tested up to: 6.6.2
 * Requires PHP: 7.4
 * Author: Basgate Super APP  
 * Author URI: https://basgate.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
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

<?php
/*
Plugin Name: Bassdk WP Login
Description: A popup login dialog that appears when the user opens the website.
Version: 1.21
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


function plugin_root()
{
    return __FILE__;
}

// Instantiate the plugin class.
WP_Plugin_Basgate::get_instance();

<?php

/**
 * Basgate Login SDK
 *
 * @license  GPL-2.0+
 * @link     https://github.com/basgate
 * @package  Basgate Login SDK
 */


namespace BasgateSDK;


/**
 * Main plugin class. Activates/deactivates the plugin, and registers all hooks.
 */
class WP_Plugin_Basgate extends Singleton
{

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		// Installation and uninstallation hooks.
		register_activation_hook('bassdk-login/bassdk-login.php', array($this, 'activate'));
		register_deactivation_hook('bassdk-login/bassdk-login.php', array($this, 'deactivate'));

		/**
		 * Register hooks.
		 */

		// // Custom wp authentication routine using external service.
		add_filter('authenticate', array(Authentication::get_instance(), 'custom_authenticate'), 20, 3);

		// // Custom logout action using external service.
		add_action('clear_auth_cookie', array(Authentication::get_instance(), 'pre_logout'));
		add_action('wp_logout', array(Authentication::get_instance(), 'custom_logout'));

		// // Create settings link on Plugins page.
		add_filter('plugin_action_links_' . plugin_basename(plugin_root()), array(Admin_Page::get_instance(), 'plugin_settings_link'));

		add_action('login_enqueue_scripts',  array(Login_Form::get_instance(), 'bassdk_enqueue_scripts'));
		// Modify the log in URL (if applicable options are set).
		add_filter('login_url', array(Login_Form::get_instance(), 'maybe_add_external_wordpress_to_log_in_links'));

		// // Enable localization. Translation files stored in /languages.
		add_action('plugins_loaded', array($this, 'load_textdomain'));

		// Create menu item in Settings.
		add_action('admin_menu', array(Admin_Page::get_instance(), 'add_plugin_page'));

		// Create options page.
		add_action('admin_init', array(Admin_Page::get_instance(), 'page_init'));

		// // // Add custom css and js to wp-login.php.
		// add_action('login_footer', array(Login_Form::get_instance(), 'load_login_footer_js'));

		add_action('login_footer', array(Login_Form::get_instance(), 'bassdk_add_modal'));

		// add_action('wp_enqueue_scripts', array(Login_Form::get_instance(), 'bassdk_enqueue_scripts'));
		add_action('wp_enqueue_scripts', array(Login_Form::get_instance(), 'check_login'));

		add_action('wp_ajax_nopriv_process_basgate_login', array(Authentication::get_instance(), 'ajax_process_basgate_login'));

		add_shortcode('bassdk_login', array(Login_Form::get_instance(), 'bassdk_login_form'));
		// add_action('login_footer', array(Login_Form::get_instance(), 'load_login_footer_js'));

		// add_action('wp_footer', array(Login_Form::get_instance(), 'bassdk_add_modal'));

	}
	/**
	 * Plugin activation hook.
	 * Will also activate the plugin for all sites/blogs if this is a "Network enable."
	 *
	 * @param bool $network_wide Whether the plugin is being activated for the whole network.
	 * @return void
	 */
	public function activate($network_wide)
	{
		global $wpdb;
		$options       = Options::get_instance();
		// // $sync_userdata = Sync_Userdata::get_instance();

		// // If we're in a multisite environment, run the plugin activation for each
		// // site when network enabling.
		// // Note: wp-cli does not use nonces, so we skip the nonce check here to
		// // allow the "wp plugin activate basgate" command.
		// // phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification
		// if (is_multisite() && $network_wide) {

		// 	// Add super admins to the multisite approved list.
		// 	$auth_multisite_settings_access_users_approved               = get_blog_option(get_main_site_id(get_main_network_id()), 'auth_multisite_settings_access_users_approved', array());
		// 	$should_update_auth_multisite_settings_access_users_approved = false;
		// 	foreach (get_super_admins() as $super_admin) {
		// 		$user = get_user_by('login', $super_admin);
		// 		// Skip if user wasn't found (edge case).
		// 		if (empty($user)) {
		// 			continue;
		// 		}
		// 		// // Add to approved list if not there.
		// 		// if (! Helper::in_multi_array($user->user_email, $auth_multisite_settings_access_users_approved)) {
		// 		// 	$approved_user = array(
		// 		// 		'email'      => Helper::lowercase($user->user_email),
		// 		// 		'role'       => is_array($user->roles) && count($user->roles) > 0 ? $user->roles[0] : 'administrator',
		// 		// 		'date_added' => wp_date('M Y', strtotime($user->user_registered)),
		// 		// 		'local_user' => true,
		// 		// 	);
		// 		// 	array_push($auth_multisite_settings_access_users_approved, $approved_user);
		// 		// 	$should_update_auth_multisite_settings_access_users_approved = true;
		// 		// }
		// 	}
		// 	if ($should_update_auth_multisite_settings_access_users_approved) {
		// 		update_blog_option(get_main_site_id(get_main_network_id()), 'auth_multisite_settings_access_users_approved', $auth_multisite_settings_access_users_approved);
		// 	}

		// 	// Run plugin activation on each site in the network.
		// 	$current_blog_id = $wpdb->blogid;
		// 	// phpcs:ignore WordPress.WP.DeprecatedFunctions.wp_get_sitesFound
		// 	$sites = function_exists('get_sites') ? get_sites() : wp_get_sites(array('limit' => PHP_INT_MAX));
		// 	foreach ($sites as $site) {
		// 		$blog_id = function_exists('get_sites') ? $site->blog_id : $site['blog_id'];
		// 		switch_to_blog($blog_id);
		// 		// Set default plugin options and add current users to approved list.
		// 		$options->set_default_options();
		// 		// $sync_userdata->add_wp_users_to_approved_list();
		// 	}
		// 	switch_to_blog($current_blog_id);
		// } else {
		//	// Set default plugin options and add current users to approved list.
		$options->set_default_options();
		//	// $sync_userdata->add_wp_users_to_approved_list();
		// }
	}


	/**
	 * Plugin deactivation.
	 *
	 * @return void
	 */
	public function deactivate()
	{
		// Do nothing. Use uninstall.php instead.
	}


	/**
	 * Load translated strings from *.mo files in /languages.
	 *
	 * Action: plugins_loaded
	 */
	public function load_textdomain()
	{
		load_plugin_textdomain(
			'basgate',
			false,
			basename(dirname(plugin_root())) . '/languages'
		);
	}
}

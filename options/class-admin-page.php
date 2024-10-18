<?php

/**
 * Basgate SDK
 *
 * @license  GPL-2.0+
 * @link     https://github.com/Basgate
 * @package  Basgate SDK
 */

namespace BasgateSDK;

use BasgateSDK\Login_Access;
use BasgateSDK\Helper;


/**
 * Contains functions for creating the Basgate Settings page and adding it to
 * the WordPress Dashboard menu.
 */
class Admin_Page extends Singleton
{

	protected $msg = array();

	private $id;
	private $method_title;
	private $method_description;
	private $icon;
	private $has_fields;
	private $title;
	private $description;
	/**
	 * Contruction function
	 */
	public function __construct()
	{
		// Go wild in here
		$this->id = BasgateConstants::ID;
		$this->method_title = BasgateConstants::METHOD_TITLE;
		$this->method_description = BasgateConstants::METHOD_DESCRIPTION;
		// $getBasgateSetting = get_option('woocommerce_basgate_settings');
		// $invertLogo = isset($getBasgateSetting['invertLogo']) ? $getBasgateSetting['invertLogo'] : "0";
		// if ($invertLogo == 1) {
		$this->icon = esc_url("https://ykbsocial.com/basgate/reportlogo.png");
		// } else {
		//     $this->icon = esc_url("https://ykbsocial.com/basgate/reportlogo.png");
		// }
		$this->has_fields = false;

		$this->title = BasgateConstants::TITLE;

		$this->msg = array('message' => '', 'class' => '');
	}


	/**
	 * Add help documentation to the options page.
	 *
	 * Action: load-settings_page_authorizer > admin_head
	 */
	public function admin_head()
	{
		$screen = get_current_screen();

		print("===== admin_head => screen->id :" . esc_attr($screen->id));
		// Don't print any help items if not on the Basgate Settings page.
		if (empty($screen->id) || ! in_array($screen->id, array('toplevel_page_authorizer-network', 'toplevel_page_authorizer', 'settings_page_authorizer'), true)) {
			return;
		}

		// Add help tab for Access Lists Settings.
		$help_auth_settings_access_lists_content = '
			<p>' . __("<strong>Pending Users</strong>: Pending users are users who have successfully logged in to the site, but who haven't yet been approved (or blocked) by you.", 'bassdk-wp-login') . '</p>
			<p>' . __('<strong>Approved Users</strong>: Approved users have access to the site once they successfully log in.', 'bassdk-wp-login') . '</p>
			<p>' . __('<strong>Blocked Users</strong>: Blocked users will receive an error message when they try to visit the site after authenticating.', 'bassdk-wp-login') . '</p>
			<p>' . __('Users in the <strong>Pending</strong> list appear automatically after a new user tries to log in from the configured external authentication service. You can add users to the <strong>Approved</strong> or <strong>Blocked</strong> lists by typing them in manually, or by clicking the <em>Approve</em> or <em>Block</em> buttons next to a user in the <strong>Pending</strong> list.', 'bassdk-wp-login') . '</p>
		';
		$screen->add_help_tab(
			array(
				'id'      => 'help_auth_settings_access_lists_content',
				'title'   => __('Authentication', 'bassdk-wp-login'),
				'content' => wp_kses_post($help_auth_settings_access_lists_content),
			)
		);
	}


	/**
	 * Add notices to the top of the options page.
	 *
	 * Action: load-settings_page_authorizer > admin_notices
	 *
	 * Description: Check for invalid settings combinations and show a warning message, e.g.:
	 *   if ( cas url inaccessible ) : ?>
	 *     <div class='updated settings-error'><p>Can't reach CAS server.</p></div>
	 *   <?php endif;
	 */
	public function admin_notices()
	{
		// Grab plugin settings.
		$options       = Options::get_instance();
		$auth_settings = $options->get_all(Helper::SINGLE_CONTEXT, 'allow override');
	}


	/**
	 * Add a link to this plugin's settings page from the WordPress Plugins page.
	 * Called from "plugin_action_links" filter in __construct() above.
	 *
	 * Filter: plugin_action_links_authorizer.php
	 *
	 * @param  array $links Admin sidebar links.
	 * @return array        Admin sidebar links with Basgate added.
	 */
	public function plugin_settings_link($links)
	{
		$options      = Options::get_instance();
		$admin_menu   = $options->get('advanced_admin_menu');
		$settings_url = 'settings' === $admin_menu ? admin_url('options-general.php?page=basgate') : admin_url('admin.php?page=basgate');
		array_unshift($links, '<a href="' . $settings_url . '">' . __('Settings', 'bassdk-wp-login') . '</a>');
		return $links;
	}


	/**
	 * Add a link to this plugin's network settings page from the WordPress Plugins page.
	 * Called from "network_admin_plugin_action_links" filter in __construct() above.
	 *
	 * Filter: network_admin_plugin_action_links_authorizer.php
	 *
	 * @param  array $links Network admin sidebar links.
	 * @return array        Network admin sidebar links with Basgate added.
	 */
	// public function network_admin_plugin_settings_link($links)
	// {
	// 	$settings_link = '<a href="admin.php?page=basgate">' . __('Network Settings','bassdk-wp-login') . '</a>';
	// 	array_unshift($links, $settings_link);
	// 	return $links;
	// }


	/**
	 * Create sections and options.
	 *
	 * Action: admin_init
	 */
	public function page_init()
	{
		register_setting(
			'basgate_settings_group',
			BasgateConstants::OPTION_DATA_NAME,
			array(Options::get_instance(), 'sanitize_options')
		);

		add_settings_section(
			'auth_settings_tabs',
			'',
			array(Options::get_instance(), 'print_section_info_tabs'),
			$this->id
		);

		// Create Login Access section.
		add_settings_section(
			'auth_settings_basgate_config',
			'',
			array(Login_Access::get_instance(), 'print_section_info_basgate_config'),
			$this->id
		);

		add_settings_field(
			'bas_description',
			__('Description', 'bassdk-wp-login'),
			array(Login_Access::get_instance(), 'print_text_description'),
			$this->id,
			'auth_settings_basgate_config'
		);
		add_settings_field(
			'bas_environment',
			__('Environment Mode', 'bassdk-wp-login'),
			array(Login_Access::get_instance(), 'print_select_environment_mode'),
			$this->id,
			'auth_settings_basgate_config'
		);
		add_settings_field(
			'bas_application_id',
			__('Application Id', 'bassdk-wp-login'),
			array(Login_Access::get_instance(), 'print_text_application_id'),
			$this->id,
			'auth_settings_basgate_config'
		);
		add_settings_field(
			'bas_merchant_key',
			__('Merchant Key', 'bassdk-wp-login'),
			array(Login_Access::get_instance(), 'print_text_merchant_key'),
			$this->id,
			'auth_settings_basgate_config'
		);
		add_settings_field(
			'bas_client_id',
			__('Client Id', 'bassdk-wp-login'),
			array(Login_Access::get_instance(), 'print_text_client_id'),
			$this->id,
			'auth_settings_basgate_config'
		);
		add_settings_field(
			'bas_client_secret',
			__('Client Secret', 'bassdk-wp-login'),
			array(Login_Access::get_instance(), 'print_text_client_secret'),
			$this->id,
			'auth_settings_basgate_config'
		);
		add_settings_field(
			'enabled',
			__('Enable/Disable', 'bassdk-wp-login'),
			array(Login_Access::get_instance(), 'print_checkbox_enabled'),
			$this->id,
			'auth_settings_basgate_config'
		);

		// add_settings_field(
		// 	'advanced_disable_wp_login',
		// 	__('Disable Default login', 'bassdk-wp-login'),
		// 	array(Login_Access::get_instance(), 'print_checkbox_disable_wp_login'),
		// 	$this->id,
		// 	'auth_settings_basgate_config'
		// );
	}


	/**
	 * Output the HTML for the options page.
	 */
	public function create_admin_page()
	{
?>
		<div class="wrap">
			<h2><?php esc_html_e('Basgate Settings', 'bassdk-wp-login'); ?></h2>
			<form method="post" action="options.php" autocomplete="off">
				<?php
				// This prints out all hidden settings fields.
				settings_fields('basgate_settings_group');
				// This prints out all the sections.
				do_settings_sections($this->id);
				submit_button();
				?>
			</form>
		</div>
		<div>
			<?php
			$this->bassdk_admin_footer();
			?>
		</div>
<?php
	}

	public function bassdk_admin_footer()
	{

		$curl_version = Helper::getcURLversion();
		// $last_updated = date("d F Y", strtotime(BasgateConstants::LAST_UPDATED)) . ' - ' . Helper::get_plugin_data()['version'];
		$last_updated = Helper::get_plugin_data()['version'];
		// eslint-disable-next-line
		$wooVersion = defined("WOOCOMMERCE_VERSION") ? WOOCOMMERCE_VERSION : "N/A";

		$footer_text = '<div style="text-align: center;"><hr/>';
		$footer_text .= '<strong>' . __('PHP Version') . '</strong> ' . PHP_VERSION . ' | ';
		$footer_text .= '<strong>' . __('cURL Version') . '</strong> ' . $curl_version . ' | ';
		$footer_text .= '<strong>' . __('Wordpress Version') . '</strong> ' . get_bloginfo('version') . ' | ';
		$footer_text .= '<strong>' . __('WooCommerce Version') . '</strong> ' . $wooVersion . ' | ';
		$footer_text .= '<strong>' . __('SDK Version') . '</strong> ' . $last_updated . ' | ';
		$footer_text .= '<a href="' . esc_url(BasgateConstants::PLUGIN_DOC_URL) . '" target="_blank">Developer Docs</a>';

		$footer_text .= '</div>';

		echo wp_kses($footer_text, Helper::$allowed_html);
	}

	/**
	 * Create the options page under Dashboard > Settings.
	 *
	 * Action: admin_menu
	 */
	public function add_plugin_page()
	{
		// $options    = Options::get_instance();
		// $admin_menu = $options->get('advanced_admin_menu');
		// if ('settings' === $admin_menu) {
		// 	// @see http://codex.wordpress.org/Function_Reference/add_options_page
		// 	add_options_page(
		// 		'Basgate',
		// 		'Basgate',
		// 		'create_users',
		// 		$this->id,
		// 		array(self::get_instance(), 'create_admin_page')
		// 	);
		// } else {
		// @see http://codex.wordpress.org/Function_Reference/add_menu_page
		add_menu_page(
			'Basgate',
			'Basgate',
			'create_users',
			$this->id,
			array(self::get_instance(), 'create_admin_page'),
			plugins_url('images/bassdk-logo.svg', \BasgateSDK\plugin_root()),
			'99.0018465' // position (decimal is to make overlap with other plugins less likely).
		);
		// }
	}
}

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
 * Contains functions for rendering the Access Lists tab in Basgate Login SDK Settings.
 */
class Options extends Singleton
{

	/**
	 * Retrieves a specific plugin option from db. Multisite enabled.
	 *
	 * @param  string $option        Option name.
	 * @param  string $admin_mode    Helper::NETWORK_CONTEXT will retrieve the multisite value.
	 * @param  string $override_mode 'allow override' will retrieve the multisite value if it exists.
	 * @param  string $print_mode    'print overlay' will output overlay that hides this option on the settings page.
	 * @return mixed                 Option value, or null on failure.
	 */
	public function get($option, $admin_mode = 'single_admin', $override_mode = 'no override', $print_mode = 'no overlay')
	{
		// Special case for user lists (they are saved seperately to prevent concurrency issues).
		if (in_array($option, array('access_users_pending', 'access_users_approved', 'access_users_blocked'), true)) {
			$list = 'multisite_admin' === $admin_mode ? array() : get_option('auth_settings_' . $option, array());
			if (is_multisite() && 'multisite_admin' === $admin_mode) {
				$list = get_blog_option(get_main_site_id(get_main_network_id()), 'auth_multisite_settings_' . $option, array());
			}
			return $list;
		}

		// Get all plugin options.
		$auth_settings = $this->get_all($admin_mode, $override_mode);

		// Get multisite options (for checking if multisite override is prevented).
		$auth_multisite_settings = is_multisite() ? get_blog_option(get_main_site_id(get_main_network_id()), 'auth_multisite_settings', array()) : array();

		// Set option to null if it wasn't found.
		if (! array_key_exists($option, $auth_settings)) {
			return null;
		}

		// If requested and appropriate, print the overlay hiding the
		// single site option that is overridden by a multisite option.
		if (
			'multisite_admin' !== $admin_mode &&
			'allow override' === $override_mode &&
			'print overlay' === $print_mode &&
			array_key_exists('multisite_override', $auth_settings) &&
			'1' === $auth_settings['multisite_override'] &&
			(
				! array_key_exists('advanced_override_multisite', $auth_settings) ||
				1 !== intval($auth_settings['advanced_override_multisite']) ||
				! empty($auth_multisite_settings['prevent_override_multisite'])
			)
		) {
			// Get original plugin options (not overridden value). We'll
			// show this old value behind the disabled overlay.
			// $auth_settings = $this->get_all( $admin_mode, 'no override' );
			// (This feature is disabled).
			//
			$name = "auth_settings[$option]";
			$id   = "auth_settings_$option";
			// Get category of option so we can link directly to the appropriate tab
			// in multisite options (most options are on the External Service tab;
			// only access_who_can_login and access_who_can_view are on the Access
			// Lists tab; all options on the Advanced tab start with "advanced_").
			// $tab = '&tab=external';
			// if ('access_who_can_login' === $option || 'access_who_can_view' === $option) {
			// 	$tab = '&tab=access_lists';
			// } elseif (0 === strpos($option, 'advanced_')) {
			// 	$tab = '&tab=advanced';
			// }
?>
			<div id="overlay-hide-auth_settings_<?php echo esc_attr($option); ?>" class="auth_multisite_override_overlay">
				<span class="overlay-note">
					<?php esc_html_e('This setting is overridden by a', 'basgate'); ?> <a href="<?php echo esc_attr(network_admin_url('admin.php?page=basgate' . $tab)); ?>"><?php esc_html_e('multisite option', 'basgate'); ?></a>.
				</span>
			</div>
		<?php
		}

		// If we're getting an option in a site that has overridden the multisite
		// override (and is not prevented from doing so), make sure we are returning
		// the option value from that site (not the multisite value).
		if (
			array_key_exists('advanced_override_multisite', $auth_settings) &&
			1 === intval($auth_settings['advanced_override_multisite']) &&
			empty($auth_multisite_settings['prevent_override_multisite'])
		) {
			$auth_settings = $this->get_all($admin_mode, 'no override');
		}

		// Set option to null if it wasn't found.
		if (! array_key_exists($option, $auth_settings)) {
			return null;
		}

		return $auth_settings[$option];
	}

	/**
	 * Retrieves all plugin options from db. Multisite enabled.
	 *
	 * @param  string $admin_mode    Helper::NETWORK_CONTEXT will retrieve the multisite value.
	 * @param  string $override_mode 'allow override' will retrieve the multisite value if it exists.
	 * @return mixed                 Option value, or null on failure.
	 */
	public function get_all($admin_mode = 'single_admin', $override_mode = 'no override')
	{
		// Grab plugin settings (skip if in Helper::NETWORK_CONTEXT mode).
		// $auth_settings = 'multisite_admin' === $admin_mode ? array() : get_option(BasgateConstants::OPTION_DATA_NAME);
		$auth_settings =  get_option(BasgateConstants::OPTION_DATA_NAME);

		// Initialize to default values if the plugin option doesn't exist.
		if (false === $auth_settings) {
			$auth_settings = $this->set_default_options();
		}

		return $auth_settings;
	}


	/**
	 * Set meaningful defaults for the plugin options.
	 *
	 * Note: This function is called on plugin activation.
	 *
	 * @param array $args {
	 *     Optional.
	 *
	 *     @type bool $set_multisite_options  Whether to also set the default
	 *                                        multisite options, if in multisite.
	 *                                        Defaults to true.
	 * }
	 */
	public function set_default_options($args = array())
	{
		// global $wp_roles;

		// // Set default args.
		// $defaults = array(
		// 	'set_multisite_options' => true,
		// );
		// $args     = wp_parse_args($args, $defaults);

		$auth_settings = get_option(BasgateConstants::OPTION_DATA_NAME);
		if (false === $auth_settings) {
			$auth_settings = array();
		}

		// // External Service Defaults.
		// if (! array_key_exists('access_default_role', $auth_settings)) {
		// 	// Set default role to 'subscriber'
		// 	$auth_settings['access_default_role'] = 'subscriber';
		// 	if (! empty($wp_roles)) {
		// 		$all_roles      = $wp_roles->roles;
		// 	}
		// }

		if (! array_key_exists('bas_description', $auth_settings)) {
			$auth_settings['bas_description'] = '';
		}
		if (! array_key_exists('bas_environment', $auth_settings)) {
			$auth_settings['bas_environment'] = '0';
		}
		if (! array_key_exists('bas_application_id', $auth_settings)) {
			$auth_settings['bas_application_id'] = '';
		}
		if (! array_key_exists('bas_merchant_key', $auth_settings)) {
			$auth_settings['bas_merchant_key'] = '';
		}
		if (! array_key_exists('bas_client_id', $auth_settings)) {
			$auth_settings['bas_client_id'] = '';
		}
		if (! array_key_exists('bas_client_secret', $auth_settings)) {
			$auth_settings['bas_client_secret'] = '';
		}
		if (! array_key_exists('enabled', $auth_settings)) {
			$auth_settings['enabled'] = 'yes';
		}
		if (! array_key_exists('advanced_disable_wp_login', $auth_settings)) {
			$auth_settings['advanced_disable_wp_login'] = '0';
		}


		// Save default options to database.
		update_option(BasgateConstants::OPTION_DATA_NAME, $auth_settings);

		return $auth_settings;
	}


	/**
	 * List sanitizer.
	 *
	 * @param  array $user_list Array of users to sanitize.
	 * @return array            Array of sanitized users.
	 */
	public function sanitize_user_list($user_list)
	{
		// If it's not a list, make it so.
		if (! is_array($user_list)) {
			$user_list = array();
		}
		foreach ($user_list as $key => $user_info) {
			if (strlen($user_info['email']) < 1) {
				// Make sure there are no empty entries in the list.
				unset($user_list[$key]);
			}
		}
		return $user_list;
	}


	/**
	 * Settings sanitizer callback.
	 *
	 * @param  array $auth_settings Basgate settings array.
	 * @return array                Sanitized Basgate settings array.
	 */
	public function sanitize_options($auth_settings)
	{

		// Sanitize Enable enabled Logins (checkbox: value can only be '1' or empty string).
		$auth_settings['enabled'] = array_key_exists('enabled', $auth_settings) && $auth_settings['enabled'] === 'yes'  ? 'yes' : 'no';


		// // Sanitize Disable WordPress logins (checkbox: value can only be '1' or empty string).
		// $auth_settings['advanced_disable_wp_login'] = array_key_exists('advanced_disable_wp_login', $auth_settings) && strlen($auth_settings['advanced_disable_wp_login']) > 0 ? '1' : '';

		return $auth_settings;
	}


	/**
	 * Sanitizes an array of user update commands coming from the AJAX handler in Basgate Settings.
	 *
	 * Example $users array:
	 * array(
	 *   array(
	 *     edit_action: 'add' or 'remove' or 'change_role',
	 *     email: 'johndoe@example.com',
	 *     role: 'subscriber',
	 *     date_added: 'Jun 2014',
	 *     local_user: 'true' or 'false',
	 *     multisite_user: 'true' or 'false',
	 *   ),
	 *   ...
	 * )
	 *
	 * @param  array $users Users to edit.
	 * @param  array $args  Options (e.g., 'allow_wildcard_email' => true).
	 * @return array        Sanitized users to edit.
	 */
	public function sanitize_update_auth_users($users = array(), $args = array())
	{
		if (! is_array($users)) {
			$users = array();
		}
		if (isset($args['allow_wildcard_email']) && $args['allow_wildcard_email']) {
			$users = array_map(array($this, 'sanitize_update_auth_user_allow_wildcard_email'), $users);
		} else {
			$users = array_map(array($this, 'sanitize_update_auth_user'), $users);
		}

		// Remove any entries that failed email address validation.
		$users = array_filter($users, array($this, 'remove_invalid_auth_users'));

		return $users;
	}


	/**
	 * Callback for array_map in sanitize_update_auth_users().
	 *
	 * @param  array $user User data to sanitize.
	 * @return array       Sanitized user data.
	 */
	public function sanitize_update_auth_user($user)
	{
		if (array_key_exists('edit_action', $user)) {
			$user['edit_action'] = sanitize_text_field($user['edit_action']);
		}
		if (isset($user['email'])) {
			$user['email'] = sanitize_email($user['email']);
		}
		if (isset($user['role'])) {
			$user['role'] = sanitize_text_field($user['role']);
		}
		if (isset($user['date_added'])) {
			$user['date_added'] = sanitize_text_field($user['date_added']);
		}
		if (isset($user['local_user'])) {
			$user['local_user'] = 'true' === $user['local_user'] ? 'true' : 'false';
		}
		if (isset($user['multisite_user'])) {
			$user['multisite_user'] = 'true' === $user['multisite_user'] ? 'true' : 'false';
		}

		return $user;
	}


	/**
	 * Settings print callback.
	 *
	 * @param  string $args Args (e.g., multisite admin mode).
	 * @return void
	 */
	public function print_section_info_tabs($args = '')
	{
		if ('multisite_admin' === $this->get_context($args)) :
		?>
			<h2 class="nav-tab-wrapper">
				<a class="nav-tab nav-tab-access_lists nav-tab-active" href="javascript:chooseTab('access_lists' );"><?php esc_html_e('Authentication', 'basgate'); ?></a>
			</h2>
		<?php else : ?>
			<h2 class="nav-tab-wrapper">
				<a class="nav-tab nav-tab-access_lists nav-tab-active" href="javascript:chooseTab('access_lists' );"><?php esc_html_e('Authentication', 'basgate'); ?></a>
			</h2>
<?php
		endif;
	}

	public static function get_context($args)
	{
		if (
			is_array($args) &&
			array_key_exists('context', $args) &&
			'multisite_admin' === $args['context']
		) {
			return 'multisite_admin';
		} else {
			return 'single_admin';
		}
	}


	/**
	 * This array filter will remove any users who failed email address validation
	 * (which would set their email to a blank string).
	 *
	 * @param  array $user User data to check for a valid email.
	 * @return bool  Whether to filter out the user.
	 */
	protected function remove_invalid_auth_users($user)
	{
		return isset($user['email']) && strlen($user['email']) > 0;
	}


	/**
	 * Callback for array_map in sanitize_update_auth_users().
	 *
	 * @param  array $user User data to sanitize.
	 * @return array       Sanitized user data.
	 */
	protected function sanitize_update_auth_user_allow_wildcard_email($user)
	{
		if (array_key_exists('edit_action', $user)) {
			$user['edit_action'] = sanitize_text_field($user['edit_action']);
		}
		if (isset($user['email'])) {
			if (strpos($user['email'], '@') === 0) {
				$user['email'] = sanitize_text_field($user['email']);
			} else {
				$user['email'] = sanitize_email($user['email']);
			}
		}
		if (isset($user['role'])) {
			$user['role'] = sanitize_text_field($user['role']);
		}
		if (isset($user['date_added'])) {
			$user['date_added'] = sanitize_text_field($user['date_added']);
		}
		if (isset($user['local_user'])) {
			$user['local_user'] = 'true' === $user['local_user'] ? 'true' : 'false';
		}
		if (isset($user['multisite_user'])) {
			$user['multisite_user'] = 'true' === $user['multisite_user'] ? 'true' : 'false';
		}

		return $user;
	}
}

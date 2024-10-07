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

		// Don't print any help items if not on the Basgate Settings page.
		if (empty($screen->id) || ! in_array($screen->id, array('toplevel_page_authorizer-network', 'toplevel_page_authorizer', 'settings_page_authorizer'), true)) {
			return;
		}

		// Add help tab for Access Lists Settings.
		$help_auth_settings_access_lists_content = '
			<p>' . __("<strong>Pending Users</strong>: Pending users are users who have successfully logged in to the site, but who haven't yet been approved (or blocked) by you.", 'basgate') . '</p>
			<p>' . __('<strong>Approved Users</strong>: Approved users have access to the site once they successfully log in.', 'basgate') . '</p>
			<p>' . __('<strong>Blocked Users</strong>: Blocked users will receive an error message when they try to visit the site after authenticating.', 'basgate') . '</p>
			<p>' . __('Users in the <strong>Pending</strong> list appear automatically after a new user tries to log in from the configured external authentication service. You can add users to the <strong>Approved</strong> or <strong>Blocked</strong> lists by typing them in manually, or by clicking the <em>Approve</em> or <em>Block</em> buttons next to a user in the <strong>Pending</strong> list.', 'basgate') . '</p>
		';
		$screen->add_help_tab(
			array(
				'id'      => 'help_auth_settings_access_lists_content',
				'title'   => __('Access Lists', 'basgate'),
				'content' => wp_kses_post($help_auth_settings_access_lists_content),
			)
		);

		// // Add help tab for Login Access Settings.
		// $help_auth_settings_access_login_content = '
		// 	<p>' . __("<strong>Who can log in to the site?</strong>: Choose the level of access restriction you'd like to use on your site here. You can leave the site open to anyone with a WordPress account or an account on an external service like Google, CAS, or LDAP, or restrict it to WordPress users and only the external users that you specify via the <em>Access Lists</em>.", 'basgate') . '</p>
		// 	<p>' . __("<strong>Which role should receive email notifications about pending users?</strong>: If you've restricted access to <strong>approved users</strong>, you can determine which WordPress users will receive a notification email everytime a new external user successfully logs in and is added to the pending list. All users of the specified role will receive an email, and the external user will get a message (specified below) telling them their access is pending approval.", 'basgate') . '</p>
		// 	<p>' . __('<strong>What message should pending users see after attempting to log in?</strong>: Here you can specify the exact message a new external user will see once they try to log in to the site for the first time.', 'basgate') . '</p>
		// ';
		// $screen->add_help_tab(
		// 	array(
		// 		'id'      => 'help_auth_settings_access_login_content',
		// 		'title'   => __('Login Access', 'basgate'),
		// 		'content' => wp_kses_post($help_auth_settings_access_login_content),
		// 	)
		// );

		// // Add help tab for Public Access Settings.
		// $help_auth_settings_access_public_content = '
		// 	<p>' . __("<strong>Who can view the site?</strong>: You can restrict the site's visibility by only allowing logged in users to see pages. If you do so, you can customize the specifics about the site's privacy using the settings below.", 'basgate') . '</p>
		// 	<p>' . __("<strong>What pages (if any) should be available to everyone?</strong>: If you'd like to declare certain pages on your site as always public (such as the course syllabus, introduction, or calendar), specify those pages here. These pages will always be available no matter what access restrictions exist.", 'basgate') . '</p>
		// 	<p>' . __('<strong>What happens to people without access when they visit a <em>private</em> page?</strong>: Choose the response anonymous users receive when visiting the site. You can choose between immediately taking them to the <strong>login screen</strong>, or simply showing them a <strong>message</strong>.', 'basgate') . '</p>
		// 	<p>' . __('<strong>What happens to people without access when they visit a <em>public</em> page?</strong>: Choose the response anonymous users receive when visiting a page on the site marked as public. You can choose between showing them the page without any message, or showing them a the page with a message above the content.', 'basgate') . '</p>
		// 	<p>' . __('<strong>What message should people without access see?</strong>: If you chose to show new users a <strong>message</strong> above, type that message here.', 'basgate') . '</p>
		// ';
		// $screen->add_help_tab(
		// 	array(
		// 		'id'      => 'help_auth_settings_access_public_content',
		// 		'title'   => __('Public Access', 'basgate'),
		// 		'content' => wp_kses_post($help_auth_settings_access_public_content),
		// 	)
		// );

		// // Add help tab for External Service (CAS, LDAP) Settings.
		// $help_auth_settings_external_content = '
		// 	<p>' . __("<strong>Type of external service to authenticate against</strong>: Choose which authentication service type you will be using. You'll have to fill out different fields below depending on which service you choose.", 'basgate') . '</p>
		// 	<p>' . __('<strong>Enable OAuth2 Logins</strong>: Choose if you want to allow users to log in with one of the supported OAuth2 providers. You will need to enter your API Client ID and Secret to enable these logins.', 'basgate') . '</p>
		// 	<p>' . __('<strong>Enable Google Logins</strong>: Choose if you want to allow users to log in with their Google Account credentials. You will need to enter your API Client ID and Secret to enable Google Logins.', 'basgate') . '</p>
		// 	<p>' . __('<strong>Enable CAS Logins</strong>: Choose if you want to allow users to log in with via CAS (Central Authentication Service). You will need to enter details about your CAS server (host, port, and path) to enable CAS Logins.', 'basgate') . '</p>
		// 	<p>' . __('<strong>Enable LDAP Logins</strong>: Choose if you want to allow users to log in with their LDAP (Lightweight Directory Access Protocol) credentials. You will need to enter details about your LDAP server (host, port, search base, uid attribute, directory user, directory user password, and whether to use STARTTLS) to enable LDAP Logins.', 'basgate') . '</p>
		// 	<p>' . __('<strong>Default role for new CAS users</strong>: Specify which role new external users will get by default. Be sure to choose a role with limited permissions!', 'basgate') . '</p>
		// 	<p><strong><em>' . __('If you enable OAuth2 logins:', 'basgate') . '</em></strong></p>
		// 	<ul>
		// 		<li>' . __('<strong>Client ID</strong>: You can generate this ID following the instructions for your specific provider.', 'basgate') . '<br>' . __("Note: for increased security, you can leave this field blank and instead define this value either in wp-config.php via <code>define( 'AUTHORIZER_OAUTH2_CLIENT_ID', '...' );</code>, or you may fetch it from an external service like AWS Secrets Manager by hooking into the <code>authorizer_oauth2_client_id</code> filter. This will prevent it from being stored in plaintext in the WordPress database.", 'basgate') . '</li>
		// 		<li>' . __('<strong>Client Secret</strong>: You can generate this secret by following the instructions for your specific provider.', 'basgate') . '<br>' . __("Note: for increased security, you can leave this field blank and instead define this value either in wp-config.php via <code>define( 'AUTHORIZER_OAUTH2_CLIENT_SECRET', '...' );</code>, or you may fetch it from an external service like AWS Secrets Manager by hooking into the <code>authorizer_oauth2_client_secret</code> filter. This will prevent it from being stored in plaintext in the WordPress database.", 'basgate') . '</li>
		// 		<li>' . __('<strong>Authorization URL</strong>: For the generic OAuth2 provider, you will need to specify the 3 endpoints required for the oauth2 authentication flow. This is the first: the endpoint first contacted to initiate the authentication.', 'basgate') . '</li>
		// 		<li>' . __('<strong>Access Token URL</strong>: For the generic OAuth2 provider, you will need to specify the 3 endpoints required for the oauth2 authentication flow. This is the second: the endpoint that is contacted after initiation to retrieve an access token for the user that just authenticated.', 'basgate') . '</li>
		// 		<li>' . __('<strong>Resource Owner URL</strong>: For the generic OAuth2 provider, you will need to specify the 3 endpoints required for the oauth2 authentication flow. This is the third: the endpoint that is contacted after successfully receiving an authentication token to retrieve details on the user that just authenticated.', 'basgate') . '</li>
		// 	</ul>
		// 	<p><strong><em>' . __('If you enable Google logins:', 'basgate') . '</em></strong></p>
		// 	<ul>
		// 		<li>' . __("<strong>Google Client ID</strong>: You can generate this ID by creating a new Project in the <a href='https://cloud.google.com/console'>Google Developers Console</a>. A Client ID typically looks something like this: 1234567890123-kdjr85yt6vjr6d8g7dhr8g7d6durjf7g.apps.googleusercontent.com", 'basgate') . '<br>' . __("Note: for increased security, you can leave this field blank and instead define this value either in wp-config.php via <code>define( 'AUTHORIZER_GOOGLE_CLIENT_ID', '...' );</code>, or you may fetch it from an external service like AWS Secrets Manager by hooking into the <code>authorizer_google_client_id</code> filter. This will prevent it from being stored in plaintext in the WordPress database.", 'basgate') . '</li>
		// 		<li>' . __("<strong>Google Client Secret</strong>: You can generate this secret by creating a new Project in the <a href='https://cloud.google.com/console'>Google Developers Console</a>. A Client Secret typically looks something like this: sDNgX5_pr_5bly-frKmvp8jT", 'basgate') . '<br>' . __("Note: for increased security, you can leave this field blank and instead define this value either in wp-config.php via <code>define( 'AUTHORIZER_GOOGLE_CLIENT_SECRET', '...' );</code>, or you may fetch it from an external service like AWS Secrets Manager by hooking into the <code>authorizer_google_client_secret</code> filter. This will prevent it from being stored in plaintext in the WordPress database.", 'basgate') . '</li>
		// 	</ul>
		// 	<p><strong><em>' . __('If you enable CAS logins:', 'basgate') . '</em></strong></p>
		// 	<ul>
		// 		<li>' . __('<strong>CAS server hostname</strong>: Enter the hostname of the CAS server you authenticate against (e.g., authn.example.edu).', 'basgate') . '</li>
		// 		<li>' . __('<strong>CAS server port</strong>: Enter the port on the CAS server to connect to (e.g., 443).', 'basgate') . '</li>
		// 		<li>' . __('<strong>CAS server path/context</strong>: Enter the path to the login endpoint on the CAS server (e.g., /cas).', 'basgate') . '</li>
		// 		<li>' . __('<strong>CAS server method</strong>: Select the method to use when setting the CAS config (e.g.,"client" or "proxy")', 'basgate') . '</li>
		// 		<li>' . __("<strong>CAS attribute containing first name</strong>: Enter the CAS attribute that has the user's first name. When this user first logs in, their WordPress account will have their first name retrieved from CAS and added to their WordPress profile.", 'basgate') . '</li>
		// 		<li>' . __("<strong>CAS attribute containing last name</strong>: Enter the CAS attribute that has the user's last name. When this user first logs in, their WordPress account will have their last name retrieved from CAS and added to their WordPress profile.", 'basgate') . '</li>
		// 		<li>' . __('<strong>CAS attribute update</strong>: Select whether the first and last names retrieved from CAS should overwrite any value the user has entered in the first and last name fields in their WordPress profile. If this is not set, this only happens the first time they log in.', 'basgate') . '</li>
		// 	</ul>
		// 	<p><strong><em>' . __('If you enable LDAP logins:', 'basgate') . '</em></strong></p>
		// 	<ul>
		// 		<li>' . __('<strong>LDAP Host</strong>: Enter the URL of the LDAP server you authenticate against.', 'basgate') . '</li>
		// 		<li>' . __('<strong>LDAP Port</strong>: Enter the port number that the LDAP server listens on.', 'basgate') . '</li>
		// 		<li>' . __('<strong>LDAP Search Base</strong>: Enter the LDAP string that represents the search base, e.g., ou=people,dc=example,dc=edu', 'basgate') . '</li>
		// 		<li>' . __('<strong>LDAP Search Filter</strong>: Enter the optional LDAP string that represents the search filter, e.g., (memberOf=cn=wp_users,ou=people,dc=example,dc=edu)', 'basgate') . '</li>
		// 		<li>' . __('<strong>LDAP attribute containing username</strong>: Enter the name of the LDAP attribute that contains the usernames used by those attempting to log in. The plugin will search on this attribute to find the cn to bind against for login attempts.', 'basgate') . '</li>
		// 		<li>' . __('<strong>LDAP Directory User</strong>: Enter the name of the LDAP user that has permissions to browse the directory.', 'basgate') . '<br>' . __("Note: for increased security, you can leave this field blank and instead define this value either in wp-config.php via <code>define( 'AUTHORIZER_LDAP_USER', '...' );</code>, or you may fetch it from an external service like AWS Secrets Manager by hooking into the <code>authorizer_ldap_user</code> filter. This will prevent it from being stored in plaintext in the WordPress database.", 'basgate') . '</li>
		// 		<li>' . __('<strong>LDAP Directory User Password</strong>: Enter the password for the LDAP user that has permission to browse the directory.', 'basgate') . '<br>' . __("Note: for increased security, you can leave this field blank and instead define this value either in wp-config.php via <code>define( 'AUTHORIZER_LDAP_PASSWORD', '...' );</code>, or you may fetch it from an external service like AWS Secrets Manager by hooking into the <code>authorizer_ldap_password</code> filter. This will prevent it from being stored in the WordPress database.", 'basgate') . '</li>
		// 		<li>' . __('<strong>Use STARTTLS</strong>: Select whether unencrypted communication with the LDAP server should be upgraded to a TLS-secured connection using STARTTLS.', 'basgate') . '</li>
		// 		<li>' . __("<strong>Custom lost password URL</strong>: The WordPress login page contains a link to recover a lost password. If you have external users who shouldn't change the password on their WordPress account, point them to the appropriate location to change the password on their external authentication service here.", 'basgate') . '</li>
		// 		<li>' . __("<strong>LDAP attribute containing first name</strong>: Enter the LDAP attribute that has the user's first name. When this user first logs in, their WordPress account will have their first name retrieved from LDAP and added to their WordPress profile.", 'basgate') . '</li>
		// 		<li>' . __("<strong>LDAP attribute containing last name</strong>: Enter the LDAP attribute that has the user's last name. When this user first logs in, their WordPress account will have their last name retrieved from LDAP and added to their WordPress profile.", 'basgate') . '</li>
		// 		<li>' . __('<strong>LDAP attribute update</strong>: Select whether the first and last names retrieved from LDAP should overwrite any value the user has entered in the first and last name fields in their WordPress profile. If this is not set, this only happens the first time they log in.', 'basgate') . '</li>
		// 	</ul>
		// ';
		// $screen->add_help_tab(
		// 	array(
		// 		'id'      => 'help_auth_settings_external_content',
		// 		'title'   => __('External Service', 'basgate'),
		// 		'content' => wp_kses_post($help_auth_settings_external_content),
		// 	)
		// );

		// // Add help tab for Advanced Settings.
		// $help_auth_settings_advanced_content = '
		// 	<p>' . __('<strong>Limit invalid login attempts</strong>: Choose how soon (and for how long) to restrict access to individuals (or bots) making repeated invalid login attempts. You may set a shorter delay first, and then a longer delay after repeated invalid attempts; you may also set how much time must pass before the delays will be reset to normal.', 'basgate') . '</p>
		// 	<p>' . __('<strong>Hide WordPress Logins</strong>: If you want to hide the WordPress username and password fields and the Log In button on the wp-login screen, enable this option. Note: You can always access the WordPress logins by adding external=wordpress to the wp-login URL, like so:', 'basgate') . ' <a href="' . wp_login_url() . '?external=wordpress" target="_blank">' . wp_login_url() . '?external=wordpress</a>.</p>
		// 	<p>' . __('<strong>Disable WordPress Logins</strong>: If you want to prevent users from logging in with their WordPress passwords and instead only allow logins from external services, enable this option. Note: enabling this will also hide WordPress logins unless the LDAP external service is enabled.', 'basgate') . '</p>
		// 	<p>' . __("<strong>Custom WordPress login branding</strong>: If you'd like to use custom branding on the WordPress login page, select that here. You will need to use the `authorizer_add_branding_option` filter in your theme to add it. You can see an example theme that implements this filter in the plugin directory under sample-theme-add-branding.", 'basgate') . '</p>
		// ';
		// $screen->add_help_tab(
		// 	array(
		// 		'id'      => 'help_auth_settings_advanced_content',
		// 		'title'   => __('Advanced', 'basgate'),
		// 		'content' => wp_kses_post($help_auth_settings_advanced_content),
		// 	)
		// );
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
	 * Show custom admin notice.
	 *
	 * Note: currently unused, but if anywhere we:
	 *   add_option( 'auth_settings_advanced_admin_notice, 'Your message.' );
	 * It will display and then delete that message on the admin dashboard.
	 *
	 * Filter: admin_notices
	 * filter: network_admin_notices
	 */
	public function show_advanced_admin_notice()
	{
		$notice = get_option('auth_settings_advanced_admin_notice');
		delete_option('auth_settings_advanced_admin_notice');

		if ($notice && strlen($notice) > 0) {
?>
			<div class="error">
				<p><?php echo wp_kses($notice, Helper::$allowed_html); ?></p>
			</div>
		<?php
		}
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
		array_unshift($links, '<a href="' . $settings_url . '">' . __('Settings', 'basgate') . '</a>');
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
	public function network_admin_plugin_settings_link($links)
	{
		$settings_link = '<a href="admin.php?page=basgate">' . __('Network Settings', 'basgate') . '</a>';
		array_unshift($links, $settings_link);
		return $links;
	}


	/**
	 * Create sections and options.
	 *
	 * Action: admin_init
	 */
	public function page_init()
	{
		/**
		 * Create one setting that holds all the options (array).
		 *
		 * @see http://codex.wordpress.org/Function_Reference/register_setting
		 * @see http://codex.wordpress.org/Function_Reference/add_settings_section
		 * @see http://codex.wordpress.org/Function_Reference/add_settings_field
		 */
		register_setting(
			'auth_settings_group',
			'auth_settings',
			array(Options::get_instance(), 'sanitize_options')
		);

		add_settings_section(
			'auth_settings_tabs',
			'',
			array(Options::get_instance(), 'print_section_info_tabs'),
			'basgate'
		);

		// Create Login Access section.
		add_settings_section(
			'auth_settings_basgate_config',
			'',
			array(Login_Access::get_instance(), 'print_section_info_basgate_config'),
			'basgate'
		);

		add_settings_field(
			'description',
			__('Description',  $this->id),
			array(Login_Access::get_instance(), 'print_text_description'),
			'basgate',
			'auth_settings_basgate_config'
		);
		// add_settings_field(
		// 	'auth_settings_access_who_can_login',
		// 	__('Who can log into the site????', 'basgate'),
		// 	array(Login_Access::get_instance(), 'print_radio_auth_access_who_can_login'),
		// 	'basgate',
		// 	'auth_settings_basgate_config'
		// );
		// add_settings_field(
		// 	'auth_settings_access_role_receive_pending_emails',
		// 	__('Which role should receive email notifications about pending users?', 'basgate'),
		// 	array(Login_Access::get_instance(), 'print_select_auth_access_role_receive_pending_emails'),
		// 	'basgate',
		// 	'auth_settings_basgate_config'
		// );
		add_settings_field(
			'environment',
			__('Environment Mode', $this->id),
			array(Login_Access::get_instance(), 'print_select_environment_mode'),
			$this->id,
			'auth_settings_basgate_config'
		);
		add_settings_field(
			'auth_settings_access_pending_redirect_to_message',
			__('What message should pending users see after attempting to log in?',  $this->id),
			array(Login_Access::get_instance(), 'print_wysiwyg_auth_access_pending_redirect_to_message'),
			$this->id,
			'auth_settings_basgate_config'
		);
		add_settings_field(
			'auth_settings_access_blocked_redirect_to_message',
			__('What message should blocked users see after attempting to log in?',  $this->id),
			array(Login_Access::get_instance(), 'print_wysiwyg_auth_access_blocked_redirect_to_message'),
			$this->id,
			'auth_settings_basgate_config'
		);
		add_settings_field(
			'auth_settings_access_should_email_approved_users',
			__('Send welcome email to new approved users?', 'basgate'),
			array(Login_Access::get_instance(), 'print_checkbox_auth_access_should_email_approved_users'),
			'basgate',
			'auth_settings_basgate_config'
		);
		add_settings_field(
			'auth_settings_access_email_approved_users_subject',
			__('Welcome email subject', 'basgate'),
			array(Login_Access::get_instance(), 'print_text_auth_access_email_approved_users_subject'),
			'basgate',
			'auth_settings_basgate_config'
		);
		add_settings_field(
			'auth_settings_access_email_approved_users_body',
			__('Welcome email body', 'basgate'),
			array(Login_Access::get_instance(), 'print_wysiwyg_auth_access_email_approved_users_body'),
			'basgate',
			'auth_settings_basgate_config'
		);
	}


	/**
	 * Output the HTML for the options page.
	 */
	public function create_admin_page()
	{
		?>
		<div class="wrap">
			<h2><?php esc_html_e('Basgate Settings', 'basgate'); ?></h2>
			<form method="post" action="options.php" autocomplete="off">
				<?php
				// This prints out all hidden settings fields.
				settings_fields('auth_settings_group');
				// This prints out all the sections.
				do_settings_sections('basgate');
				submit_button();
				?>
			</form>
		</div>
	<?php
	}


	/**
	 * Output the HTML for the options page.
	 */
	public function create_network_admin_page()
	{
		// if ( ! current_user_can( 'manage_network_options' ) ) {
		// 	wp_die( wp_kses( __( 'You do not have sufficient permissions to access this page.', 'basgate' ), Helper::$allowed_html ) );
		// }
		$options       = Options::get_instance();
		$login_access  = Login_Access::get_instance();
		$auth_settings = get_blog_option(get_main_site_id(get_main_network_id()), 'auth_multisite_settings', array());
	?>
		<div class="wrap">
			<form method="post" action="" autocomplete="off">
				<h2><?php esc_html_e('Basgate Settings', 'basgate'); ?></h2>
				<p><?php echo wp_kses(__('Most <strong>Basgate</strong> settings are set in the individual sites, but you can specify a few options here that apply to <strong>all sites in the network</strong>. These settings will override settings in the individual sites.', 'basgate'), Helper::$allowed_html); ?></p>

				<p><input type="checkbox" id="auth_settings_multisite_override" name="auth_settings[multisite_override]" value="1" <?php checked(1 === intval($auth_settings['multisite_override'])); ?> /><label for="auth_settings_multisite_override"><?php esc_html_e('Override individual site settings with the settings below', 'basgate'); ?></label></p>
				<p><input type="checkbox" id="auth_settings_prevent_override_multisite" name="auth_settings[prevent_override_multisite]" value="1" <?php checked(1 === intval($auth_settings['prevent_override_multisite'])); ?> /><label for="auth_settings_prevent_override_multisite"><?php esc_html_e('Prevent site administrators from overriding any multisite settings defined here (via Basgate > Advanced > Override multisite options)', 'basgate'); ?></label></p>

				<div id="auth_multisite_settings_disabled_overlay" style="display: none;"></div>

				<div class="wrap" id="auth_multisite_settings">
					<?php $options->print_section_info_tabs(array('context' => 'multisite_admin')); ?>

					<?php wp_nonce_field('save_auth_settings', 'nonce_save_auth_settings'); ?>

					<?php // Custom access lists (for network, we only really want approved list, not pending or blocked). 
					?>
					<div id="section_info_access_lists" class="section_info">
						<p><?php esc_html_e('Manage who has access to all sites in the network.', 'basgate'); ?></p>
					</div>
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row"><?php esc_html_e('Who can log in to sites in this network?', 'basgate'); ?></th>
								<td><?php $login_access->print_radio_auth_access_who_can_login(array('context' => 'multisite_admin')); ?></td>
							</tr>
						</tbody>
					</table>

					<br class="clear" />
				</div>
				<input type="button" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Save Changes', 'basgate'); ?>" onclick="saveAuthMultisiteSettings(this);" />
			</form>
		</div>
<?php
	}


	/**
	 * Network Admin menu item
	 *
	 * Action: network_admin_menu
	 *
	 * @return void
	 */
	public function network_admin_menu()
	{
		// @see http://codex.wordpress.org/Function_Reference/add_menu_page
		// add_menu_page(
		// 	'Basgate',
		// 	'Basgate',
		// 	'manage_network_options',
		// 	'basgate',
		// 	array(self::get_instance(), 'create_network_admin_page'),
		// 	'dashicons-groups',
		// 	89 // Position.
		// );
	}


	/**
	 * Create the options page under Dashboard > Settings.
	 *
	 * Action: admin_menu
	 */
	public function add_plugin_page()
	{
		$options    = Options::get_instance();
		$admin_menu = $options->get('advanced_admin_menu');
		if ('settings' === $admin_menu) {
			// @see http://codex.wordpress.org/Function_Reference/add_options_page
			add_options_page(
				'Basgate',
				'Basgate',
				'create_users',
				'basgate',
				array(self::get_instance(), 'create_admin_page')
			);
		} else {
			// @see http://codex.wordpress.org/Function_Reference/add_menu_page
			add_menu_page(
				'Basgate',
				'Basgate',
				'create_users',
				'basgate',
				array(self::get_instance(), 'create_admin_page'),
				'dashicons-groups',
				'99.0018465' // position (decimal is to make overlap with other plugins less likely).
			);
		}
	}


	/**
	 * Load external resources on this plugin's options page.
	 *
	 * Action: load-settings_page_authorizer
	 * Action: load-toplevel_page_authorizer
	 * Action: admin_head-index.php
	 */
	public function load_options_page()
	{
		wp_enqueue_script('basgate', plugins_url('js/basgate.js', \BasgateSDK\plugin_root()), array('jquery-effects-shake'), '3.10.0', true);
		wp_localize_script(
			'basgate',
			'authL10n',
			array(
				'baseurl'              => get_bloginfo('url'),
				'saved'                => esc_html__('Saved', 'basgate'),
				'duplicate'            => esc_html__('Duplicate', 'basgate'),
				'failed'               => esc_html__('Failed', 'basgate'),
				'local_wordpress_user' => esc_html__('Local WordPress user', 'basgate'),
				'block_ban_user'       => esc_html__('Block/Ban user', 'basgate'),
				'remove_user'          => esc_html__('Remove user', 'basgate'),
				'no_users_in'          => esc_html__('No users in', 'basgate'),
				'save_changes'         => esc_html__('Save Changes', 'basgate'),
				'private_pages'        => esc_html__('Private Pages', 'basgate'),
				'public_pages'         => esc_html__('Public Pages', 'basgate'),
				'first_page'           => esc_html__('First page'),
				'previous_page'        => esc_html__('Previous page'),
				'next_page'            => esc_html__('Next page'),
				'last_page'            => esc_html__('Last page'),
				'is_network_admin'     => is_network_admin() ? '1' : '0',
			)
		);

		// wp_enqueue_script('jquery-autogrow-textarea', plugins_url('vendor-custom/jquery.autogrow-textarea/jquery.autogrow-textarea.js', \BasgateSDK\plugin_root()), array('jquery'), '3.0.7', true);

		// wp_enqueue_script('jquery.multi-select', plugins_url('vendor-custom/jquery.multi-select/0.9.12/js/jquery.multi-select.js', \BasgateSDK\plugin_root()), array('jquery'), '0.9.12', true);

		wp_register_style('basgate-css', plugins_url('css/basgate.css', \BasgateSDK\plugin_root()), array(), '3.10.0');
		wp_enqueue_style('basgate-css');

		// wp_register_style('jquery-multi-select-css', plugins_url('vendor-custom/jquery.multi-select/0.9.12/css/multi-select.css', \BasgateSDK\plugin_root()), array(), '0.9.12');
		// wp_enqueue_style('jquery-multi-select-css');

		add_action('admin_notices', array(self::get_instance(), 'admin_notices')); // Add any notices to the top of the options page.
		add_action('admin_head', array(self::get_instance(), 'admin_head')); // Add help documentation to the options page.
	}
}

<?php

/**
 * Basgate
 *
 * @license  GPL-2.0+
 * @link     https://github.com/basgate
 * @package  basgate
 */

namespace BasgateSDK;

use BasgateSDK\Helper;
use BasgateSDK\Options;

/**
 * Contains modifications to the WordPress login form.
 */
class Login_Form extends Singleton
{

	/**
	 * Load script to display message to anonymous users browing a site (only
	 * enqueue if configured to only allow logged in users to view the site and
	 * show a warning to anonymous users).
	 *
	 * Action: wp_enqueue_scripts
	 */
	public function auth_public_scripts()
	{
		// Load (and localize) public scripts.
		$options = Options::get_instance();
		if (
			'logged_in_users' === $options->get('access_who_can_view') &&
			'warning' === $options->get('access_public_warning') &&
			get_option('auth_settings_advanced_public_notice')
		) {
			$current_path = ! empty($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : home_url();
			wp_enqueue_script('auth_public_scripts', plugins_url('/js/authorizer-public.js', plugin_root()), array('jquery'), '3.2.2', false);
			$auth_localized = array(
				'wpLoginUrl'      => wp_login_url($current_path),
				'anonymousNotice' => $options->get('access_redirect_to_message'),
				'logIn'           => esc_html__('Log In', 'authorizer'),
			);
			wp_localize_script('auth_public_scripts', 'auth', $auth_localized);
		}
	}


	/**
	 * Enqueue JS scripts and CSS styles appearing on wp-login.php.
	 *
	 * Action: login_enqueue_scripts
	 *
	 * @return void
	 */
	public function bassdk_enqueue_scripts()
	{
		wp_enqueue_style('bassdk-login-styles', plugins_url('css/styles.css', plugin_root()), array(), '1.0');
		//TODO: Replace this script with cdn url
		wp_enqueue_script('bassdk-login-script', plugins_url('js/script.js', plugin_root()), array('jquery'), '1.0', true);
		// wp_enqueue_script('bassdk-login-cdn-script', esc_url('https://pub-8bba29ca4a7a4024b100dca57bc15664.r2.dev/sdk/stage/v1/public.js'),  array('jquery'), '1.0', true);
		//TODO: Add Admin options part
		// $this->auth_public_scripts();
	}

	public function bassdk_add_modal()
	{
		echo do_shortcode('[bassdk_login]');
		$this->load_login_footer_js();
	}


	function bassdk_login_form()
	{
		$options       = Options::get_instance();
		$auth_settings = $options->get_all(Helper::SINGLE_CONTEXT, 'allow override');

		if (!array_key_exists('bas_client_id', $auth_settings)) {
			$auth_settings['bas_client_id'] = "no_client_id";
		}

		ob_start();
?>



		<script type="text/javascript">
			try {
				window.addEventListener("JSBridgeReady", async (event) => {
					console.log("JSBridgeReady READY Now ");

					// $('#bassdk-login-modal').show();
					console.log("JSBridgeReady Successfully loaded ");
					await getBasAuthCode('<?php echo esc_attr(trim($auth_settings['bas_client_id'])); ?>').then((res) => {
						if (res) {
							console.log("getBasAuthCode res.status :", res.status)
							signInCallback(res.data);
						}
					}).catch((error) => {
						console.error("ERROR on catch getBasAuthCode:", error)
					})
				}, false);
			} catch (error) {
				console.error("ERROR on getBasAuthCode:", error)
			}
		</script>

		<div id="bassdk-login-modal" class="bassdk-modal">
			<div class="bassdk-modal-content" style="text-align: center;">
				<span class="bassdk-close">&times;</span>
				<h2>BAS Login</h2>
				<script type="text/javascript">
					function invokeBasLogin() {
						try {
							console.log("===== invokeBasLogin() isJSBridgeReady :", isJSBridgeReady)
						} catch (error) {
							console.error("ERROR on isJSBridgeReady:", error)
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
		<script type="application/javascript" crossorigin="anonymous" src="https://pub-8bba29ca4a7a4024b100dca57bc15664.r2.dev/sdk/merchant/v1/public.js" onload="invokeBasLogin();"></script>
		<?php
		return ob_get_clean();
	}


	/**
	 * Load external resources in the footer of the wp-login.php page.
	 *
	 * Action: login_footer
	 */
	public function load_login_footer_js()
	{

		// Grab plugin settings.
		$options       = Options::get_instance();
		$auth_settings = $options->get_all(Helper::SINGLE_CONTEXT, 'allow override');
		$ajaxurl       = admin_url('admin-ajax.php');
		if ('1' === $auth_settings['bas_enabled']) :
		?>
			<script>
				/* global location, window */
				// Reload login page if reauth querystring param exists,
				// since reauth interrupts external logins (e.g., google).
				if (location.search.indexOf('reauth=1') >= 0) {
					location.href = location.href.replace('reauth=1', '');
				}

				// eslint-disable-next-line no-implicit-globals
				function authUpdateQuerystringParam(uri, key, value) {
					var re = new RegExp('([?&])' + key + '=.*?(&|$)', 'i');
					var separator = uri.indexOf('?') !== -1 ? '&' : '?';
					if (uri.match(re)) {
						return uri.replace(re, '$1' + key + '=' + value + '$2');
					} else {
						return uri + separator + key + '=' + value;
					}
				}

				// eslint-disable-next-line
				function signInCallback(resData) { // jshint ignore:line
					console.log("STARTED signInCallback()")
					var $ = jQuery;
					console.log("signInCallback() resData:", JSON.stringify(resData))

					if (resData.hasOwnProperty('authId')) {
						// Send the JWT to the server
						console.log("signInCallback() resData.hasOwnProperty('authId') ")
						var ajaxurl = '<?php echo esc_attr($ajaxurl); ?>';
						var nonce = '<?php echo esc_attr(wp_create_nonce('basgate_login_nonce')); ?>';
						$.post(ajaxurl, {
							action: 'process_basgate_login',
							data: resData,
							nonce: nonce,
							authId: resData.authId,
						}, function(data, textStatus) {

							// console.log("inside signInCallback() textStatus :" + textStatus)
							console.log("signInCallback() textStatus :", textStatus)
							console.log("signInCallback() data :", data)

							// console.log("inside signInCallback() ajaxurl :" + ajaxurl + "  - nonce :" + nonce)
							// Reload wp-login.php to continue the authentication process.
							var newHref = authUpdateQuerystringParam(location.href, 'external', 'basgate');

							// If we have a login form embedded via [authorizer_login_form], we are
							// not on wp-login.php, so change the location to wp-login.php.

							if ('undefined' !== typeof auth && auth.hasOwnProperty('wpLoginUrl')) {
								newHref = authUpdateQuerystringParam(auth.wpLoginUrl, 'external', 'basgate');
							} else {
								newHref = '<?php echo esc_attr(Helper::modify_current_url_for_external_login()); ?>';
							}


							if (location.href === newHref) {
								console.log('signInCallback location.reload() location.href:', location.href);
								location.reload();
							} else {
								console.log("signInCallback() else newHref: " + newHref)
								console.log("signInCallback() else location.href: " + location.href)
								location.href = newHref;
							}
						});
					} else {
						// Update the app to reflect a signed out user
						// Possible error values:
						//   "user_signed_out" - User is signed-out
						//   "access_denied" - User denied access to your app
						//   "immediate_failed" - Could not automatically log in the user
						// console.log('Sign-in state: ' + credentialResponse['error']);

						// If user denies access, reload the login page.
						if (credentialResponse.error === 'access_denied' || credentialResponse.error === 'user_signed_out') {
							window.location.reload();
						}
					}
				}
			</script>
<?php
		endif;
	}



	/**
	 * Add custom error message to login screen.
	 *
	 * Filter: login_errors
	 *
	 * @param  string $errors Error description.
	 * @return string         Error description with Authorizer errors added.
	 */
	public function show_advanced_login_error($errors)
	{
		$error = get_option('auth_settings_advanced_login_error');
		delete_option('auth_settings_advanced_login_error');
		$errors = '    ' . $error . "<br />\n";
		return $errors;
	}


	public function createNewUser($user_data)
	{
		if (array_key_exists('username', $user_data)) {
			$username = $user_data['username'];
		} else if(array_key_exists('email', $user_data)){
			$username = explode('@', $user_data['email']);
			$username = $username[0];
		}
		// // If there's already a user with this username (e.g.,
		// // johndoe/johndoe@gmail.com exists, and we're trying to add
		// // johndoe/johndoe@example.com), use the full email address
		// // as the username.
		// if (get_user_by('login', $username) !== false) {
		// 	$username = $user_data['email'];
		// }

		$result = wp_insert_user(
			array(
				'user_login'      => strtolower($username),
				'user_pass'       => wp_generate_password(), // random password.
				'first_name'      => array_key_exists('first_name', $user_data) ? $user_data['first_name'] : '',
				'last_name'       => array_key_exists('last_name', $user_data) ? $user_data['last_name'] : '',
				'user_email'      => Helper::lowercase($user_data['email']),
				'user_registered' => wp_date('Y-m-d H:i:s'),
				'role'            => $user_data['role'],
			)
		);

		// Fail with message if error.
		if (is_wp_error($result) || 0 === $result) {
			return $result;
		}

		// Authenticate as new user.
		$user = new \WP_User($result);

		do_action('authorizer_user_register', $user, $user_data);
	}
}

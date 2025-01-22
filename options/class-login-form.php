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
	 * Enqueue JS scripts and CSS styles appearing on wp-login.php.
	 *
	 * Action: login_enqueue_scripts
	 *
	 * @return void
	 */
	public function bassdk_enqueue_scripts()
	{
		Helper::basgate_log('===== STARTED bassdk_enqueue_scripts() ');
		wp_enqueue_script('bassdk-sdk-script', plugins_url('js/public.js', plugin_root()), array(), time(), array(
			'strategy' => 'async',
			'in_footer' => true,
		));
		if (wp_script_is('bassdk-sdk-script', 'enqueued')) {
			Helper::basgate_log('===== bassdk_enqueue_scripts() script is enqueued');
		} else {
			Helper::basgate_log('===== bassdk_enqueue_scripts() script is not enqueued');
			wp_enqueue_script('bassdk-sdk-script', plugins_url('js/public.js', plugin_root()), array('jquery'), time(), true);
		}

		wp_enqueue_style('bassdk-loading-style', plugins_url('css/basgate-login.css', plugin_root()), array(), time(), '');
	}

	public function check_login()
	{
		Helper::basgate_log('===== STARTED check_login() ');

		if (Helper::is_user_already_logged_in()) {
			Helper::basgate_log('===== check_login() user already logged-in');
			return;
		}

		try {
			if (strpos($_SERVER['REQUEST_URI'], 'my-account') !== false) {
				Helper::basgate_log('===== check_login() my-account page');
				$this->bassdk_enqueue_scripts();
				$this->bassdk_add_modal();
			}
		} catch (Exception $e) {
			Helper::basgate_log('===== ERROR in check_login() ' . $e->getMessage());
		}

		if (
			// is_page('my-account') ||
			isset($_GET['action']) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$_GET['action'] === 'login' // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		) {
			Helper::basgate_log('===== check_login() my-account page or login action:'.$_GET['action']);
			$this->bassdk_enqueue_scripts();
			$this->bassdk_add_modal();
		}
	}

	public function loading()
	{
		return '<div id="basgate-pg-spinner" class="basgate-woopg-loader">
				<div class="bounce1"></div>
				<div class="bounce2"></div>
				<div class="bounce3"></div>
				<div class="bounce4"></div>
				<div class="bounce5">
				</div>
					<p class="loading-basgate">Loading Basgate</p>
				</div>
				<div class="basgate-overlay basgate-woopg-loader"></div>
			';
	}

	public function unloading()
	{
		return '
				jQuery(".loading-basgate").hide();
                jQuery(".basgate-woopg-loader").hide();
                jQuery(".basgate-overlay").hide();
			';
	}

	public function bassdk_add_modal()
	{
		Helper::basgate_log('===== STARTED bassdk_add_modal() ');
		$this->load_login_footer_js();
		echo do_shortcode('[bassdk_login]');
	}

	function bassdk_login_form()
	{
		Helper::basgate_log('===== STARTED bassdk_login_form() ');

		$options = Options::get_instance();
		$option = 'bas_client_id';
		$bas_client_id = $options->get($option);

		ob_start();
		$current_user = wp_get_current_user();
		$authenticated_by = get_user_meta($current_user->ID, 'authenticated_by', true);

		if (!is_user_logged_in() && $authenticated_by !== 'basgate'):
		?>
			<div>
				<script type="text/javascript">
					try {
						console.log("===== STARTED bassdk_login_form PHP")
						window.addEventListener("JSBridgeReady", async (event) => {
							document.getElementById('basgate-pg-spinner').removeAttribute('hidden');
							document.querySelector('.basgate-overlay').removeAttribute('hidden');

							var clientId = '<?php echo esc_attr($bas_client_id); ?>';
							console.log("JSBridgeReady Successfully loaded clientId:", clientId);
							if (!('getBasAuthCode' in window)) {
								console.log("JSBridgeReady waiting to load getBasAuthCode...");
							}
							try {
								await getBasAuthCode(clientId).then((res) => {
									console.log("getBasAuthCode 111 res:", JSON.stringify(res))
									if (res) {
										if (res.status == "1") {
											signInCallback(res.data);
										} else {
											console.error("ERROR on getBasAuthCode res 111:", JSON.stringify(res))
										}
									}else{
										console.error("ERROR on getBasAuthCode res 222:", JSON.stringify(res))
										jQuery(document).ready(function ($) {
											$(".loading-basgate").hide();
											$(".basgate-woopg-loader").hide();
											$(".basgate-overlay").hide();
										});
									}
								}).catch((error) => {
									console.error("ERROR on catch getBasAuthCode 111:", error)
									jQuery(document).ready(function ($) {
										$(".loading-basgate").hide();
										$(".basgate-woopg-loader").hide();
										$(".basgate-overlay").hide();
									});
								})
							} catch (error) {
								console.error("ERROR getBasAuthCode 111:", error)
								try {

									await getBasAuthCode(clientId).then((res) => {
										console.log("getBasAuthCode 222 res:", res)
										if (res) {
											if (res.status == "1") {
												signInCallback(res.data);
											} else {
												console.error("ERROR on getBasAuthCode res:", res)
											}
										}
									}).catch((error) => {
										console.error("ERROR on catch getBasAuthCode 222:", error)
									})
								} catch (error) {
									console.error("ERROR getBasAuthCode 222:", error)
								}
							}
						}, false);
					} catch (error) {
						console.error("ERROR on getBasAuthCode:", error)
					}
				</script>
				<div id="basgate-pg-spinner" class="basgate-woopg-loader" hidden>
					<div class="bounce1"></div>
					<div class="bounce2"></div>
					<div class="bounce3"></div>
					<div class="bounce4"></div>
					<div class="bounce5"></div>
					<p class="loading-basgate">Loading Basgate...</p>
				</div>
				<div class="basgate-overlay basgate-woopg-loader" hidden></div>
			</div>
		<?php
		endif;
		return ob_get_clean();
	}

	/**
	 * Load external resources in the footer of the wp-login.php page.
	 *
	 * Action: login_footer
	 */
	public function load_login_footer_js()
	{
		Helper::basgate_log('===== STARTED load_login_footer_js() ');

		// Grab plugin settings.
		$options = Options::get_instance();
		$auth_settings = $options->get_all(Helper::SINGLE_CONTEXT, 'allow override');
		$ajaxurl = admin_url('admin-ajax.php');

		if ('yes' === $auth_settings['enabled']):
			?>
				<script>
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
						var $ = jQuery;
						console.log("signInCallback() resData:", JSON.stringify(resData))

						if (resData.hasOwnProperty('authId')) {
							// Send the authId to the server
							var ajaxurl = '<?php echo esc_attr($ajaxurl); ?>';
							var nonce = '<?php echo esc_attr(wp_create_nonce('basgate_login_nonce')); ?>';
							$.post(ajaxurl, {
								action: 'process_basgate_login',
								data: resData,
								nonce: nonce,
								// authId: resData.authId,
							}, function(data, textStatus) {

								console.log("signInCallback() textStatus :", textStatus)
								console.log("signInCallback() data :", data)

								var newHref = '<?php echo esc_attr(Helper::get_login_redirect_url()); ?>';
								console.log("signInCallback() before newHref: ", newHref)
								newHref = authUpdateQuerystringParam(newHref, 'external', 'basgate');
								console.log("signInCallback() after newHref: ", newHref)

								if (location.href === newHref) {
									console.log('signInCallback location.reload() location.href:', location.href);
									location.reload();
								} else {
									console.log("signInCallback() else location.href: ", location.href)
									location.href = newHref;
								}
							});
						} else {
							// If user denies access, reload the login page.
							if (resData.error === 'access_denied' || resData.error === 'user_signed_out') {
								window.location.reload();
							}
						}
					}
				</script>
	<?php
		endif;
	}

	/**
	 * Ensure that whenever we are on a wp-login.php page for WordPress and there is a log in link, it properly
	 * generates a wp-login.php URL with the additional "wordpress=external" URL parameter.
	 * Only affects the URL if the Hide WordPress Logins option is enabled.
	 *
	 * Filter:  wp_login_url https://developer.wordpress.org/reference/functions/wp_login_url/
	 *
	 * @param  string $login_url URL for the log in page.
	 * @return string            URL for the log in page.
	 */
	public function maybe_add_external_wordpress_to_log_in_links($login_url)
	{
		Helper::basgate_log('===== STARTED maybe_add_external_wordpress_to_log_in_links() $login_url: ' . $login_url);
		// Initial check to make sure that we are on a wp-login.php page.
		if (isset($GLOBALS['pagenow']) && site_url($GLOBALS['pagenow'], 'login') === $login_url) {
			// Do a check in here within the $_REQUEST params to narrow down the scope of where we'll modify the URL
			// We need to check against the following:  action=lostpassword, checkemail=confirm, action=rp, and action=resetpass.
			if (
				(
					isset($_REQUEST['action']) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					(
						'lostpassword' === $_REQUEST['action'] || // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						'rp' === $_REQUEST['action'] || // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						'resetpass' === $_REQUEST['action'] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					)
				) || (
					isset($_REQUEST['checkemail']) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					'confirm' === $_REQUEST['checkemail'] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				)
			) {
				// Grab plugins settings.
				$options = Options::get_instance();
				$auth_settings = $options->get_all(HELPER::SINGLE_CONTEXT, 'allow override');
				// Only change the Log in URL if the Hide WordPress Logins option is enabled in Authorizer.
				if (
					array_key_exists('advanced_hide_wp_login', $auth_settings) &&
					'1' === $auth_settings['advanced_hide_wp_login']
				) {
					// Need to determine if existing URL has params already or not, then add the param and value.
					if (strpos($login_url, '?') === false) {
						$login_url = $login_url . '?external=wordpress';
					} else {
						$login_url = $login_url . '&external=wordpress';
					}
				}
			}
		}
		return $login_url;
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

	/**
	 * Give a personalized message for logged in users and a generic one for anonymous visitors
	 */
	public function bas_personal_message_when_logged_in()
	{
		if (is_user_logged_in()) {
			$current_user = wp_get_current_user();
			printf('Personal Message For %s!', esc_html($current_user->user_firstname));
		} else {
			echo ('Non-Personalized Message!');
		}
	}
}

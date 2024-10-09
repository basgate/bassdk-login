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
 * Implements the authentication (is user who they say they are?) features of
 * the plugin.
 */
class Authentication extends Singleton
{

	/**
	 * Tracks the external service used by the user currently logging out.
	 *
	 * @var string
	 */
	private static $authenticated_by = '';

	/**
	 * Authenticate against an external service.
	 *
	 * Filter: authenticate
	 *
	 * @param WP_User $user     user to authenticate.
	 * @param string  $username optional username to authenticate.
	 * @param string  $password optional password to authenticate.
	 * @return WP_User|WP_Error WP_User on success, WP_Error on failure.
	 */
	public function custom_authenticate($user, $username, $password)
	{

?>
		<script>
			alert("STARTED custom_authenticate() ")
		</script>
		<?php
		// Pass through if already authenticated.
		if (is_a($user, 'WP_User')) {
			return $user;
		} else {
			$user = null;
		}

		// Grab plugin settings.
		$options       = Options::get_instance();
		$auth_settings = $options->get_all(Helper::SINGLE_CONTEXT, 'allow override');

		// Start external authentication.
		$externally_authenticated_emails = array();
		$authenticated_by                = 'basgate';
		$result                          = null;

		// Try Basgate authentication if it's enabled and we don't have a
		// successful login yet.
		if (
			'1' === $auth_settings['bas_enabled'] &&
			0 === count($externally_authenticated_emails) &&
			! is_wp_error($result)
		) {
		?>
			<script>
				console.log("inside custom_authenticate() if( $auth_settings['bas_enabled'])")
			</script>
		<?php
			$result = $this->custom_authenticate_basgate($auth_settings);
			if (! is_null($result) && ! is_wp_error($result)) {
				if (is_array($result['email'])) {
					$externally_authenticated_emails = $result['email'];
				} else {
					$externally_authenticated_emails[] = $result['email'];
				}
				$authenticated_by = $result['authenticated_by'];
				$openId = $result['open_id'];
			}
		}

		?>
		<script>
			var open_id = '<?php echo esc_attr($openId); ?>'
			console.log("custom_authenticate() open_id :", open_id)
		</script>
		<?php
		// If we don't have an externally authenticated user, either skip to
		// WordPress authentication (if WordPress logins are enabled), or return
		// an error (if WordPress logins are disabled and at least one external
		// service is enabled).
		if (count(array_filter($externally_authenticated_emails)) < 1) {
			if (
				array_key_exists('advanced_disable_wp_login', $auth_settings) &&
				'1' === $auth_settings['advanced_disable_wp_login'] &&
				(
					'1' === $auth_settings['bas_enabled']
				)
			) {
				remove_filter('authenticate', 'wp_authenticate_username_password', 20, 3);
				remove_filter('authenticate', 'wp_authenticate_email_password', 20, 3);

				$error = new \WP_Error();

				if (empty($username)) {
					$error->add('empty_username', __('<strong>ERROR</strong>: The username field is empty.'));
				}

				if (empty($password)) {
					$error->add('empty_password', __('<strong>ERROR</strong>: The password field is empty.'));
				}

				return $error;
			}

			return $result;
		}

		// Remove duplicate and blank emails, if any.
		$externally_authenticated_emails = array_filter(array_unique($externally_authenticated_emails));

		// Get the external user's WordPress account by email address. This is
		// the normal behavior (and the most secure).
		foreach ($externally_authenticated_emails as $externally_authenticated_email) {
			$user = get_user_by('email', Helper::lowercase($externally_authenticated_email));
			// Stop trying email addresses once we have found a match.
			if (false !== $user) {
				break;
			}
		}

		// We'll track how this user was authenticated in user meta.
		if ($user) {
			update_user_meta($user->ID, 'authenticated_by', $authenticated_by);
			update_user_meta($user->ID, 'open_id', $openId);
		}

		// Check this external user's access against the access lists
		// (pending, approved, blocked).
		//TODO: Commented temperlay
		// $result = Authorization::get_instance()->check_user_access($user, $externally_authenticated_emails, $result);

		// Fail with message if there was an error creating/adding the user.
		if (is_wp_error($result) || 0 === $result) {
			return $result;
		}

		// If we have a valid user from check_user_access(), log that user in.
		if (get_class($result) === 'WP_User') {
			$user = $result;
		}

		// If we haven't exited yet, we have a valid/approved user, so authenticate them.
		return $user;
	}


	/**
	 * Validate this user's credentials against Basgate.
	 *
	 * @param  array $auth_settings Plugin settings.
	 * @return array|WP_Error       Array containing email, authenticated_by, first_name,
	 *                              last_name, and username strings for the successfully
	 *                              authenticated user, or WP_Error() object on failure,
	 *                              or null if not attempting a basgate login.
	 */
	protected function custom_authenticate_basgate($auth_settings)
	{

		?>
		<script>
			alert("STARTED custom_authenticate_basgate() ")
		</script>
		<?php
		// Move on if Basgate auth hasn't been requested here.
		// phpcs:ignore WordPress.Security.NonceVerification
		if (empty($_GET['external']) || 'basgate' !== $_GET['external']) {
			return null;
		}

		// Get one time use token.
		session_start();
		$token = array_key_exists('token', $_SESSION) ? $_SESSION['token'] : null;

		?>
		<script>
			var token = '<?php echo esc_attr($token) ?>'
			console.log("custom_authenticate_basgate() token 111:", token)
			console.log("custom_authenticate_basgate() token 222:", JSON.stringify(token))
		</script>

		<?php

		// No token, so this is not a succesful Basgate login.
		if (empty($token)) {
			return null;
		}

		// $auth_settings['bas_client_id'] = apply_filters('basgate_client_id', $auth_settings['bas_client_id']);
		// $auth_settings['bas_client_secret'] = apply_filters('basgate_client_secret', $auth_settings['bas_client_secret']);

		// Verify this is a successful Basgate authentication.
		try {
			$payload = $this->getBasUserInfo($token);
		} catch (\Throwable $th) {
			return new \WP_Error('invalid_basgate_login', __('Invalid Basgate credentials provided.', BasgateConstants::ID));
		}

		// Invalid ticket, so this in not a successful Basgate login.
		if (empty($payload['user_name'])) {
			return new \WP_Error('invalid_basgate_login', __('Invalid Basgate credentials provided.', BasgateConstants::ID));
		}


		$username     = $payload['user_name'];
		$name     = $payload['name'];
		$openId     = $payload['open_id'];
		$phone     = $payload['phone'];

		?>
		<script>
			var payload = '<?php echo esc_attr($payload); ?>'
			console.log("custom_authenticate() $payload :", payload)
		</script>
<?php


		return array(
			'email'             => $phone,
			'username'          => $username,
			'first_name'        => $name,
			'last_name'         => '',
			'open_id'         => $openId,
			'authenticated_by'  => 'basgate',
			'bas_attributes' => $payload,
		);
	}



	/**
	 * Verify the Basgate login and set a session token.
	 *
	 * Flow: "Sign in with Basgate" button clicked; JS Basgate library
	 * called; JS function signInCallback() fired with results from Basgate;
	 * signInCallback() posts code and nonce (via AJAX) to this function;
	 * This function checks the token using the Basgate PHP library, and
	 * saves it to a session variable if it's authentic; control passes
	 * back to signInCallback(), which will reload the current page
	 * (wp-login.php) on success; wp-login.php reloads; custom_authenticate
	 * hooked into authenticate action fires again, and
	 * custom_authenticate_google() runs to verify the token; once verified
	 * custom_authenticate proceeds as normal with the google email address
	 * as a successfully authenticated external user.
	 *
	 * Action: wp_ajax_process_google_login
	 * Action: wp_ajax_nopriv_process_google_login
	 *
	 * @return void, but die with the value to return to the success() function in AJAX call signInCallback().
	 */
	public function ajax_process_basgate_login()
	{

		// Nonce check.
		if (
			! isset($_POST['nonce']) ||
			! wp_verify_nonce(sanitize_key($_POST['nonce']), 'basgate_login_nonce')
		) {
			die(esc_html("ERROR wrong nonce"));
		}

		// Basgate authentication token.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$data = isset($_POST['data']) ? wp_unslash($_POST['data']) : null;
		if (array_key_exists('auth_id', $data)) {
			$auth_id = $data["auth_id"];
		}

		// Grab plugin settings.
		// $options       = Options::get_instance();
		// $auth_settings = $options->get_all(Helper::SINGLE_CONTEXT, 'allow override');

		// $auth_settings['bas_client_id'] = apply_filters('basgate_client_id', $auth_settings['bas_client_id']);
		// $auth_settings['bas_client_secret'] = apply_filters('basgate_client_secret', $auth_settings['bas_client_secret']);


		//TODO: Add basgate backend request for token and userinfo
		if (empty($auth_id)) {
			return null;
		}
		$bas_token = $this->getBasToken($auth_id);

		// Store the token (for verifying later in wp-login).
		session_start();
		if (empty($_SESSION['token'])) {
			// Store the token in the session for later use.
			$_SESSION['token'] = $bas_token;

			$response = 'Successfully authenticated. :' . $bas_token;
		} else {
			$response = 'Already authenticated. :' . $_SESSION['token'];
			$_SESSION['token'] = $bas_token;
		}

		die(esc_html($response));
	}



	//Process Basgate Token
	public function getBasToken($auth_id)
	{

		$options       = Options::get_instance();
		$auth_settings = $options->get_all(Helper::SINGLE_CONTEXT, 'allow override');

		//access BASSuperApp  settings
		if (array_key_exists('bas_environment', $auth_settings) && $auth_settings["bas_environment"] === "1") {
			$bassdk_api =  BasgateConstants::PRODUCTION_HOST;
		} else {
			$bassdk_api = BasgateConstants::STAGING_HOST;
		}

		$client_id  = $auth_settings["bas_client_id"];
		$client_secret  =  $auth_settings["bas_client_secret"];
		$grant_type  = "authorization_code";
		$code = $auth_id;
		$redirect_uri = $bassdk_api . "api/v1/auth/callback";

		try {
			//Send Post request to get payment session details
			$curl = curl_init();
			curl_setopt_array($curl, [
				CURLOPT_URL => $bassdk_api . 'api/v1/auth/token',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "POST",
				CURLOPT_POSTFIELDS => 'grant_type=' . $grant_type . '&client_id=' . $client_id . '&client_secret=' . $client_secret . '&code=' . $code . '&redirect_uri=' . $redirect_uri,
				CURLOPT_HTTPHEADER => [
					"Content-Type: application/x-www-form-urlencoded"
				],
			]);
			$result = curl_exec($curl);
			$err = curl_error($curl);
			curl_close($curl);
			if ($err) {
			} else {
				$response = json_decode($result, true);
				if (array_key_exists('status', $response)) {
					if ($response['status'] === '1') {
						return $response['data']['access_token'];
					} else {
						return $response['messages'];
					}
				}
			}
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	//Process Basgate get UserInfo
	public function getBasUserInfo($token)
	{

		$options       = Options::get_instance();
		$auth_settings = $options->get_all(Helper::SINGLE_CONTEXT, 'allow override');

		//access BASSuperApp  settings
		if (array_key_exists('bas_environment', $auth_settings) && $auth_settings["bas_environment"] === "1") {
			$bassdk_api =  BasgateConstants::PRODUCTION_HOST;
		} else {
			$bassdk_api = BasgateConstants::STAGING_HOST;
		}

		try {
			$curl = curl_init();
			curl_setopt_array($curl, [
				CURLOPT_URL => $bassdk_api . 'api/v1/auth/token',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "GET",
				CURLOPT_HTTPHEADER => [
					"Content-Type: application/json",
					"Authorization: Bearer " . $token
				],
			]);
			$result = curl_exec($curl);
			$err = curl_error($curl);
			curl_close($curl);
			if ($err) {
			} else {
				$response = json_decode($result, true);
				if (array_key_exists('status', $response)) {
					if ($response['status'] === '1') {
						return $response['data'];
					} else {
						return $response['messages'];
					}
				}
			}
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	/**
	 * Fetch the logging out user's external service (so we can log out of it
	 * below in the wp_logout hook).
	 *
	 * Action: clear_auth_cookie
	 *
	 * @return void
	 */
	public function pre_logout()
	{
		self::$authenticated_by = get_user_meta(get_current_user_id(), 'authenticated_by', true);

		// If we didn't find an authenticated method, check $_REQUEST (if this is a
		// pending user facing the "no access" message, their logout link will
		// include "external=?" since they don't have a WP_User to attach the
		// "authenticated_by" usermeta to).
		if (empty(self::$authenticated_by) && ! empty($_REQUEST['external'])) {
			self::$authenticated_by = $_REQUEST['external'];
		}
	}

	/**
	 * Log out of the attached external service.
	 *
	 * Action: wp_logout
	 *
	 * @return void
	 */
	public function custom_logout()
	{
		// Grab plugin settings.
		$options       = Options::get_instance();
		$auth_settings = $options->get_all(Helper::SINGLE_CONTEXT, 'allow override');

		// Reset option containing old error messages.
		delete_option('auth_settings_advanced_login_error');


		// If session token set, log out of Basgate.
		if (session_id() === '') {
			session_start();
		}
		if ('google' === self::$authenticated_by && array_key_exists('token', $_SESSION)) {
			$token = $_SESSION['token'];

			// Fetch the Basgate Client ID (allow overrides from filter or constant).
			if (defined('AUTHORIZER_GOOGLE_CLIENT_ID')) {
				// $auth_settings['bas_client_id'] = \AUTHORIZER_GOOGLE_CLIENT_ID;
			}
			/**
			 * Filters the Basgate Client ID used by Authorizer to authenticate.
			 *
			 * @since 3.9.0
			 *
			 * @param string $google_client_id  The stored Basgate Client ID.
			 */
			$auth_settings['bas_client_id'] = apply_filters('basgate_client_id', $auth_settings['bas_client_id']);

			// Fetch the Basgate Client Secret (allow overrides from filter or constant).
			if (defined('AUTHORIZER_GOOGLE_CLIENT_SECRET')) {
				// $auth_settings['bas_client_secret'] = \AUTHORIZER_GOOGLE_CLIENT_SECRET;
			}
			/**
			 * Filters the Basgate Client Secret used by Authorizer to authenticate.
			 *
			 * @since 3.6.1
			 *
			 * @param string $google_client_secret  The stored Basgate Client Secret.
			 */
			$auth_settings['bas_client_secret'] = apply_filters('basgate_client_secret', $auth_settings['bas_client_secret']);

			// Build the Basgate Client.
			// $client = new \Google_Client();
			// $client->setApplicationName('WordPress');
			// $client->setClientId(trim($auth_settings['bas_client_id']));
			// $client->setClientSecret(trim($auth_settings['bas_client_secret']));
			// $client->setRedirectUri('postmessage');

			// // Revoke the token.
			// $client->revokeToken($token);

			// Remove the credentials from the user's session.
			unset($_SESSION['token']);
		}
	}
}

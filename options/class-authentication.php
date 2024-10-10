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
			console.log("STARTED custom_authenticate() ")
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
				console.log("custom_authenticate() bas_enabled=true")
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
			?>
				<script>
					var open_id = '<?php echo esc_attr($openId); ?>'
					console.log("custom_authenticate() open_id :", open_id)
				</script>
		<?php
			}
		}


		// Check this external user's access against the access lists
		// (pending, approved, blocked).
		//TODO: Commented temperlay
		$result = $this->check_user_access($user, $result);

		// Fail with message if there was an error creating/adding the user.
		if (is_wp_error($result) || 0 === $result) {
			return $result;
		}

		// If we have a valid user from check_user_access(), log that user in.
		if (get_class($result) === 'WP_User') {
			$user = $result;
		}

		// We'll track how this user was authenticated in user meta.
		if ($user) {
			update_user_meta($user->ID, 'authenticated_by', $authenticated_by);
			update_user_meta($user->ID, 'open_id', $openId);
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
			console.log("STARTED custom_authenticate_basgate() location.href:", location.href);
		</script>
		<?php

		// Get one time use token.
		session_start();
		if (array_key_exists('basToken', $_SESSION) || array_key_exists('token', $_SESSION)) {
			$token =  $_SESSION['basToken'];
		?>
			<script>
				console.log("custom_authenticate_basgate() $token exist");
			</script>
		<?php
		} else {
			// No token, so this is not a succesful Basgate login.
		?>
			<script>
				console.log("custom_authenticate_basgate() ERROR $token not Exists");
			</script>
		<?php
			return new \WP_Error('invalid_basgate_login', __('You are not Basgate.', BasgateConstants::ID));
		}

		// $auth_settings['bas_client_id'] = apply_filters('basgate_client_id', $auth_settings['bas_client_id']);
		// $auth_settings['bas_client_secret'] = apply_filters('basgate_client_secret', $auth_settings['bas_client_secret']);

		// Verify this is a successful Basgate authentication.
		try {
			$payload = $this->getBasUserInfo($token);
		} catch (\Throwable $th) {
			return new \WP_Error('invalid_basgate_login', __('Error on getting userinfo from Basgate API.', BasgateConstants::ID), $th->getMessage());
		}

		// Invalid ticket, so this in not a successful Basgate login.
		if (array_key_exists("status", $payload) && "0" === $payload['status']) {
			return new \WP_Error('invalid_basgate_login', __('Invalid Basgate credentials provided.', BasgateConstants::ID));
		}

		$data = $payload['data'];

		$username     = $data['user_name'];
		$name     = $data['name'];
		$openId     = $data['open_id'];
		$phone     = $data['phone'];

		?>
		<script>
			var data = '<?php echo esc_attr($data); ?>'
			console.log("custom_authenticate() $payload :", JSON.stringify(data))
		</script>
	<?php


		return array(
			'email'             => $phone . BasgateConstants::EMAIL_DOMAIN,
			'username'          => $username,
			'first_name'        => $name,
			'last_name'         => '',
			'open_id'         	=> $openId,
			'role'				=> BasgateConstants::DEFAULT_ROLE,
			'authenticated_by'  => 'basgate',
			'bas_attributes' 	=> $data,
		);
	}

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

		if (empty($auth_id)) {
			$auth_id = isset($_POST['authId']) ? wp_unslash($_POST['authId']) : null;
		}
		// Grab plugin settings.
		$options       = Options::get_instance();
		$auth_settings = $options->get_all(Helper::SINGLE_CONTEXT, 'allow override');

		$auth_settings['bas_client_id'] = apply_filters('basgate_client_id', $auth_settings['bas_client_id']);
		$auth_settings['bas_client_secret'] = apply_filters('basgate_client_secret', $auth_settings['bas_client_secret']);


		//TODO: Add basgate backend request for token and userinfo
		if (empty($auth_id)) {
			die('');
			return null;
		}


		$bas_token = $this->getBasToken($auth_id);

		// Store the token (for verifying later in wp-login).
		session_start();
		if (!empty($bas_token)) {
			// Store the token in the session for later use.
			$_SESSION['basToken'] = $bas_token;
			$response = 'Successfully authenticated. :' . $bas_token;
		} else {
			$response = 'ERROR on authentication Token is empty.';
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
			//Send Post request to get token details
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
				if (array_key_exists('access_token', $response)) {
					return $response['access_token'];
				} else {
					return null;
				}
			}
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	//Process Basgate get UserInfo
	public function getBasUserInfo($token)
	{

	?>
		<script>
			console.log("===== STARTED getBasUserInfo()");
		</script>
		<?php
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
				CURLOPT_URL => $bassdk_api . 'api/v1/auth/userinfo',
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
		?>
				<script>
					console.log("ERROR getUserInfo() $err")
				</script>
			<?php
			}
			if ($result) {
			?>
				<script>
					var results = '<?php echo esc_attr($result) ?>';
					console.log("getBasUserInfo() curl Successed results:", results);
				</script>
			<?php
				$response = json_decode($result, true);
				// if (array_key_exists('status', $response)) {
				// if ($response['status'] === '1') {
				return $response;
				// }
				// }
			}
		} catch (\Throwable $th) {
			?>
			<script>
				console.log("getBasUserInfo() curl ERROR");
			</script>
<?php
			throw $th;
		}
	}

	public function check_user_access($user, $user_data = array())
	{
		// Grab plugin settings.
		$options                                    = Options::get_instance();
		$auth_settings                              = $options->get_all(Helper::SINGLE_CONTEXT, 'allow override');

		// If the approved external user does not have a WordPress account, create it.
		if (!$user) {
			if (array_key_exists('username', $user_data)) {
				$username = $user_data['username'];
			} else {
				$username = explode('@', $user_data['email']);
				$username = $username[0];
			}
			// If there's already a user with this username (e.g.,
			// johndoe/johndoe@gmail.com exists, and we're trying to add
			// johndoe/johndoe@example.com), use the full email address
			// as the username.
			if (get_user_by('login', $username) !== false) {
				$username = $user_data['email'];
			}
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

			/**
			 * Fires after an external user is authenticated for the first time
			 * and a new WordPress account is created for them.
			 *
			 * @since 2.8.0
			 *
			 * @param WP_User $user      User object.
			 * @param array   $user_data User data from external service.
			 *
			 * Example $user_data:
			 * array(
			 *   'email'            => 'user@example.edu',
			 *   'username'         => 'user',
			 *   'first_name'       => 'First',
			 *   'last_name'        => 'Last',
			 *   'authenticated_by' => 'cas',
			 *   'cas_attributes'   => array( ... ),
			 * );
			 */
			do_action('basgate_user_register', $user, $user_data);
			return $user;
		}

		// Sanity check: if we made it here without returning, something has gone wrong.
		return new \WP_Error('invalid_login', __('Invalid login attempted.', BasgateConstants::ID));
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
		if ('google' === self::$authenticated_by && array_key_exists('basToken', $_SESSION)) {
			$token = $_SESSION['basToken'];

			$auth_settings['bas_client_id'] = apply_filters('basgate_client_id', $auth_settings['bas_client_id']);
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
			unset($_SESSION['basToken']);
		}
	}
}

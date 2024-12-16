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
		Helper::basgate_log('===== STARTED () custom_authenticate');

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
		$authenticated_by                = '';
		$result                          = null;

		// Try Basgate authentication if it's enabled and we don't have a
		// successful login yet.
		if ('yes' === $auth_settings['enabled'] && ! is_wp_error($result)) {
			Helper::basgate_log('===== custom_authenticate() enabled=yes');
			$result = $this->custom_authenticate_basgate($auth_settings);
			if (! is_null($result) && ! is_wp_error($result)) {
				if (is_array($result['email'])) {
					$externally_authenticated_emails = $result['email'];
				} else {
					$externally_authenticated_emails[] = $result['email'];
				}
				$authenticated_by = $result['authenticated_by'];
				$openId = $result['open_id'];
				$bas_attributes = $result['bas_attributes'];
				Helper::basgate_log('===== custom_authenticate() open_id :' . $openId);
				$result = $this->check_user_access($user, $result);
			}
		}

		// Fail with message if there was an error creating/adding the user.
		if (is_wp_error($result) || 0 === $result || is_null($result)) {
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
			update_user_meta($user->ID, 'bas_attributes', $bas_attributes);
		}

		$this->set_auth_cookies($user);

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
		Helper::basgate_log("===== STARTED custom_authenticate_basgate():");

		// phpcs:ignore WordPress.Security.NonceVerification
		if (empty($_GET['external']) || 'basgate' !== $_GET['external']) {
			return null;
		}

		//TODO: Add BasScript Loading here


		// Get one time use token.
		session_start();
		if (array_key_exists('basToken', $_SESSION) || array_key_exists('token', $_SESSION)) {
			$token =  sanitize_text_field($_SESSION['basToken']);
			Helper::basgate_log('custom_authenticate_basgate() exist $token:' . $token);
		} else {
			// No token, so this is not a succesful Basgate login.
			Helper::basgate_log('custom_authenticate_basgate() ERROR $token not Exists');
			return new \WP_Error('invalid_basgate_login', __('You are not Basgate.', 'bassdk-login'));
		}

		// Verify this is a successful Basgate authentication.
		try {
			$payload = $this->getBasUserInfo($token);
			Helper::basgate_log('custom_authenticate_basgate() $payload:' . wp_json_encode($payload));
		} catch (\Throwable $th) {
			Helper::basgate_log('ERROR custom_authenticate_basgate :Error on getting userinfo from Basgate API.' . $th->getMessage());
			return new \WP_Error('invalid_basgate_login', __('Error on getting userinfo from Basgate API.', 'bassdk-login'), $th->getMessage());
		}

		// Invalid ticket, so this in not a successful Basgate login.
		if (array_key_exists("status", $payload) && "0" === $payload['status']) {
			Helper::basgate_log('custom_authenticate_basgate Invalid Basgate credentials provided.');
			return new \WP_Error('invalid_basgate_login', __('Invalid Basgate credentials provided.', 'bassdk-login'));
		}

		$data = $payload['data'];

		$username     = $data['user_name'];
		$name     = $data['name'];
		$openId     = $data['open_id'];
		$phone     = $data['phone'];

		Helper::basgate_log('===== custom_authenticate() $data: ' . wp_json_encode($data));

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
		Helper::basgate_log('===== STARTED ajax_process_basgate_login() ');

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
			$auth_id = isset($_POST['authId']) ? sanitize_text_field(wp_unslash($_POST['authId'])) : null;
		}
		// Grab plugin settings.
		$options       = Options::get_instance();
		$auth_settings = $options->get_all(Helper::SINGLE_CONTEXT, 'allow override');

		$auth_settings['bas_client_id'] = apply_filters('basgate_client_id', $auth_settings['bas_client_id']);
		$auth_settings['bas_client_secret'] = apply_filters('basgate_client_secret', $auth_settings['bas_client_secret']);



		if (empty($auth_id)) {
			die('');
			return null;
		}

		//TODO: Add basgate backend request for token and userinfo
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
		Helper::basgate_log("===== STARTED getBasToken auth_id: $auth_id");
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
			$reqBody = [
				'grant_type' => $grant_type,
				'client_id' => $client_id,
				'client_secret' => $client_secret,
				'code' => $code,
				'redirect_uri' => $redirect_uri
			];

			$header = array("Content-type" => "application/x-www-form-urlencoded");

			$retry = 1;
			do {

				$response = Helper::executecUrl($bassdk_api . 'api/v1/auth/token', http_build_query($reqBody), "POST", $header);
				$retry++;
			} while (!$response['success'] && $retry < BasgateConstants::MAX_RETRY_COUNT);
			Helper::basgate_log("getBasToken response:" . wp_json_encode($response));
			if (array_key_exists('success', $response) && $response['success'] == true) {
				if (array_key_exists('body', $response)) {
					$data = $response['body'];
					return  array_key_exists('access_token', $data) ? $data['access_token'] : null;
				} else {
					return null;
				}
			} else {
				return null;
			}
		} catch (\Throwable $th) {
			throw $th;
		}
	}

	//Process Basgate get UserInfo
	public function getBasUserInfo($token)
	{
		Helper::basgate_log('===== STARTED getBasUserInfo() $token: ' . $token);
		$options       = Options::get_instance();
		$auth_settings = $options->get_all(Helper::SINGLE_CONTEXT, 'allow override');
		if (array_key_exists('bas_environment', $auth_settings) && $auth_settings["bas_environment"] === "1") {
			$bassdk_api =  BasgateConstants::PRODUCTION_HOST;
		} else {
			$bassdk_api = BasgateConstants::STAGING_HOST;
		}

		try {
			$header = array("Content-type" => "application/json", "Authorization" => "Bearer " . $token);
			$retry = 1;
			do {
				$response = Helper::executecUrl($bassdk_api . 'api/v1/auth/userinfo', array(), "GET", $header);
				$retry++;
				Helper::basgate_log('===== getBasUserInfo() $response: ' . wp_json_encode($response));
			} while (!$response['body'] && $retry < BasgateConstants::MAX_RETRY_COUNT);

			if (array_key_exists('success', $response) && $response['success'] == true) {
				if (array_key_exists('body', $response)) {
					$body = $response['body'];
					if (array_key_exists('data', $body)) {
						return $body;
					} else {
						return null;
					}
				} else {
					return null;
				}
			} else {
				return null;
			}
		} catch (\Throwable $th) {
			$msg = "ERROR getBasUserInfo():" . $th->getMessage();
			Helper::basgate_log($msg);
			// error_log($msg);
			throw $th;
		}
	}

	public function check_user_access($user, $user_data = array())
	{
		Helper::basgate_log('===== STARTED check_user_access()');
		// Grab plugin settings.
		$options                                    = Options::get_instance();
		$auth_settings                              = $options->get_all(Helper::SINGLE_CONTEXT, 'allow override');

		if (is_null($user_data)) {
			Helper::basgate_log('check_user_access is_null($user_data)==true Invalid login attempted.');
			return new \WP_Error('invalid_login', __('Invalid login attempted.', 'bassdk-login'));
		}

		// If the approved external user does not have a WordPress account, create it.
		if (!$user && !is_wp_error($user_data)) {
			if (array_key_exists('username', $user_data)) {
				$username = $user_data['username'];
			} else {
				$username = explode('@', $user_data['email']);
				$username = $username[0];
			}

			if (get_user_by('login', $username) !== false) {
				$user = get_user_by('login', $username);
				do_action('basgate_user_logged_in', $user, $user_data);
			} else {
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
			}

			return $user;
		} else if (is_wp_error($user_data)) {
			return $user_data;
		}

		// Sanity check: if we made it here without returning, something has gone wrong.
		return new \WP_Error('invalid_login', __('Invalid login attempted.', 'bassdk-login'));
	}

	/**
	 * Set auth cookies for WordPress login.
	 *
	 * @param WP_User $user WP User object.
	 *
	 * @return void
	 */
	public function set_auth_cookies(\WP_User $user)
	{
		Helper::basgate_log('===== STARTED set_auth_cookies() ');

		wp_clear_auth_cookie();
		wp_set_current_user($user->ID, $user->user_login);
		wp_set_auth_cookie($user->ID);
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
		// if (empty(self::$authenticated_by) && ! empty($_REQUEST['external'])) {
		// 	self::$authenticated_by = sanitize_text_field(wp_unslash($_REQUEST['external']));
		// }
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
			$token = sanitize_text_field($_SESSION['basToken']);

			$auth_settings['bas_client_id'] = apply_filters('basgate_client_id', $auth_settings['bas_client_id']);
			$auth_settings['bas_client_secret'] = apply_filters('basgate_client_secret', $auth_settings['bas_client_secret']);

			unset($_SESSION['basToken']);
		}
	}
}

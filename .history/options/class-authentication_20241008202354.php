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
			alert("Logined Successfully inside custom_authenticate() ")
		</script>
<?php
		// Pass through if already authenticated.
		if (is_a($user, 'WP_User')) {
			return $user;
		} else {
			$user = null;
		}

		// If username and password are blank, this isn't a log in attempt.
		$is_login_attempt = strlen($username) > 0 && strlen($password) > 0;

		// Check to make sure that $username is not locked out due to too
		// many invalid login attempts. If it is, tell the user how much
		// time remains until they can try again.
		$unauthenticated_user            = $is_login_attempt ? get_user_by('login', $username) : false;
		$unauthenticated_user_is_blocked = false;
		if ($is_login_attempt && false !== $unauthenticated_user) {
			$last_attempt = get_user_meta($unauthenticated_user->ID, 'auth_settings_advanced_lockouts_time_last_failed', true);
			$num_attempts = get_user_meta($unauthenticated_user->ID, 'auth_settings_advanced_lockouts_failed_attempts', true);
			// Also check the auth_blocked user_meta flag (users in blocked list will get this flag).
			$unauthenticated_user_is_blocked = get_user_meta($unauthenticated_user->ID, 'auth_blocked', true) === 'yes';
		} else {
			$last_attempt = get_option('auth_settings_advanced_lockouts_time_last_failed');
			$num_attempts = get_option('auth_settings_advanced_lockouts_failed_attempts');
		}

		// Inactive users should be treated like deleted users (we just
		// do this to preserve any content they created, but here we should
		// pretend they don't exist).
		if ($unauthenticated_user_is_blocked) {
			remove_filter('authenticate', 'wp_authenticate_username_password', 20, 3);
			remove_filter('authenticate', 'wp_authenticate_email_password', 20, 3);
			return new \WP_Error('empty_password', __('<strong>ERROR</strong>: Incorrect username or password.', 'authorizer'));
		}

		// Grab plugin settings.
		$options       = Options::get_instance();
		$auth_settings = $options->get_all(Helper::SINGLE_CONTEXT, 'allow override');

		// Make sure $last_attempt (time) and $num_attempts are positive integers.
		// Note: this addresses resetting them if either is unset from above.
		$last_attempt = absint($last_attempt);
		$num_attempts = absint($num_attempts);

		// Create semantic lockout variables.
		$lockouts                        = $auth_settings['advanced_lockouts'];
		$time_since_last_fail            = time() - $last_attempt;
		$reset_duration                  = absint($lockouts['reset_duration']) * 60; // minutes to seconds.
		$num_attempts_long_lockout       = absint($lockouts['attempts_1']) + absint($lockouts['attempts_2']);
		$num_attempts_short_lockout      = absint($lockouts['attempts_1']);
		$seconds_remaining_long_lockout  = absint($lockouts['duration_2']) * 60 - $time_since_last_fail;
		$seconds_remaining_short_lockout = absint($lockouts['duration_1']) * 60 - $time_since_last_fail;

		// Check if we need to institute a lockout delay.
		if ($is_login_attempt && $time_since_last_fail > $reset_duration) {
			// Enough time has passed since the last invalid attempt and
			// now that we can reset the failed attempt count, and let this
			// login attempt go through.
			$num_attempts = 0; // This does nothing, but include it for semantic meaning.
		} elseif ($is_login_attempt && $num_attempts > $num_attempts_long_lockout && $seconds_remaining_long_lockout > 0) {
			// Stronger lockout (1st/2nd round of invalid attempts reached)
			// Note: set the error code to 'empty_password' so it doesn't
			// trigger the wp_login_failed hook, which would continue to
			// increment the failed attempt count.
			remove_filter('authenticate', 'wp_authenticate_username_password', 20, 3);
			remove_filter('authenticate', 'wp_authenticate_email_password', 20, 3);
			return new \WP_Error(
				'empty_password',
				sprintf(
					/* TRANSLATORS: 1: username 2: duration of lockout in seconds 3: duration of lockout as a phrase 4: lost password URL */
					__('<strong>ERROR</strong>: There have been too many invalid login attempts for the username <strong>%1$s</strong>. Please wait <strong id="seconds_remaining" data-seconds="%2$s">%3$s</strong> before trying again. <a href="%4$s" title="Password Lost and Found">Lost your password</a>?', 'authorizer'),
					$username,
					$seconds_remaining_long_lockout,
					Helper::seconds_as_sentence($seconds_remaining_long_lockout),
					wp_lostpassword_url()
				)
			);
		} elseif ($is_login_attempt && $num_attempts > $num_attempts_short_lockout && $seconds_remaining_short_lockout > 0) {
			// Normal lockout (1st round of invalid attempts reached)
			// Note: set the error code to 'empty_password' so it doesn't
			// trigger the wp_login_failed hook, which would continue to
			// increment the failed attempt count.
			remove_filter('authenticate', 'wp_authenticate_username_password', 20, 3);
			remove_filter('authenticate', 'wp_authenticate_email_password', 20, 3);
			return new \WP_Error(
				'empty_password',
				sprintf(
					/* TRANSLATORS: 1: username 2: duration of lockout in seconds 3: duration of lockout as a phrase 4: lost password URL */
					__('<strong>ERROR</strong>: There have been too many invalid login attempts for the username <strong>%1$s</strong>. Please wait <strong id="seconds_remaining" data-seconds="%2$s">%3$s</strong> before trying again. <a href="%4$s" title="Password Lost and Found">Lost your password</a>?', 'authorizer'),
					$username,
					$seconds_remaining_short_lockout,
					Helper::seconds_as_sentence($seconds_remaining_short_lockout),
					wp_lostpassword_url()
				)
			);
		}

		// Start external authentication.
		$externally_authenticated_emails = array();
		$authenticated_by                = '';
		$result                          = null;

		// Try Google authentication if it's enabled and we don't have a
		// successful login yet.
		if (
			'1' === $auth_settings['bas_enabled'] &&
			0 === count($externally_authenticated_emails) &&
			! is_wp_error($result)
		) {
			$result = $this->custom_authenticate_basgate($auth_settings);
			if (! is_null($result) && ! is_wp_error($result)) {
				if (is_array($result['email'])) {
					$externally_authenticated_emails = $result['email'];
				} else {
					$externally_authenticated_emails[] = $result['email'];
				}
				$authenticated_by = $result['authenticated_by'];
			}
		}

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

		/**
		 * If we've made it this far, we should have an externally
		 * authenticated user. The following should be set:
		 *   $externally_authenticated_emails
		 *   $authenticated_by
		 */

		// Look for an existing WordPress account matching the externally
		// authenticated user. Perform the match either by username or email.
		if (isset($auth_settings['cas_link_on_username']) && 1 === intval($auth_settings['cas_link_on_username'])) {
			// Get the external user's WordPress account by username. This is less
			// secure, but a user reported having an installation where a previous
			// CAS plugin had created over 9000 WordPress accounts without email
			// addresses. This option was created to support that case, and any
			// other CAS servers where emails are not used as account identifiers.
			$user = get_user_by('login', $result['username']);
		} else {
			// Get the external user's WordPress account by email address. This is
			// the normal behavior (and the most secure).
			foreach ($externally_authenticated_emails as $externally_authenticated_email) {
				$user = get_user_by('email', Helper::lowercase($externally_authenticated_email));
				// Stop trying email addresses once we have found a match.
				if (false !== $user) {
					break;
				}
			}
		}

		// We'll track how this user was authenticated in user meta.
		if ($user) {
			update_user_meta($user->ID, 'authenticated_by', $authenticated_by);
		}

		// Check this external user's access against the access lists
		// (pending, approved, blocked).
		$result = Authorization::get_instance()->check_user_access($user, $externally_authenticated_emails, $result);

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
	 * Verify the Google login and set a session token.
	 *
	 * Flow: "Sign in with Google" button clicked; JS Google library
	 * called; JS function signInCallback() fired with results from Google;
	 * signInCallback() posts code and nonce (via AJAX) to this function;
	 * This function checks the token using the Google PHP library, and
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

?>
		<script>
			alert("Logined Successfully inside ajax_process_basgate_login() ")
		</script>
<?php
		// Nonce check.
		if (
			! isset( $_POST['nonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'basgate_login_nonce' )
		) {
			die( '' );
		}

		// Google authentication token.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$id_token = isset($_POST['data']) ? wp_unslash($_POST['data']) : null;

		// Grab plugin settings.
		$options       = Options::get_instance();
		$auth_settings = $options->get_all(Helper::SINGLE_CONTEXT, 'allow override');

		// Fetch the Google Client ID (allow overrides from filter or constant).
		// if ( defined( 'AUTHORIZER_GOOGLE_CLIENT_ID' ) ) {
		// 	$auth_settings['bas_client_id'] = \AUTHORIZER_GOOGLE_CLIENT_ID;
		// }
		/**
		 * Filters the Google Client ID used by Authorizer to authenticate.
		 *
		 * @since 3.9.0
		 *
		 * @param string $google_client_id  The stored Google Client ID.
		 */
		// $auth_settings['bas_client_id'] = apply_filters( 'authorizer_basgate_client_id', $auth_settings['bas_client_id'] );

		// Fetch the Google Client Secret (allow overrides from filter or constant).
		// if ( defined( 'AUTHORIZER_GOOGLE_CLIENT_SECRET' ) ) {
		// 	$auth_settings['bas_client_secret'] = \AUTHORIZER_GOOGLE_CLIENT_SECRET;
		// }
		/**
		 * Filters the Google Client Secret used by Authorizer to authenticate.
		 *
		 * @since 3.6.1
		 *
		 * @param string $google_client_secret  The stored Google Client Secret.
		 */
		// $auth_settings['bas_client_secret'] = apply_filters( 'authorizer_google_client_secret', $auth_settings['bas_client_secret'] );

		// Build the Google Client.
		// $client = new \Google_Client();
		// $client->setApplicationName( 'WordPress' );
		// $client->setClientId( trim( $auth_settings['bas_client_id'] ) );
		// $client->setClientSecret( trim( $auth_settings['bas_client_secret'] ) );
		// $client->setRedirectUri( 'postmessage' );

		// /**
		//  * If the hosted domain parameter is set, restrict logins to that domain
		//  * (only available in google-api-php-client v2 or higher).
		//  */
		// if (
		// 	array_key_exists( 'google_hosteddomain', $auth_settings ) &&
		// 	strlen( $auth_settings['google_hosteddomain'] ) > 0 &&
		// 	$client::LIBVER >= '2.0.0'
		// ) {
		// 	$google_hosteddomains = explode( "\n", str_replace( "\r", '', $auth_settings['google_hosteddomain'] ) );
		// 	$google_hosteddomain  = trim( $google_hosteddomains[0] );
		// 	$client->setHostedDomain( $google_hosteddomain );
		// }

		// Store the token (for verifying later in wp-login).
		session_start();
		if (empty($_SESSION['token'])) {
			// Store the token in the session for later use.
			$_SESSION['token'] = $id_token;

			$response = 'Successfully authenticated.';
		} else {
			$response = 'Already authenticated.';
		}

		die(esc_html($response));
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
		// Move on if Google auth hasn't been requested here.
		// phpcs:ignore WordPress.Security.NonceVerification
		if (empty($_GET['external']) || 'basgate' !== $_GET['external']) {
			return null;
		}

		// Get one time use token.
		session_start();
		$token = array_key_exists('token', $_SESSION) ? $_SESSION['token'] : null;

		// No token, so this is not a succesful Google login.
		if (empty($token)) {
			return null;
		}

		// Fetch the Google Client ID (allow overrides from filter or constant).
		if (defined('AUTHORIZER_GOOGLE_CLIENT_ID')) {
			// $auth_settings['bas_client_id'] = \AUTHORIZER_GOOGLE_CLIENT_ID;
		}
		/**
		 * Filters the Google Client ID used by Authorizer to authenticate.
		 *
		 * @since 3.9.0
		 *
		 * @param string $google_client_id  The stored Google Client ID.
		 */
		$auth_settings['bas_client_id'] = apply_filters('authorizer_google_client_id', $auth_settings['bas_client_id']);

		// Fetch the Google Client Secret (allow overrides from filter or constant).
		if (defined('AUTHORIZER_GOOGLE_CLIENT_SECRET')) {
			// $auth_settings['bas_client_secret'] = \AUTHORIZER_GOOGLE_CLIENT_SECRET;
		}
		/**
		 * Filters the Google Client Secret used by Authorizer to authenticate.
		 *
		 * @since 3.6.1
		 *
		 * @param string $google_client_secret  The stored Google Client Secret.
		 */
		$auth_settings['bas_client_secret'] = apply_filters('authorizer_google_client_secret', $auth_settings['bas_client_secret']);

		// Build the Google Client.
		// $client = new \Google_Client();
		// $client->setApplicationName('WordPress');
		// $client->setClientId(trim($auth_settings['bas_client_id']));
		// $client->setClientSecret(trim($auth_settings['bas_client_secret']));
		// $client->setRedirectUri('postmessage');

		/**
		 * If the hosted domain parameter is set, restrict logins to that domain
		 * (only available in google-api-php-client v2 or higher).
		 */
		if (
			array_key_exists('google_hosteddomain', $auth_settings) &&
			strlen($auth_settings['google_hosteddomain']) > 0
			// &&
			// $client::LIBVER >= '2.0.0'
		) {
			$google_hosteddomains = explode("\n", str_replace("\r", '', $auth_settings['google_hosteddomain']));
			$google_hosteddomain  = trim($google_hosteddomains[0]);
			// $client->setHostedDomain($google_hosteddomain);
		}

		// // Allow minor clock drift between this server's clock and Google's.
		// // See: https://github.com/googleapis/google-api-php-client/issues/1630
		// \Firebase\JWT\JWT::$leeway = 30;

		// Verify this is a successful Google authentication.
		// try {
		$payload = ''; //$client->verifyIdToken($token);
		// } catch (\Firebase\JWT\BeforeValidException $e) {
		// 	// Server clock out of sync with Google servers.
		// 	return new \WP_Error('invalid_google_login', __('The authentication timestamp is too old, please try again.', 'authorizer'));
		// } catch (Google_Auth_Exception $e) {
		// 	// Invalid ticket, so this in not a successful Google login.
		// 	return new \WP_Error('invalid_google_login', __('Invalid Google credentials provided.', 'authorizer'));
		// }

		// Invalid ticket, so this in not a successful Google login.
		if (empty($payload['email'])) {
			return new \WP_Error('invalid_google_login', __('Invalid Google credentials provided.', 'authorizer'));
		}

		// Get email address.
		$email = Helper::lowercase($payload['email']);

		$email_domain = substr(strrchr($email, '@'), 1);
		$username     = current(explode('@', $email));

		/**
		 * Fail if hd param is set and the logging in user's email address doesn't
		 * match the allowed hosted domain.
		 *
		 * See: https://developers.google.com/identity/protocols/OpenIDConnect#hd-param
		 * See: https://github.com/google/google-api-php-client/blob/v1-master/src/Google/Client.php#L407-L416
		 *
		 * Note: this is a failsafe if the setHostedDomain() feature in v2 does not work above.
		 */
		if (
			array_key_exists('google_hosteddomain', $auth_settings) &&
			strlen($auth_settings['google_hosteddomain']) > 0
		) {
			// Allow multiple whitelisted domains.
			$google_hosteddomains = explode("\n", str_replace("\r", '', $auth_settings['google_hosteddomain']));
			if (! in_array($email_domain, $google_hosteddomains, true)) {
				$this->custom_logout();
				return new \WP_Error('invalid_google_login', __('Google credentials do not match the allowed hosted domain', 'authorizer'));
			}
		}

		return array(
			'email'             => $email,
			'username'          => $username,
			'first_name'        => '',
			'last_name'         => '',
			'authenticated_by'  => 'google',
			'google_attributes' => $payload,
		);
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


		// If session token set, log out of Google.
		if (session_id() === '') {
			session_start();
		}
		if ('google' === self::$authenticated_by && array_key_exists('token', $_SESSION)) {
			$token = $_SESSION['token'];

			// Fetch the Google Client ID (allow overrides from filter or constant).
			if (defined('AUTHORIZER_GOOGLE_CLIENT_ID')) {
				// $auth_settings['bas_client_id'] = \AUTHORIZER_GOOGLE_CLIENT_ID;
			}
			/**
			 * Filters the Google Client ID used by Authorizer to authenticate.
			 *
			 * @since 3.9.0
			 *
			 * @param string $google_client_id  The stored Google Client ID.
			 */
			$auth_settings['bas_client_id'] = apply_filters('authorizer_google_client_id', $auth_settings['bas_client_id']);

			// Fetch the Google Client Secret (allow overrides from filter or constant).
			if (defined('AUTHORIZER_GOOGLE_CLIENT_SECRET')) {
				// $auth_settings['bas_client_secret'] = \AUTHORIZER_GOOGLE_CLIENT_SECRET;
			}
			/**
			 * Filters the Google Client Secret used by Authorizer to authenticate.
			 *
			 * @since 3.6.1
			 *
			 * @param string $google_client_secret  The stored Google Client Secret.
			 */
			$auth_settings['bas_client_secret'] = apply_filters('authorizer_google_client_secret', $auth_settings['bas_client_secret']);

			// Build the Google Client.
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

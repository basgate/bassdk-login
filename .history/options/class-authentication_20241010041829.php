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
			return null;
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
			'email'             => $phone,
			'username'          => $username,
			'first_name'        => $name,
			'last_name'         => '',
			'open_id'         => $openId,
			'authenticated_by'  => 'basgate',
			'bas_attributes' => $data,
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

	
	/**
	 * This function will fail with a wp_die() message to the user if they
	 * don't have access.
	 *
	 * @param WP_User $user        User to check.
	 * @param array   $user_emails Array of user's plaintext emails (in case current user doesn't have a WP account).
	 * @param array   $user_data   Array of keys for email, username, first_name, last_name,
	 *                             authenticated_by, google_attributes, cas_attributes, ldap_attributes,
	 *                             oauth2_attributes.
	 * @return WP_Error|WP_User
	 *                             WP_Error if there was an error on user creation / adding user to blog.
	 *                             WP_Error / wp_die() if user does not have access.
	 *                             WP_User if user has access.
	 */
	public function check_user_access( $user, $user_emails, $user_data = array() ) {
		// Grab plugin settings.
		$options                                    = Options::get_instance();
		$auth_settings                              = $options->get_all( Helper::SINGLE_CONTEXT, 'allow override' );
		$auth_settings_access_users_pending         = $options->sanitize_user_list(
			$options->get( 'access_users_pending', Helper::SINGLE_CONTEXT )
		);
		$auth_settings_access_users_approved_single = $options->get( 'access_users_approved', Helper::SINGLE_CONTEXT );
		$auth_settings_access_users_approved_multi  = $options->get( 'access_users_approved', Helper::NETWORK_CONTEXT );
		$auth_settings_access_users_approved        = $options->sanitize_user_list(
			array_merge(
				$auth_settings_access_users_approved_single,
				$auth_settings_access_users_approved_multi
			)
		);

		// Detect whether this user's first and last name should be updated below
		// (if the external CAS/LDAP service provides a different value, the option
		// is set to update it, and it's empty if the option to only set it if empty
		// is enabled).
		$should_update_first_name =
			$user && ! empty( $user_data['first_name'] ) && $user_data['first_name'] !== $user->first_name &&
			(
				(
					! empty( $user_data['authenticated_by'] ) && 'cas' === $user_data['authenticated_by'] &&
					! empty( $auth_settings['cas_attr_update_on_login'] ) &&
					( '1' === $auth_settings['cas_attr_update_on_login'] || ( 'update-if-empty' === $auth_settings['cas_attr_update_on_login'] && empty( $user->first_name ) ) )
				) || (
					! empty( $user_data['authenticated_by'] ) && 'ldap' === $user_data['authenticated_by'] &&
					! empty( $auth_settings['ldap_attr_update_on_login'] ) &&
					( '1' === $auth_settings['ldap_attr_update_on_login'] || ( 'update-if-empty' === $auth_settings['ldap_attr_update_on_login'] && empty( $user->first_name ) ) )
				) || (
					! empty( $user_data['authenticated_by'] ) && 'oauth2' === $user_data['authenticated_by'] &&
					! empty( $auth_settings['oauth2_attr_update_on_login'] ) &&
					( '1' === $auth_settings['oauth2_attr_update_on_login'] || ( 'update-if-empty' === $auth_settings['oauth2_attr_update_on_login'] && empty( $user->first_name ) ) )
				)
			);

		$should_update_last_name =
			$user && ! empty( $user_data['last_name'] ) && $user_data['last_name'] !== $user->last_name &&
			(
				(
					! empty( $user_data['authenticated_by'] ) && 'cas' === $user_data['authenticated_by'] &&
					! empty( $auth_settings['cas_attr_update_on_login'] ) &&
					( '1' === $auth_settings['cas_attr_update_on_login'] || ( 'update-if-empty' === $auth_settings['cas_attr_update_on_login'] && empty( $user->last_name ) ) )
				) || (
					! empty( $user_data['authenticated_by'] ) && 'ldap' === $user_data['authenticated_by'] &&
					! empty( $auth_settings['ldap_attr_update_on_login'] ) &&
					( '1' === $auth_settings['ldap_attr_update_on_login'] || ( 'update-if-empty' === $auth_settings['ldap_attr_update_on_login'] && empty( $user->last_name ) ) )
				) || (
					! empty( $user_data['authenticated_by'] ) && 'oauth2' === $user_data['authenticated_by'] &&
					! empty( $auth_settings['oauth2_attr_update_on_login'] ) &&
					( '1' === $auth_settings['oauth2_attr_update_on_login'] || ( 'update-if-empty' === $auth_settings['oauth2_attr_update_on_login'] && empty( $user->last_name ) ) )
				)
			);

		/**
		 * Filter whether to block the currently logging in user based on any of
		 * their user attributes.
		 *
		 * @param bool $allow_login Whether to block the currently logging in user.
		 * @param array $user_data User data returned from external service.
		 */
		$allow_login       = apply_filters( 'authorizer_allow_login', true, $user_data );
		$blocked_by_filter = ! $allow_login; // Use this for better readability.

		// Check our externally authenticated user against the block list.
		// If any of their email addresses are blocked, set the relevant user
		// meta field, and show them an error screen.
		foreach ( $user_emails as $user_email ) {
			if ( $blocked_by_filter || $this->is_email_in_list( $user_email, 'blocked' ) ) {

				// Add user to blocked list if it was blocked via the filter.
				if ( $blocked_by_filter && ! $this->is_email_in_list( $user_email, 'blocked' ) ) {
					$auth_settings_access_users_blocked = $options->sanitize_user_list(
						$options->get( 'access_users_blocked', Helper::SINGLE_CONTEXT )
					);
					array_push(
						$auth_settings_access_users_blocked,
						array(
							'email'      => Helper::lowercase( $user_email ),
							'date_added' => wp_date( 'M Y' ),
						)
					);
					update_option( 'auth_settings_access_users_blocked', $auth_settings_access_users_blocked );
				}

				// If the blocked external user has a WordPress account, mark it as
				// blocked (enforce block in this->authenticate()).
				if ( $user ) {
					update_user_meta( $user->ID, 'auth_blocked', 'yes' );
				}

				// Notify user about blocked status and return without authenticating them.
				// phpcs:ignore WordPress.Security.NonceVerification
				$redirect_to = ! empty( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : home_url();
				$page_title  = sprintf(
					/* TRANSLATORS: %s: Name of blog */
					__( '%s - Access Restricted', 'authorizer' ),
					get_bloginfo( 'name' )
				);
				$error_message =
					apply_filters( 'the_content', $auth_settings['access_blocked_redirect_to_message'] ) .
					'<hr />' .
					'<p style="text-align: center;">' .
					'<a class="button" href="' . wp_logout_url( $redirect_to ) . '">' .
					__( 'Back', 'authorizer' ) .
					'</a></p>';
				update_option( 'auth_settings_advanced_login_error', $error_message );
				wp_die( wp_kses( $error_message, Helper::$allowed_html ), esc_html( $page_title ) );
				return new \WP_Error( 'invalid_login', __( 'Invalid login attempted.', 'authorizer' ) );
			}
		}

		// Get the default role for this user (or their current role, if they
		// already have an account).
		$default_role = $user && is_array( $user->roles ) && count( $user->roles ) > 0 ? $user->roles[0] : $auth_settings['access_default_role'];
		/**
		 * Filter the role of the user currently logging in. The role will be
		 * set to the default (specified in Authorizer options) for new users,
		 * or the user's current role for existing users. This filter allows
		 * changing user roles based on custom CAS/LDAP attributes.
		 *
		 * @param bool $role Role of the user currently logging in.
		 * @param array $user_data User data returned from external service.
		 * @param WP_User|false|null|WP_Error $user User object if logging in user exists.
		 */
		$approved_role = apply_filters( 'authorizer_custom_role', $default_role, $user_data, $user );

		/**
		 * Filter whether to automatically approve the currently logging in user
		 * based on any of their user attributes.
		 *
		 * @param bool  $automatically_approve_login
		 *   Whether to automatically approve the currently logging in user.
		 * @param array $user_data User data returned from external service.
		 */
		$automatically_approve_login = apply_filters( 'authorizer_automatically_approve_login', false, $user_data );

		// If this externally-authenticated user is an existing administrator (admin
		// in single site mode, or super admin in network mode), and isn't blocked,
		// let them in. Update their first/last name if needed (CAS/LDAP).
		if ( $user && is_super_admin( $user->ID ) ) {
			if ( $should_update_first_name ) {
				update_user_meta( $user->ID, 'first_name', $user_data['first_name'] );
			}
			if ( $should_update_last_name ) {
				update_user_meta( $user->ID, 'last_name', $user_data['last_name'] );
			}

			return $user;
		}

		// Iterate through each of the email addresses provided by the external
		// service and determine if any of them have access.
		$last_email = end( $user_emails );
		reset( $user_emails );
		foreach ( $user_emails as $user_email ) {
			$is_newly_approved_user = false;

			// If this externally authenticated user isn't in the approved list
			// and login access is set to "All authenticated users," or if they were
			// automatically approved in the "authorizer_approve_login" filter
			// above, then add them to the approved list (they'll get an account
			// created below if they don't have one yet).
			if (
				! $this->is_email_in_list( $user_email, 'approved' ) &&
				( 'external_users' === $auth_settings['access_who_can_login'] || $automatically_approve_login )
			) {
				$is_newly_approved_user = true;

				// If this user happens to be in the pending list (rare),
				// remove them from pending before adding them to approved.
				if ( $this->is_email_in_list( $user_email, 'pending' ) ) {
					foreach ( $auth_settings_access_users_pending as $key => $pending_user ) {
						if ( 0 === strcasecmp( $pending_user['email'], $user_email ) ) {
							unset( $auth_settings_access_users_pending[ $key ] );
							update_option( 'auth_settings_access_users_pending', $auth_settings_access_users_pending );
							break;
						}
					}
				}

				// Add this user to the approved list.
				$approved_user = array(
					'email'      => Helper::lowercase( $user_email ),
					'role'       => $approved_role,
					'date_added' => wp_date( 'Y-m-d H:i:s' ),
				);
				array_push( $auth_settings_access_users_approved, $approved_user );
				array_push( $auth_settings_access_users_approved_single, $approved_user );
				update_option( 'auth_settings_access_users_approved', $auth_settings_access_users_approved_single );
			}

			// Check our externally authenticated user against the approved
			// list. If they are approved, log them in (and create their account
			// if necessary).
			if ( $is_newly_approved_user || $this->is_email_in_list( $user_email, 'approved' ) ) {
				$user_info = $is_newly_approved_user ? $approved_user : Helper::get_user_info_from_list( $user_email, $auth_settings_access_users_approved );

				// If this user's role was modified above (in the authorizer_custom_role
				// filter), update the role in the approved list and use that role
				// (i.e., if the roles are out of sync, use the authorizer_custom_role
				// value instead of the role in the approved list).
				if ( has_filter( 'authorizer_custom_role' ) ) {
					$user_info['role'] = $approved_role;

					// Find the user in either the single site or multisite approved list
					// and update their role there if different.
					foreach ( $auth_settings_access_users_approved_single as $index => $auth_settings_access_user_approved_single ) {
						if ( $user_info['email'] === $auth_settings_access_user_approved_single['email'] ) {
							if ( $auth_settings_access_users_approved_single[ $index ]['role'] !== $approved_role ) {
								$auth_settings_access_users_approved_single[ $index ]['role'] = $approved_role;
								update_option( 'auth_settings_access_users_approved', $auth_settings_access_users_approved_single );
							}
							break;
						}
					}
					if ( is_multisite() ) {
						foreach ( $auth_settings_access_users_approved_multi as $index => $auth_settings_access_user_approved_multi ) {
							if ( $user_info['email'] === $auth_settings_access_user_approved_multi['email'] ) {
								if ( $auth_settings_access_users_approved_multi[ $index ]['role'] !== $approved_role ) {
									$auth_settings_access_users_approved_multi[ $index ]['role'] = $approved_role;
									update_blog_option( get_main_site_id( get_main_network_id() ), 'auth_multisite_settings_access_users_approved', $auth_settings_access_users_approved_multi );
								}
								break;
							}
						}
					}
				}

				// If the approved external user does not have a WordPress account, create it.
				if ( ! $user ) {
					if ( array_key_exists( 'username', $user_data ) ) {
						$username = $user_data['username'];
					} else {
						$username = explode( '@', $user_info['email'] );
						$username = $username[0];
					}
					// If there's already a user with this username (e.g.,
					// johndoe/johndoe@gmail.com exists, and we're trying to add
					// johndoe/johndoe@example.com), use the full email address
					// as the username.
					if ( get_user_by( 'login', $username ) !== false ) {
						$username = $user_info['email'];
					}
					$result = wp_insert_user(
						array(
							'user_login'      => strtolower( $username ),
							'user_pass'       => wp_generate_password(), // random password.
							'first_name'      => array_key_exists( 'first_name', $user_data ) ? $user_data['first_name'] : '',
							'last_name'       => array_key_exists( 'last_name', $user_data ) ? $user_data['last_name'] : '',
							'user_email'      => Helper::lowercase( $user_info['email'] ),
							'user_registered' => wp_date( 'Y-m-d H:i:s' ),
							'role'            => $user_info['role'],
						)
					);

					// Fail with message if error.
					if ( is_wp_error( $result ) || 0 === $result ) {
						return $result;
					}

					// Authenticate as new user.
					$user = new \WP_User( $result );

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
					do_action( 'authorizer_user_register', $user, $user_data );

					// If multisite, iterate through all sites in the network and add the user
					// currently logging in to any of them that have the user on the approved list.
					// Note: this is useful for first-time logins--some users will have access
					// to multiple sites, and this prevents them from having to log into each
					// site individually to get access.
					if ( is_multisite() ) {
						$site_ids_of_user = array_map(
							function ( $site_of_user ) {
								return intval( $site_of_user->userblog_id );
							},
							get_blogs_of_user( $user->ID )
						);

						// phpcs:ignore WordPress.WP.DeprecatedFunctions.wp_get_sitesFound
						$sites = function_exists( 'get_sites' ) ? get_sites() : wp_get_sites( array( 'limit' => PHP_INT_MAX ) );
						foreach ( $sites as $site ) {
							$blog_id = function_exists( 'get_sites' ) ? $site->blog_id : $site['blog_id'];

							// Skip if user is already added to this site.
							if ( in_array( intval( $blog_id ), $site_ids_of_user, true ) ) {
								continue;
							}

							// Check if user is on the approved list of this site they are not added to.
							$other_auth_settings_access_users_approved = get_blog_option( $blog_id, 'auth_settings_access_users_approved', array() );
							if ( Helper::in_multi_array( $user->user_email, $other_auth_settings_access_users_approved ) ) {
								$other_user_info = Helper::get_user_info_from_list( $user->user_email, $other_auth_settings_access_users_approved );
								// Add user to other site.
								add_user_to_blog( $blog_id, $user->ID, $other_user_info['role'] );
							}
						}
					}

					// Check if this new user has any preassigned usermeta
					// values in their approved list entry, and apply them to
					// their new WordPress account.
					if ( array_key_exists( 'usermeta', $user_info ) && is_array( $user_info['usermeta'] ) ) {
						$meta_key = $options->get( 'advanced_usermeta' );

						if ( array_key_exists( 'meta_key', $user_info['usermeta'] ) && array_key_exists( 'meta_value', $user_info['usermeta'] ) ) {
							// Only update the usermeta if the stored value matches
							// the option set in authorizer settings (if they don't
							// match it's probably old data).
							if ( $meta_key === $user_info['usermeta']['meta_key'] ) {
								// Update user's usermeta value for usermeta key stored in authorizer options.
								if ( strpos( $meta_key, 'acf___' ) === 0 && class_exists( 'acf' ) ) {
									// We have an ACF field value, so use the ACF function to update it.
									update_field( str_replace( 'acf___', '', $meta_key ), $user_info['usermeta']['meta_value'], 'user_' . $user->ID );
								} else {
									// We have a normal usermeta value, so just update it via the WordPress function.
									update_user_meta( $user->ID, $meta_key, $user_info['usermeta']['meta_value'] );
								}
							}
						} elseif ( is_multisite() && count( $user_info['usermeta'] ) > 0 ) {
							// Update usermeta for each multisite blog defined for this user.
							foreach ( $user_info['usermeta'] as $blog_id => $usermeta ) {
								if ( array_key_exists( 'meta_key', $usermeta ) && array_key_exists( 'meta_value', $usermeta ) ) {
									// Add this new user to the blog before we create their user meta (this step typically happens below, but we need it to happen early so we can create user meta here).
									if ( ! is_user_member_of_blog( $user->ID, $blog_id ) ) {
										add_user_to_blog( $blog_id, $user->ID, $user_info['role'] );
									}
									switch_to_blog( $blog_id );
									// Update user's usermeta value for usermeta key stored in authorizer options.
									if ( strpos( $meta_key, 'acf___' ) === 0 && class_exists( 'acf' ) ) {
										// We have an ACF field value, so use the ACF function to update it.
										update_field( str_replace( 'acf___', '', $meta_key ), $usermeta['meta_value'], 'user_' . $user->ID );
									} else {
										// We have a normal usermeta value, so just update it via the WordPress function.
										update_user_meta( $user->ID, $meta_key, $usermeta['meta_value'] );
									}
									restore_current_blog();
								}
							}
						}
					}
				} else {
					// Update first/last name from CAS/LDAP if needed.
					if ( $should_update_first_name ) {
						update_user_meta( $user->ID, 'first_name', $user_data['first_name'] );
					}
					if ( $should_update_last_name ) {
						update_user_meta( $user->ID, 'last_name', $user_data['last_name'] );
					}
				}

				// If this is multisite, add new user to current blog.
				if ( is_multisite() && ! is_user_member_of_blog( $user->ID ) ) {
					$result = add_user_to_blog( get_current_blog_id(), $user->ID, $user_info['role'] );

					// Fail with message if error.
					if ( is_wp_error( $result ) ) {
						return $result;
					}
				}

				// Ensure user has the same role as their entry in the approved list.
				if ( $user_info && ! in_array( $user_info['role'], $user->roles, true ) ) {
					$user->set_role( $user_info['role'] );
				}

				return $user;

			} elseif ( 0 === strcasecmp( $user_email, $last_email ) ) {
				/**
				 * Note: only do this for the last email address we are checking (we need
				 * to iterate through them all to make sure one of them isn't approved).
				 */

				// User isn't an admin, is not blocked, and is not approved.
				// Add them to the pending list and notify them and their instructor.
				if ( strlen( $user_email ) > 0 && ! $this->is_email_in_list( $user_email, 'pending' ) ) {
					$pending_user               = array();
					$pending_user['email']      = Helper::lowercase( $user_email );
					$pending_user['role']       = $approved_role;
					$pending_user['date_added'] = '';
					array_push( $auth_settings_access_users_pending, $pending_user );
					update_option( 'auth_settings_access_users_pending', $auth_settings_access_users_pending );

					// Create strings used in the email notification.
					$site_name              = get_bloginfo( 'name' );
					$site_url               = get_bloginfo( 'url' );
					$authorizer_options_url = 'settings' === $auth_settings['advanced_admin_menu'] ? admin_url( 'options-general.php?page=authorizer' ) : admin_url( '?page=authorizer' );

					// Notify users with the role specified in "Which role should
					// receive email notifications about pending users?".
					if ( strlen( $auth_settings['access_role_receive_pending_emails'] ) > 0 ) {
						foreach ( get_users( array( 'role' => $auth_settings['access_role_receive_pending_emails'] ) ) as $user_recipient ) {
							wp_mail(
								$user_recipient->user_email,
								sprintf(
									/* TRANSLATORS: 1: User email 2: Name of site */
									__( 'Action required: Pending user %1$s at %2$s', 'authorizer' ),
									$pending_user['email'],
									$site_name
								),
								sprintf(
									/* TRANSLATORS: 1: Name of site 2: URL of site 3: URL of authorizer */
									__( "A new user has tried to access the %1\$s site you manage at:\n%2\$s\n\nPlease log in to approve or deny their request:\n%3\$s\n", 'authorizer' ),
									$site_name,
									$site_url,
									$authorizer_options_url
								)
							);
						}
					}
				}

				// Fetch the external service this user authenticated with, and append
				// it to the logout URL below (so we can fire custom logout routines in
				// custom_logout() based on their external service. This is necessary
				// because a pending user does not have a WP_User, and thus no
				// "authenticated_by" usermeta that is normally used to do this.
				$external_param = isset( $user_data['authenticated_by'] ) ? '&external=' . $user_data['authenticated_by'] : '';

				// Notify user about pending status and return without authenticating them.
				// phpcs:ignore WordPress.Security.NonceVerification
				$redirect_to   = ! empty( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : home_url();
				$page_title    = get_bloginfo( 'name' ) . ' - Access Pending';
				$error_message =
					apply_filters( 'the_content', $auth_settings['access_pending_redirect_to_message'] ) .
					'<hr />' .
					'<p style="text-align: center;">' .
					'<a class="button" href="' . wp_logout_url( $redirect_to ) . $external_param . '">' .
					__( 'Back', 'authorizer' ) .
					'</a></p>';
				update_option( 'auth_settings_advanced_login_error', $error_message );
				wp_die( wp_kses( $error_message, Helper::$allowed_html ), esc_html( $page_title ) );
			}
		}

		// Sanity check: if we made it here without returning, something has gone wrong.
		return new \WP_Error( 'invalid_login', __( 'Invalid login attempted.', 'authorizer' ) );
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

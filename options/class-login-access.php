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

/**
 * Contains functions for rendering the Login Access tab in Basgate Settings.
 */
class Login_Access extends Singleton
{

	protected $id = BasgateConstants::ID;


	public function print_section_info_basgate_config($args = '')
	{
?>
		<div id="section_info_access_login" class="section_info">
			<?php wp_nonce_field('save_auth_settings', 'nonce_save_auth_settings'); ?>
			<p><?php esc_html_e('Online payment solutions for all your transactions by Basgate.', $this->id); ?></p>
		</div>
	<?php
	}



	public function print_text_description($args = '')
	{
		// Get plugin option.
		$options              = Options::get_instance();
		$option               = 'bas_description';
		$auth_settings_option = $options->get($option);

		// Print option elements.
	?>
		<textarea rows="3" id="auth_settings_<?php echo esc_attr($option); ?>" name="auth_settings[<?php echo esc_attr($option); ?>]" placeholder='<?php echo esc_html_e(BasgateConstants::DESCRIPTION, $this->id); ?>' style="width:100%">
			<?php esc_html_e($auth_settings_option, $this->id) ?>
		</textarea>
		<br />
		<small>
			<?php echo wp_kses(__('This controls the description which the user sees during checkout.', $this->id), Helper::$allowed_html); ?>
		</small>

	<?php
	}


	public function print_select_environment_mode($args = '')
	{
		// Get plugin option.
		$options              = Options::get_instance();
		$option               = 'bas_environment_mode';
		$auth_settings_option = $options->get($option);

		// Print option elements.
	?>
		<select id="auth_settings_<?php echo esc_attr($option); ?>" name="auth_settings[<?php echo esc_attr($option); ?>]">
			<option value="0" <?php selected($auth_settings_option); ?>><?php esc_html_e("Test/Staging", $this->id); ?></option>
			<option value="1" <?php selected($auth_settings_option); ?>><?php esc_html_e("Production", $this->id); ?></option>
		</select>
		<br />
		<small>
			<?php echo wp_kses(__('Select "Test/Staging" to setup test transactions & "Production" once you are ready to go live', $this->id), Helper::$allowed_html); ?>
		</small>
	<?php
	}


	public function print_text_application_id($args = '')
	{
		// Get plugin option.
		$options              = Options::get_instance();
		$option               = 'bas_application_id';
		$auth_settings_option = $options->get($option);

		// Print option elements.
	?>
		<input type="text" id="auth_settings_<?php echo esc_attr($option); ?>" name="auth_settings[<?php echo esc_attr($option); ?>]" value="<?php echo esc_attr($auth_settings_option); ?>" placeholder='' style="width:400px;" />
		<br />
		<small>
			<?php echo wp_kses(__('Based on the selected Environment Mode, copy the relevant Application ID for test or production environment you received on email.', $this->id), Helper::$allowed_html); ?>
		</small>
	<?php
	}

	public function print_text_merchant_key($args = '')
	{
		// Get plugin option.
		$options              = Options::get_instance();
		$option               = 'bas_merchant_key';
		$auth_settings_option = $options->get($option);

		// Print option elements.
	?>
		<input type="text" id="auth_settings_<?php echo esc_attr($option); ?>" name="auth_settings[<?php echo esc_attr($option); ?>]" value="<?php echo esc_attr($auth_settings_option); ?>" placeholder='' style="width:400px;" />
		<br />
		<small>
			<?php echo wp_kses(__('Based on the selected Environment Mode, copy the Merchant Key for test or production environment you received on email.', $this->id), Helper::$allowed_html); ?>
		</small>
	<?php
	}


	public function print_text_client_id($args = '')
	{
		// Get plugin option.
		$options              = Options::get_instance();
		$option               = 'bas_client_id';
		$auth_settings_option = $options->get($option);

		// Print option elements.
	?>
		<input type="text" id="auth_settings_<?php echo esc_attr($option); ?>" name="auth_settings[<?php echo esc_attr($option); ?>]" value="<?php echo esc_attr($auth_settings_option); ?>" placeholder='' style="width:400px;" />
		<br />
		<small>
			<?php echo wp_kses(__('Based on the selected Environment Mode, copy the Client Id for test or production environment you received on email.', $this->id), Helper::$allowed_html); ?>
		</small>
	<?php
	}


	public function print_text_client_secret($args = '')
	{
		// Get plugin option.
		$options              = Options::get_instance();
		$option               = 'bas_client_secret';
		$auth_settings_option = $options->get($option);

		// Print option elements.
	?>
		<input type="text" id="auth_settings_<?php echo esc_attr($option); ?>" name="auth_settings[<?php echo esc_attr($option); ?>]" value="<?php echo esc_attr($auth_settings_option); ?>" placeholder='' style="width:400px;" />
		<br />
		<small>
			<?php echo wp_kses(__('Based on the selected Environment Mode, copy the Client Secret for test or production environment you received on email.', $this->id), Helper::$allowed_html); ?>
		</small>
	<?php
	}



	public function print_checkbox_enabled($args = '')
	{
		// Get plugin option.
		$options              = Options::get_instance();
		$option               = 'bas_enabled';
		$auth_settings_option = $options->get($option);

		// Print option elements.
	?>
		<input type="checkbox"
			id="auth_settings_<?php echo esc_attr($option); ?>"
			name="auth_settings[<?php echo esc_attr($option); ?>]"
			value="1" <?php checked(1 === intval($auth_settings_option)); ?> />
		<label for="auth_settings_<?php echo esc_attr($option); ?>">
			<?php esc_html_e('Enable Basgate Payments.', $this->id); ?>
		</label>
	<?php
	}

	public function print_checkbox_disable_wp_login($args = '')
	{
		// Get plugin option.
		$options              = Options::get_instance();
		$option               = 'advanced_disable_wp_login';
		$auth_settings_option = $options->get($option);

		// Print option elements.
	?>
		<input type="checkbox"
			id="auth_settings_<?php echo esc_attr($option); ?>"
			name="auth_settings[<?php echo esc_attr($option); ?>]"
			value="1" <?php checked(1 === intval($auth_settings_option)); ?> />
		<label for="auth_settings_<?php echo esc_attr($option); ?>">
			<?php esc_html_e('Disable Default Wordpress login when user open store inside Bas platform.', $this->id); ?>
		</label>
	<?php
	}
}

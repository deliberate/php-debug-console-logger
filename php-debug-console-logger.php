<?php
/**
 * Plugin Name: PHP Debug Console Logger
 * Plugin URI: https://github.com/deliberate/php-debug-console-logger
 * Description: Sends data from PHP code to the browser's console
 * Version: 0.1.1
 * Author: Michael Bailey
 * Author URI: https://github.com/deliberate
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activation
 *
 * @return void
 */
function pdcl_activate() {
	add_option( 'pdcl_enabled', '0' );
}
register_activation_hook( __FILE__, 'pdcl_activate' );

/**
 * Debug logger that sends PHP data to browser's console.log.
 *
 * @param string $name Label for the log entry.
 * @param mixed  $data Data to log.
 *
 * @return void
 */
function maybe_console_log( $name, $data ) {

	// Ensure plugin is enabled
	if ( '1' !== get_option( 'pdcl_enabled' ) ) {
		return;
	}

	// Ensure the php_debug_console_logger query parameter is present.
	if ( ! isset( $_GET['php_debug_console_logger'] ) ) {
		return;
	}

	// Maybe JSON encode data
	if ( is_array( $data ) || is_object( $data ) ) {
		$data = wp_json_encode( $data, JSON_PRETTY_PRINT );
	} else {
		$data = json_encode( (string) $data );
	}

	// Sanitize the label and define console log styling.
	$sanitized_name = esc_attr( $name );
	$log_style      = "color: white; background: #0073aa; padding: 2px 4px; border-radius: 4px; font-weight: bold;";
	
	// Output JS to log the data to the console.
	echo "<script data-php-console-log='{$sanitized_name}'>console.log('%c{$name}:', '{$log_style}', " . $data . ");</script>";
}

/**
 * Register plugin settings.
 *
 * @return void
 */
function pdcl_register_settings() {
	register_setting(
		'pdcl_settings',
		'pdcl_enabled'
	);
}
add_action( 'admin_init', 'pdcl_register_settings' );

/**
 * Add settings page under Tools menu.
 *
 * @return void
 */
function pdcl_add_settings_page() {
	add_submenu_page(
		'tools.php',
		__( 'PHP Debug Console Logger', 'php-debug-console-logger' ),
		__( 'Console Logger', 'php-debug-console-logger' ),
		'manage_options',
		'pdcl_settings',
		'pdcl_settings_page'
	);
}
add_action( 'admin_menu', 'pdcl_add_settings_page' );

/**
 * Render the plugin settings page.
 *
 * @return void
 */
function pdcl_settings_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'PHP Debug Console Logger Settings', 'php-debug-console-logger' ); ?></h1>

		<form method="post" action="options.php">

			<?php settings_fields( 'pdcl_settings' ); ?>
			<?php do_settings_sections( 'pdcl_settings' ); ?>

			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Enable Plugin', 'php-debug-console-logger' ); ?></th>
					<td>
						<input type="checkbox" name="pdcl_enabled" value="1" <?php checked( '1', get_option( 'pdcl_enabled' ) ); ?> />
						<label for="pdcl_enabled"><?php esc_html_e( 'Enable debug console logging', 'php-debug-console-logger' ); ?></label>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

	</div>
	<?php
}

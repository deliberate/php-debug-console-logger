<?php
/**
 * Plugin Name: PHP Debug Console Logger
 * Plugin URI: https://github.com/deliberate/php-debug-console-logger
 * Description: Sends data from PHP code to the browser's console
 * Version: 0.1.2
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
 * Extracts protected properties from objects, including WC_Product.
 *
 * @param string $name Label for the log entry.
 * @param mixed  $data Data to log.
 *
 * @return void
 */
function maybe_console_log( $name = '', $data = null ) {

	// Ensure plugin is enabled
	if ( '1' !== get_option( 'pdcl_enabled' ) ) {
		return;
	}

	/**
	 * Recursively extract public & protected properties from objects.
	 *
	 * @param mixed $input The object or data to process.
	 * @return mixed Processed array or original input.
	 */
	$extract_object_data = function( $input ) use ( &$extract_object_data ) {
		if ( is_object( $input ) ) {
			// Get public properties first.
			$object_data = get_object_vars( $input );

			// Get protected/private properties.
			$reflection = new ReflectionClass( $input );
			foreach ( $reflection->getProperties() as $property ) {
				$property->setAccessible( true ); // Allow access to protected/private properties
				$object_data[ $property->getName() ] = $property->getValue( $input );
			}

			// Recursively process nested objects/arrays.
			return array_map( $extract_object_data, $object_data );
		} elseif ( is_array( $input ) ) {
			return array_map( $extract_object_data, $input );
		}

		return $input;
	};

	// Convert objects to an associative array format before logging.
	$data = $extract_object_data( $data );

	// Encode data properly.
	if ( is_array( $data ) || is_object( $data ) ) {
		$data = wp_json_encode( $data, JSON_PRETTY_PRINT );
	} elseif ( ! is_null( $data ) ) {
		$data = json_encode( $data );
	}

	// Sanitize the label.
	$sanitized_name = esc_attr( $name );

	// Define console log styling.
	$log_style     = "color: white; background: " . ( is_null( $data ) ? '#808080' : '#0073aa' ) . "; padding: 2px 4px; border-radius: 4px; font-weight: bold;";
	$prequel_style = "color: white; background: red; padding: 2px 4px; border-radius: 4px; font-weight: bold;";

	// Output JS to log the data to the console.
	echo "<script data-php-console-log='{$sanitized_name}'>console.log('%cphp debug%c → %c{$name}:', '{$prequel_style}', '', '{$log_style}', " . $data . ");</script>";
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

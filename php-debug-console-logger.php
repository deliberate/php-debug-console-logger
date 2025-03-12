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

namespace Deliberate\PhpDebugConsoleLogger;

use DateTimeInterface;
use Closure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PhpDebugConsoleLogger {

	/**
	 * Initialize the plugin
	 */
	public function __construct() {
		register_activation_hook( __FILE__, [ $this, 'activate' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		add_option( 'pdcl_enabled', '0' );
	}

	/**
	 * Debug logger that sends PHP data to browser's console.log.
	 *
	 * @param string $name Label for the log entry.
	 * @param mixed  $data Data to log.
	 */
	public static function maybe_console_log( $name = '', $data = null ) {
		if ( '1' !== get_option( 'pdcl_enabled' ) ) {
			return;
		}

		$data = self::extract_object_data( $data );

		if ( is_array( $data ) || is_object( $data ) ) {
			$data = wp_json_encode( $data, JSON_PRETTY_PRINT );
		} elseif ( ! is_null( $data ) ) {
			$data = json_encode( $data );
		}

		$sanitized_name = esc_attr( $name );
		$log_style     = "color: white; background: " . ( is_null( $data ) ? '#808080' : '#0073aa' ) . "; padding: 2px 4px; border-radius: 4px; font-weight: bold;";
		$prequel_style = "color: white; background: red; padding: 2px 4px; border-radius: 4px; font-weight: bold;";

		echo "<script data-php-console-log='{$sanitized_name}'>console.log('%cphp debug%c → %c{$name}:', '{$prequel_style}', '', '{$log_style}', " . $data . ");</script>";
	}

	/**
	 * Recursively extract public & protected properties from objects.
	 *
	 * @param mixed $input The object or data to process.
	 * @return mixed Processed array or original input.
	 */
	/**
	 * Maximum recursion depth
	 */
	const MAX_DEPTH = 10;

	/**
	 * Maximum items in arrays
	 */
	const MAX_ITEMS = 100;

	/**
	 * Track processed objects to prevent circular references
	 */
	private static $processedObjects = [];

	/**
	 * Current recursion depth
	 */
	private static $currentDepth = 0;

	/**
	 * Recursively extract public & protected properties from objects.
	 *
	 * @param mixed $input The object or data to process.
	 * @return mixed Processed array or original input.
	 */
	private static function extract_object_data( $input ) {
		// Handle depth limit
		if ( self::$currentDepth > self::MAX_DEPTH ) {
			return array( 'max_depth_reached' => true );
		}
		self::$currentDepth++;

		// Handle resources
		if ( is_resource( $input ) ) {
			self::$currentDepth--;
			return array( 'resource_type' => get_resource_type( $input ) );
		}

		// Handle closures
		if ( $input instanceof Closure ) {
			self::$currentDepth--;
			return array( 'closure' => 'Function object' );
		}

		// Handle DateTime objects
		if ( $input instanceof DateTimeInterface ) {
			self::$currentDepth--;
			return array(
				'datetime' => $input->format( DateTime::ATOM ),
				'timezone' => $input->getTimezone()->getName(),
			);
		}

		// Handle circular references
		if ( is_object( $input ) ) {
			$objectId = spl_object_id( $input );
			if ( in_array( $objectId, self::$processedObjects, true ) ) {
				self::$currentDepth--;
				return array( 'circular_reference' => get_class( $input ) );
			}
			self::$processedObjects[] = $objectId;
		}

		// Process objects
		if ( is_object( $input ) ) {
			$object_data = get_object_vars( $input );
			$reflection  = new ReflectionClass( $input );

			foreach ( $reflection->getProperties() as $property ) {
				$property->setAccessible( true );
				$object_data[ $property->getName() ] = $property->getValue( $input );
			}

			$result = array_map( array( __CLASS__, 'extract_object_data' ), $object_data );
			self::$currentDepth--;
			return $result;
		}

		// Process arrays with size limit
		if ( is_array( $input ) ) {
			$result = array_map( array( __CLASS__, 'extract_object_data' ), $input );
			self::$currentDepth--;
			if ( count( $result ) > self::MAX_ITEMS ) {
				return array_slice( $result, 0, self::MAX_ITEMS ) + array( 'max_items_reached' => true );
			}
			return $result;
		}

		self::$currentDepth--;
		return $input;
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting( 'pdcl_settings', 'pdcl_enabled' );
	}

	/**
	 * Add settings page under Tools menu
	 */
	public function add_settings_page() {
		add_submenu_page(
			'tools.php',
			__( 'PHP Debug Console Logger', 'php-debug-console-logger' ),
			__( 'Console Logger', 'php-debug-console-logger' ),
			'manage_options',
			'pdcl_settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Render the plugin settings page
	 */
	public function render_settings_page() {
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
}

// Initialize the plugin
new PhpDebugConsoleLogger();

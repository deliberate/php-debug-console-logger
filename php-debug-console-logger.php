<?php
/**
 * Plugin Name: PHP Debug Console Logger (MU)
 * Description: Sends data from PHP code to the browser's console as early as possible. Designed to run as an MU plugin.
 * Version: 0.2.1
 * Author: Michael Bailey
 */

namespace Deliberate\PhpDebugConsoleLogger;

require_once __DIR__ . '/helpers.php';

use DateTimeInterface;
use ReflectionClass;
use Closure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main class for PHP Debug Console Logger
 */
class PhpDebugConsoleLogger {

	const MAX_DEPTH = 10;
	const MAX_ITEMS = 100;

	/**
	 * Track processed objects
	 * @var array
	 */
	private static $processedObjects = [];

	/**
	 * Current recursion depth
	 * @var int
	 */
	private static $currentDepth = 0;

	/**
	 * Logs data to the browser console if enabled
	 *
	 * @param string $name Label for the log entry.
	 * @param mixed  $data Data to log.
	 */
	public static function maybe_console_log( $name = '', $data = null ) {
		$enabled = defined( 'PDCL_ENABLED' ) ? PDCL_ENABLED : false;

		if ( ! $enabled ) {
			return;
		}

		$data = self::extract_object_data( $data );

		if ( is_array( $data ) || is_object( $data ) ) {
			$data = wp_json_encode( $data, JSON_PRETTY_PRINT );
		} elseif ( $data !== null ) {
			$data = json_encode( $data );
		}

		$sanitized_name = esc_attr( $name );
		$log_style      = "color: white; background: " . ( $data === null ? '#808080' : '#0073aa' ) . "; padding: 2px 4px; border-radius: 4px; font-weight: bold;";
		$prequel_style  = "color: white; background: red; padding: 2px 4px; border-radius: 4px; font-weight: bold;";

		echo "<script data-php-console-log='{$sanitized_name}'>console.log('%cphp debug%c → %c{$name}:', '{$prequel_style}', '', '{$log_style}', " . $data . ");</script>";
	}

	/**
	 * Recursively extracts object data
	 *
	 * @param mixed $input
	 * @return mixed
	 */
	private static function extract_object_data( $input ) {
		if ( self::$currentDepth > self::MAX_DEPTH ) {
			return [ 'max_depth_reached' => true ];
		}
		self::$currentDepth++;

		if ( is_resource( $input ) ) {
			self::$currentDepth--;
			return [ 'resource_type' => get_resource_type( $input ) ];
		}

		if ( $input instanceof Closure ) {
			self::$currentDepth--;
			return [ 'closure' => 'Function object' ];
		}

		if ( $input instanceof DateTimeInterface ) {
			self::$currentDepth--;
			return [
				'datetime' => $input->format( \DateTime::ATOM ),
				'timezone' => $input->getTimezone()->getName(),
			];
		}

		if ( is_object( $input ) ) {
			$objectId = spl_object_id( $input );
			if ( in_array( $objectId, self::$processedObjects, true ) ) {
				self::$currentDepth--;
				return [ 'circular_reference' => get_class( $input ) ];
			}
			self::$processedObjects[] = $objectId;

			$object_data = get_object_vars( $input );
			$reflection  = new ReflectionClass( $input );

			foreach ( $reflection->getProperties() as $property ) {
				$property->setAccessible( true );
				$object_data[ $property->getName() ] = $property->getValue( $input );
			}

			$result = array_map(
				[ __CLASS__, 'extract_object_data' ],
				$object_data
			);
			self::$currentDepth--;
			return $result;
		}

		if ( is_array( $input ) ) {
			$result = array_map(
				[ __CLASS__, 'extract_object_data' ],
				$input
			);
			self::$currentDepth--;
			if ( count( $result ) > self::MAX_ITEMS ) {
				return array_slice( $result, 0, self::MAX_ITEMS ) + [ 'max_items_reached' => true ];
			}
			return $result;
		}

		self::$currentDepth--;
		return $input;
	}
}

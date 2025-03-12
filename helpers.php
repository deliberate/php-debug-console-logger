<?php
/**
 * Helper functions for PHP Debug Console Logger
 *
 * @package php-debug-console-logger
 */

if ( ! function_exists( 'maybe_console_log' ) ) {
	/**
	 * Simplified wrapper for PhpDebugConsoleLogger::maybe_console_log
	 *
	 * @param string $name Label for the log entry.
	 * @param mixed  $data Data to log.
	 */
	function maybe_console_log( $name = '', $data = null ) {
		\Deliberate\PhpDebugConsoleLogger\PhpDebugConsoleLogger::maybe_console_log( $name, $data );
	}
}

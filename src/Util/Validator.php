<?php

namespace Push\Util;

/**
 * Validation utilities for WordPress installation checks
 */
class Validator
{
	/**
	 * Validate that a path contains a valid WordPress installation
	 *
	 * @param string $path Path to WordPress root (where wp-config.php is)
	 * @return array Validation result with 'valid' boolean and 'errors' array
	 */
	public static function validateWordPressInstallation(string $path): array
	{
		$errors = [];
		$path = rtrim($path, '/');

		// Check if path exists
		if (!is_dir($path)) {
			$errors[] = "Path does not exist: {$path}";
			return ['valid' => false, 'errors' => $errors];
		}

		// Check for wp-config.php (must be in root)
		$wpConfig = $path . '/wp-config.php';
		if (!file_exists($wpConfig)) {
			$errors[] = "wp-config.php not found in: {$path}";
		} elseif (!is_readable($wpConfig)) {
			$errors[] = "wp-config.php is not readable in: {$path}";
		}

		// Find wp-load.php in root or any subdirectory
		$wpLoad = self::findWpLoad($path);
		if ($wpLoad === false) {
			$errors[] = "wp-load.php not found in: {$path} or any subdirectory";
		} elseif (!is_readable($wpLoad)) {
			$errors[] = "wp-load.php is not readable: {$wpLoad}";
		}

		// Find wp-includes directory in root or any subdirectory
		$wpIncludes = self::findWpIncludes($path);
		if ($wpIncludes === false) {
			$errors[] = "wp-includes directory not found in: {$path} or any subdirectory";
		}

		// Find wp-content directory in root or any subdirectory
		$wpContent = self::findWpContent($path);
		if ($wpContent === false) {
			$errors[] = "wp-content directory not found in: {$path} or any subdirectory";
		}

		return [
			'valid' => empty($errors),
			'errors' => $errors,
			'path' => $path
		];
	}

	/**
	 * Find wp-load.php in WordPress installation
	 * Searches in the root and all subdirectories
	 *
	 * @param string $wpRoot Path to WordPress root (where wp-config.php is)
	 * @return string|false Path to wp-load.php or false if not found
	 */
	protected static function findWpLoad(string $wpRoot)
	{
		$wpRoot = rtrim($wpRoot, '/');

		// Check common locations first (for performance)
		$commonPaths = [
			$wpRoot . '/wp-load.php',           // Standard: in root
			$wpRoot . '/wp/wp-load.php',        // Conventional: in wp/ subdirectory
		];

		foreach ($commonPaths as $path) {
			if (file_exists($path)) {
				return $path;
			}
		}

		// If not found in common locations, search recursively
		return FileSystem::searchFileRecursive($wpRoot, 'wp-load.php');
	}

	/**
	 * Find wp-includes directory in WordPress installation
	 * Searches in the root and all subdirectories
	 *
	 * @param string $wpRoot Path to WordPress root (where wp-config.php is)
	 * @return string|false Path to wp-includes directory or false if not found
	 */
	protected static function findWpIncludes(string $wpRoot)
	{
		$wpRoot = rtrim($wpRoot, '/');

		// Check common locations first (for performance)
		$commonPaths = [
			$wpRoot . '/wp-includes',           // Standard: in root
			$wpRoot . '/wp/wp-includes',        // Conventional: in wp/ subdirectory
		];

		foreach ($commonPaths as $path) {
			if (is_dir($path)) {
				return $path;
			}
		}

		// If not found in common locations, search recursively
		return FileSystem::searchDirectoryRecursive($wpRoot, 'wp-includes');
	}

	/**
	 * Find wp-content directory in WordPress installation
	 * Searches in the root and all subdirectories
	 *
	 * @param string $wpRoot Path to WordPress root (where wp-config.php is)
	 * @return string|false Path to wp-content directory or false if not found
	 */
	protected static function findWpContent(string $wpRoot)
	{
		$wpRoot = rtrim($wpRoot, '/');

		// Check common locations first (for performance)
		$commonPaths = [
			$wpRoot . '/wp-content',           // Standard: in root
			$wpRoot . '/wp/wp-content',        // Conventional: in wp/ subdirectory
		];

		foreach ($commonPaths as $path) {
			if (is_dir($path)) {
				return $path;
			}
		}

		// If not found in common locations, search recursively
		return FileSystem::searchDirectoryRecursive($wpRoot, 'wp-content');
	}

	/**
	 * Validate database connection after WordPress is loaded
	 *
	 * @return array Validation result with 'valid' boolean and 'errors' array
	 */
	public static function validateDatabase(): array
	{
		$errors = [];

		// Check if WordPress is loaded
		if (!defined('ABSPATH')) {
			$errors[] = "WordPress is not loaded";
			return ['valid' => false, 'errors' => $errors];
		}

		// Check if database functions are available
		if (!function_exists('wp_get_db_schema')) {
			$errors[] = "WordPress database functions not available";
			return ['valid' => false, 'errors' => $errors];
		}

		// Try to connect to database
		global $wpdb;
		if (!isset($wpdb) || !$wpdb instanceof \wpdb) {
			$errors[] = "WordPress database object not available";
			return ['valid' => false, 'errors' => $errors];
		}

		// Test database connection
		$result = $wpdb->get_var("SELECT 1");
		if ($result !== '1') {
			$errors[] = "Database connection test failed";
		}

		// Check for essential WordPress tables
		$tables = ['posts', 'options', 'users'];
		$tablePrefix = $wpdb->prefix;
		foreach ($tables as $table) {
			$tableName = $tablePrefix . $table;
			$exists = $wpdb->get_var("SHOW TABLES LIKE '{$tableName}'");
			if (!$exists) {
				$errors[] = "Required table not found: {$tableName}";
			}
		}

		return [
			'valid' => empty($errors),
			'errors' => $errors
		];
	}

	/**
	 * Validate WordPress version meets minimum requirement
	 *
	 * @param string $minVersion Minimum required version (default: '5.5')
	 * @return array Validation result
	 */
	public static function validateWordPressVersion(string $minVersion = '5.5'): array
	{
		$errors = [];

		if (!defined('ABSPATH')) {
			$errors[] = "WordPress is not loaded";
			return ['valid' => false, 'errors' => $errors];
		}

		if (!function_exists('get_bloginfo')) {
			$errors[] = "WordPress functions not available";
			return ['valid' => false, 'errors' => $errors];
		}

		$currentVersion = get_bloginfo('version');
		if (version_compare($currentVersion, $minVersion, '<')) {
			$errors[] = "WordPress version {$currentVersion} is below minimum required version {$minVersion}";
		}

		return [
			'valid' => empty($errors),
			'errors' => $errors,
			'version' => $currentVersion ?? null
		];
	}
}


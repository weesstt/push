<?php

namespace Push;

use Push\Util\Validator;
use Push\Util\FileSystem;

/**
 * WordPress bootstrap and validation system
 */
class Bootstrap
{
	/**
	 * Locate WordPress installation
	 * 
	 * First checks if we're installed as a mu-plugin (wp-content/mu-plugins/push/),
	 * then falls back to traversing the directory tree from the given path.
	 *
	 * @param string|null $path Starting path (defaults to current working directory)
	 * @return string|false Path to WordPress root (where wp-config.php is) or false if not found
	 */
	public function locateWordPress(?string $path = null)
	{
		// First, try to locate via mu-plugins path (most reliable when installed correctly)
		$muPluginPath = $this->locateFromMuPlugins();
		if ($muPluginPath !== false) {
			return $muPluginPath;
		}

		// Fall back to directory traversal from given path or cwd
		if ($path === null) {
			$path = getcwd();
		}

		return $this->locateByTraversal($path);
	}

	/**
	 * Locate WordPress by checking if we're in mu-plugins
	 * 
	 * Path structure: wp-content/mu-plugins/push/src/Bootstrap.php
	 * - __DIR__ = wp-content/mu-plugins/push/src
	 * - mu-plugins = parent of parent (wp-content/mu-plugins)
	 * - wp-content = parent of mu-plugins
	 * - WordPress root = parent of wp-content (typically) or wp-content itself
	 *
	 * @return string|false WordPress root path or false if not found
	 */
	protected function locateFromMuPlugins()
	{
		// src/ directory
		$srcDir = __DIR__;
		
		// push/ directory (plugin root)
		$pluginDir = dirname($srcDir);
		
		// mu-plugins/ directory
		$muPluginsDir = dirname($pluginDir);
		
		// Verify we're actually in mu-plugins
		if (basename($muPluginsDir) !== 'mu-plugins') {
			return false;
		}
		
		// wp-content/ directory
		$wpContentDir = dirname($muPluginsDir);
		
		if (basename($wpContentDir) !== 'wp-content') {
			// Non-standard structure, but mu-plugins confirms WordPress
			// Try to find wp-config.php by going up
			return $this->locateByTraversal($wpContentDir);
		}
		
		// Standard structure: WordPress root is parent of wp-content
		$wpRoot = dirname($wpContentDir);
		
		// Check for wp-config.php in standard location
		if (file_exists($wpRoot . '/wp-config.php')) {
			return $wpRoot;
		}
		
		// wp-config.php might be one level above WordPress root (security practice)
		$parentRoot = dirname($wpRoot);
		if (file_exists($parentRoot . '/wp-config.php')) {
			// But wp-load.php should still be in $wpRoot
			if (file_exists($wpRoot . '/wp-load.php') || FileSystem::findWpLoad($wpRoot)) {
				return $wpRoot;
			}
		}
		
		// Bedrock or similar: wp-config.php in wp-content's parent
		if (file_exists($wpContentDir . '/wp-config.php')) {
			return $wpContentDir;
		}
		
		// Fall back to traversal from wp-content
		return $this->locateByTraversal($wpContentDir);
	}

	/**
	 * Locate WordPress by traversing up the directory tree
	 *
	 * @param string $path Starting path
	 * @return string|false WordPress root path or false if not found
	 */
	protected function locateByTraversal(string $path)
	{
		$path = realpath($path);
		if ($path === false) {
			return false;
		}

		$currentPath = $path;
		$rootPath = '/';

		while ($currentPath !== $rootPath && $currentPath !== false) {
			$wpConfig = $currentPath . '/wp-config.php';

			if (file_exists($wpConfig)) {
				return $currentPath;
			}

			$parentPath = dirname($currentPath);
			if ($parentPath === $currentPath) {
				break;
			}
			$currentPath = $parentPath;
		}

		return false;
	}

	/**
	 * Set up $_SERVER variables for CLI context
	 * WordPress expects these to be set even in CLI mode
	 *
	 * @param string $wpRoot Path to WordPress root
	 */
	protected function setupServerVariables(string $wpRoot): void
	{
		$wpRoot = rtrim($wpRoot, '/');

		// Set default $_SERVER variables if not already set
		if (!isset($_SERVER['HTTP_HOST'])) {
			$_SERVER['HTTP_HOST'] = 'localhost';
		}

		if (!isset($_SERVER['SERVER_NAME'])) {
			$_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
		}

		if (!isset($_SERVER['REQUEST_URI'])) {
			$_SERVER['REQUEST_URI'] = '/';
		}

		if (!isset($_SERVER['REQUEST_METHOD'])) {
			$_SERVER['REQUEST_METHOD'] = 'GET';
		}

		if (!isset($_SERVER['SERVER_PROTOCOL'])) {
			$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
		}

		if (!isset($_SERVER['HTTP_USER_AGENT'])) {
			$_SERVER['HTTP_USER_AGENT'] = 'Push-CLI/1.0';
		}

		if (!isset($_SERVER['HTTPS'])) {
			$_SERVER['HTTPS'] = 'off';
		}

		if (!isset($_SERVER['SERVER_PORT'])) {
			$_SERVER['SERVER_PORT'] = '80';
		}

		if (!isset($_SERVER['DOCUMENT_ROOT'])) {
			$_SERVER['DOCUMENT_ROOT'] = $wpRoot;
		}

		if (!isset($_SERVER['SCRIPT_NAME'])) {
			$_SERVER['SCRIPT_NAME'] = '/index.php';
		}

		if (!isset($_SERVER['PHP_SELF'])) {
			$_SERVER['PHP_SELF'] = '/index.php';
		}
	}

	/**
	 * Load WordPress installation
	 * Finds wp-load.php in any subdirectory of the WordPress root
	 *
	 * @param string $wpRoot Path to WordPress root (where wp-config.php is)
	 * @return bool True on success, false on failure
	 */
	public function loadWordPress(string $wpRoot): bool
	{
		$wpRoot = rtrim($wpRoot, '/');
		
		// Set up $_SERVER variables for CLI context
		$this->setupServerVariables($wpRoot);
		
		// Find wp-load.php in root or any subdirectory
		$wpLoad = FileSystem::findWpLoad($wpRoot);
		
		if ($wpLoad === false) {
			return false;
		}

		// Suppress output and errors during WordPress loading
		$errorLevel = error_reporting(E_ERROR | E_WARNING | E_PARSE);
		ob_start();

		try {
			require_once $wpLoad;
		} catch (\Throwable $e) {
			ob_end_clean();
			error_reporting($errorLevel);
			return false;
		}

		ob_end_clean();
		error_reporting($errorLevel);

		// Verify WordPress loaded correctly
		if (!defined('ABSPATH')) {
			return false;
		}

		return true;
	}

	/**
	 * Validate WordPress installation
	 *
	 * @param string|null $path Path to WordPress installation (optional, will locate if not provided)
	 * @return array Validation result with 'valid', 'errors', 'path', and 'loaded' keys
	 */
	public function validateInstallation(?string $path = null): array
	{
		$result = [
			'valid' => false,
			'errors' => [],
			'path' => null,
			'loaded' => false
		];

		// Locate WordPress if path not provided
		if ($path === null) {
			$path = $this->locateWordPress();
			if ($path === false) {
				$result['errors'][] = "WordPress installation not found. Please run this command from within a WordPress directory or specify --path.";
				return $result;
			}
		}

		$result['path'] = $path;

		// Validate file structure
		$fileValidation = Validator::validateWordPressInstallation($path);
		if (!$fileValidation['valid']) {
			$result['errors'] = array_merge($result['errors'], $fileValidation['errors']);
			return $result;
		}

		// Load WordPress
		if (!$this->loadWordPress($path)) {
			$result['errors'][] = "Failed to load WordPress from: {$path}";
			return $result;
		}

		$result['loaded'] = true;

		// Validate database connection
		$dbValidation = Validator::validateDatabase();
		if (!$dbValidation['valid']) {
			$result['errors'] = array_merge($result['errors'], $dbValidation['errors']);
			return $result;
		}

		// Validate WordPress version
		$versionValidation = Validator::validateWordPressVersion();
		if (!$versionValidation['valid']) {
			$result['errors'] = array_merge($result['errors'], $versionValidation['errors']);
			return $result;
		}

		$result['valid'] = true;
		return $result;
	}

	/**
	 * Get WordPress installation information
	 *
	 * @return array Installation info
	 */
	public function getInstallationInfo(): array
	{
		if (!defined('ABSPATH')) {
			return [];
		}

		$info = [
			'version' => get_bloginfo('version'),
			'site_url' => get_site_url(),
			'admin_url' => get_admin_url(),
			'is_multisite' => is_multisite(),
		];

		if (function_exists('get_current_blog_id')) {
			$info['blog_id'] = get_current_blog_id();
		}

		return $info;
	}
}

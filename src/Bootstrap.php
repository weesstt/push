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
	 * Locate WordPress installation by traversing directory tree
	 *
	 * @param string|null $path Starting path (defaults to current working directory)
	 * @return string|false Path to WordPress root (where wp-config.php is) or false if not found
	 */
	public function locateWordPress(?string $path = null)
	{
		if ($path === null) {
			$path = getcwd();
		}

		// Normalize path
		$path = realpath($path);
		if ($path === false) {
			return false;
		}

		// Start from the given path and traverse up
		$currentPath = $path;
		$rootPath = '/';

		while ($currentPath !== $rootPath && $currentPath !== false) {
			$wpConfig = $currentPath . '/wp-config.php';

			// Check if wp-config.php exists (this is the WordPress root)
			if (file_exists($wpConfig)) {
				return $currentPath;
			}

			// Move up one directory
			$parentPath = dirname($currentPath);
			if ($parentPath === $currentPath) {
				// Reached filesystem root
				break;
			}
			$currentPath = $parentPath;
		}

		return false;
	}

	/**
	 * Find wp-load.php in WordPress installation
	 * Searches in the root and all subdirectories
	 *
	 * @param string $wpRoot Path to WordPress root (where wp-config.php is)
	 * @return string|false Path to wp-load.php or false if not found
	 */
	protected function findWpLoad(string $wpRoot)
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
	 * Load WordPress installation
	 * Finds wp-load.php in any subdirectory of the WordPress root
	 *
	 * @param string $wpRoot Path to WordPress root (where wp-config.php is)
	 * @return bool True on success, false on failure
	 */
	public function loadWordPress(string $wpRoot): bool
	{
		$wpRoot = rtrim($wpRoot, '/');
		
		// Find wp-load.php in root or any subdirectory
		$wpLoad = $this->findWpLoad($wpRoot);
		
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


<?php

namespace Push\Util;

/**
 * File system utility functions
 */
class FileSystem
{
	/**
	 * Recursively search for a file in a directory
	 *
	 * @param string $directory Directory to search
	 * @param string $filename Filename to find
	 * @return string|false Full path to file or false if not found
	 */
	public static function searchFileRecursive(string $directory, string $filename)
	{
		$directory = rtrim($directory, '/');
		
		if (!is_dir($directory) || !is_readable($directory)) {
			return false;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($iterator as $file) {
			if ($file->isFile() && $file->getFilename() === $filename) {
				return $file->getPathname();
			}
		}

		return false;
	}

	/**
	 * Recursively search for a directory
	 *
	 * @param string $directory Directory to search
	 * @param string $dirname Directory name to find
	 * @return string|false Full path to directory or false if not found
	 */
	public static function searchDirectoryRecursive(string $directory, string $dirname)
	{
		$directory = rtrim($directory, '/');
		
		if (!is_dir($directory) || !is_readable($directory)) {
			return false;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($iterator as $file) {
			if ($file->isDir() && $file->getFilename() === $dirname) {
				return $file->getPathname();
			}
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
	public static function findWpLoad(string $wpRoot)
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
		return self::searchFileRecursive($wpRoot, 'wp-load.php');
	}

	/**
	 * Find wp-content directory in WordPress installation
	 * Searches in the root and all subdirectories
	 *
	 * @param string $wpRoot Path to WordPress root (where wp-config.php is)
	 * @param bool $requireWritable Whether to require the directory to be writable
	 * @return string|false Path to wp-content directory or false if not found
	 */
	public static function findWpContent(string $wpRoot, bool $requireWritable = false)
	{
		$wpRoot = rtrim($wpRoot, '/');

		// Check common locations first (for performance)
		$commonPaths = [
			$wpRoot . '/wp-content',           // Standard: in root
			$wpRoot . '/wp/wp-content',        // Conventional: in wp/ subdirectory
		];

		foreach ($commonPaths as $path) {
			if (is_dir($path)) {
				if ($requireWritable && !is_writable($path)) {
					continue;
				}
				return $path;
			}
		}

		// If not found in common locations, search recursively
		$found = self::searchDirectoryRecursive($wpRoot, 'wp-content');
		if ($found) {
			if ($requireWritable && !is_writable($found)) {
				return false;
			}
			return $found;
		}

		return false;
	}
}


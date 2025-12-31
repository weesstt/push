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
}


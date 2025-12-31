<?php

namespace Push\Alias;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Push\Bootstrap;

/**
 * Manages site aliases
 */
class AliasManager
{
	/**
	 * @var Filesystem
	 */
	protected $filesystem;

	/**
	 * @var Bootstrap
	 */
	protected $bootstrap;

	/**
	 * @var array Loaded aliases
	 */
	protected $aliases = [];

	/**
	 * @var array Alias file paths
	 */
	protected $aliasFiles = [];

	/**
	 * @var string|null WordPress installation path
	 */
	protected $wpPath = null;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->filesystem = new Filesystem();
		$this->bootstrap = new Bootstrap();
		$this->loadAliases();
	}

	/**
	 * Load aliases from configuration files
	 * Looks for alias files in .push/ directory of WordPress installation
	 */
	protected function loadAliases(): void
	{
		// Try to locate WordPress installation
		$wpPath = $this->bootstrap->locateWordPress();
		
		if ($wpPath === false) {
			// No WordPress installation found, can't load aliases
			return;
		}

		$this->wpPath = $wpPath;
		$aliasFiles = $this->findAliasFiles($wpPath);

		foreach ($aliasFiles as $file) {
			$this->loadAliasFile($file);
		}
	}

	/**
	 * Find alias files in .push/ directory of WordPress installation
	 *
	 * @param string $wpPath Path to WordPress installation root
	 * @return array Array of file paths
	 */
	protected function findAliasFiles(string $wpPath): array
	{
		$files = [];
		$pushDir = $wpPath . '/.push';

		// Check for aliases.yml
		$aliasesYml = $pushDir . '/aliases.yml';
		if (file_exists($aliasesYml)) {
			$files[] = $aliasesYml;
		}

		// Also check for .yaml extension
		$aliasesYaml = $pushDir . '/aliases.yaml';
		if (file_exists($aliasesYaml)) {
			$files[] = $aliasesYaml;
		}

		return $files;
	}

	/**
	 * Load aliases from a YAML file
	 *
	 * @param string $filePath Path to alias file
	 */
	protected function loadAliasFile(string $filePath): void
	{
		if (!file_exists($filePath) || !is_readable($filePath)) {
			return;
		}

		// Verify it's a YAML file
		$extension = pathinfo($filePath, PATHINFO_EXTENSION);
		if ($extension !== 'yml' && $extension !== 'yaml') {
			return;
		}

		$this->aliasFiles[] = $filePath;

		$content = file_get_contents($filePath);
		if ($content === false) {
			return;
		}

		// Parse YAML
		try {
			$data = Yaml::parse($content);
		} catch (\Exception $e) {
			// Invalid YAML, skip this file
			return;
		}

		if (!is_array($data)) {
			return;
		}

		// Load aliases from data
		foreach ($data as $name => $config) {
			// Ensure name starts with @
			if (substr($name, 0, 1) !== '@') {
				$name = '@' . $name;
			}

			// Only load local aliases for Phase 1
			if (isset($config['ssh']) && $config['ssh'] !== null) {
				continue; // Skip remote aliases for now
			}

			try {
				$alias = new Alias($name, $config);
				$this->aliases[$name] = $alias;
			} catch (\Exception $e) {
				// Skip invalid aliases
				continue;
			}
		}
	}

	/**
	 * Get an alias by name
	 *
	 * @param string $name Alias name (with or without @ prefix)
	 * @return Alias|null
	 */
	public function getAlias(string $name): ?Alias
	{
		// Ensure name starts with @
		if (substr($name, 0, 1) !== '@') {
			$name = '@' . $name;
		}

		return $this->aliases[$name] ?? null;
	}

	/**
	 * Get all aliases
	 *
	 * @return array Array of Alias objects
	 */
	public function getAliases(): array
	{
		return $this->aliases;
	}

	/**
	 * Check if an alias exists
	 *
	 * @param string $name Alias name
	 * @return bool
	 */
	public function hasAlias(string $name): bool
	{
		// Ensure name starts with @
		if (substr($name, 0, 1) !== '@') {
			$name = '@' . $name;
		}

		return isset($this->aliases[$name]);
	}

	/**
	 * Resolve alias or path
	 * If input is an alias, return the alias object
	 * If input is a path, return null (path will be used directly)
	 *
	 * @param string $input Alias name or path
	 * @return Alias|null
	 */
	public function resolve(string $input): ?Alias
	{
		// If it starts with @, it's an alias
		if (substr($input, 0, 1) === '@') {
			return $this->getAlias($input);
		}

		// Otherwise, treat as path
		return null;
	}

	/**
	 * Validate all aliases
	 *
	 * @return array Validation results
	 */
	public function validateAll(): array
	{
		$results = [];

		foreach ($this->aliases as $name => $alias) {
			$validation = $alias->validate();
			$results[$name] = $validation;
		}

		return $results;
	}

	/**
	 * Get alias file paths
	 *
	 * @return array
	 */
	public function getAliasFiles(): array
	{
		return $this->aliasFiles;
	}

	/**
	 * Load aliases from a specific WordPress installation path
	 * Useful when working with a specific installation
	 *
	 * @param string $wpPath Path to WordPress installation root
	 */
	public function loadAliasesFromPath(string $wpPath): void
	{
		$this->wpPath = $wpPath;
		$this->aliases = [];
		$this->aliasFiles = [];

		$aliasFiles = $this->findAliasFiles($wpPath);

		foreach ($aliasFiles as $file) {
			$this->loadAliasFile($file);
		}
	}
}


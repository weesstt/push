<?php

namespace Push\Alias;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use Push\Bootstrap;

/**
 * Manages site aliases
 * 
 * Supports glob patterns in alias names (e.g., @provider.mysite.*)
 * where '*' can be replaced with environment values like 'dev', 'live', etc.
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
	 * @var array<string, Alias> Loaded aliases (including glob patterns)
	 */
	protected $aliases = [];

	/**
	 * @var array Alias file paths that were loaded
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

		try {
			$data = Yaml::parse($content);
		} catch (\Exception $e) {
			return;
		}

		if (!is_array($data)) {
			return;
		}

		foreach ($data as $name => $config) {
			// Ensure name starts with @
			if (substr($name, 0, 1) !== '@') {
				$name = '@' . $name;
			}

			if (!is_array($config)) {
				continue;
			}

			try {
				$alias = new Alias($name, $config);
				$this->aliases[$name] = $alias;
			} catch (\Exception $e) {
				continue;
			}
		}
	}

	/**
	 * Get an alias by name
	 * Supports glob pattern matching (e.g., @provider.mysite.dev matches @provider.mysite.*)
	 *
	 * @param string $name Alias name (with or without @ prefix)
	 * @return Alias|null Returns resolved alias or null if not found
	 */
	public function getAlias(string $name): ?Alias
	{
		// Ensure name starts with @
		if (substr($name, 0, 1) !== '@') {
			$name = '@' . $name;
		}

		// Direct match
		if (isset($this->aliases[$name])) {
			return $this->aliases[$name];
		}

		// Try glob pattern matching
		foreach ($this->aliases as $alias) {
			if ($alias->hasGlob() && $alias->matches($name)) {
				$globValue = $alias->extractGlobValue($name);
				if ($globValue !== null) {
					return $alias->resolve($globValue);
				}
			}
		}

		return null;
	}

	/**
	 * Get all aliases (including unresolved glob patterns)
	 *
	 * @return array<string, Alias> Array of Alias objects keyed by name
	 */
	public function getAliases(): array
	{
		return $this->aliases;
	}

	/**
	 * Check if an alias exists (including glob pattern match)
	 *
	 * @param string $name Alias name
	 * @return bool
	 */
	public function hasAlias(string $name): bool
	{
		return $this->getAlias($name) !== null;
	}

	/**
	 * Resolve alias or path
	 * If input is an alias, return the alias object
	 * If input is a path, return null
	 *
	 * @param string $input Alias name or path
	 * @return Alias|null
	 */
	public function resolve(string $input): ?Alias
	{
		if (substr($input, 0, 1) === '@') {
			return $this->getAlias($input);
		}

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
	 * Load aliases from a specific WordPress installation path
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


<?php

namespace Push\Alias;

/**
 * Represents a site alias
 * 
 * Supports glob patterns where '*' can be replaced with environment values
 * Example: @provider.mysite.* becomes @provider.mysite.dev when resolved
 */
class Alias
{
	/**
	 * @var string Alias name/pattern (e.g., '@self', '@provider.mysite.*')
	 */
	protected $name;

	/**
	 * @var array Raw configuration data
	 */
	protected $config;

	/**
	 * @var string|null The glob value used when resolving (e.g., 'dev', 'live')
	 */
	protected $globValue = null;

	/**
	 * Constructor
	 *
	 * @param string $name Alias name
	 * @param array $config Alias configuration
	 */
	public function __construct(string $name, array $config)
	{
		$this->name = $name;
		$this->config = $config;
	}

	/**
	 * Check if this alias contains a glob pattern
	 *
	 * @return bool
	 */
	public function hasGlob(): bool
	{
		return strpos($this->name, '*') !== false;
	}

	/**
	 * Check if a given alias name matches this pattern
	 *
	 * @param string $aliasName The alias name to check (e.g., '@pantheon.mysite.dev')
	 * @return bool
	 */
	public function matches(string $aliasName): bool
	{
		if (!$this->hasGlob()) {
			return $this->name === $aliasName;
		}

		// Convert glob pattern to regex
		$pattern = '/^' . str_replace('\\*', '([^.]+)', preg_quote($this->name, '/')) . '$/';
		return (bool) preg_match($pattern, $aliasName);
	}

	/**
	 * Extract the glob value from an alias name
	 *
	 * @param string $aliasName The alias name (e.g., '@provider.mysite.dev')
	 * @return string|null The glob value (e.g., 'dev') or null if no match
	 */
	public function extractGlobValue(string $aliasName): ?string
	{
		if (!$this->hasGlob()) {
			return null;
		}

		$pattern = '/^' . str_replace('\\*', '([^.]+)', preg_quote($this->name, '/')) . '$/';
		if (preg_match($pattern, $aliasName, $matches)) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Resolve this alias with a specific glob value
	 * Returns a new Alias instance with '*' replaced
	 *
	 * @param string $globValue The value to replace '*' with
	 * @return Alias A new resolved alias
	 */
	public function resolve(string $globValue): Alias
	{
		$resolvedName = str_replace('*', $globValue, $this->name);
		$resolvedConfig = $this->resolveConfig($this->config, $globValue);
		
		$alias = new Alias($resolvedName, $resolvedConfig);
		$alias->globValue = $globValue;
		
		return $alias;
	}

	/**
	 * Recursively resolve config values, replacing '*' with glob value
	 *
	 * @param mixed $config Configuration to resolve
	 * @param string $globValue Value to replace '*' with
	 * @return mixed Resolved configuration
	 */
	protected function resolveConfig($config, string $globValue)
	{
		if (is_string($config)) {
			return str_replace('*', $globValue, $config);
		}

		if (is_array($config)) {
			$resolved = [];
			foreach ($config as $key => $value) {
				$resolved[$key] = $this->resolveConfig($value, $globValue);
			}
			return $resolved;
		}

		return $config;
	}

	/**
	 * Get alias name
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Get a config value by key
	 *
	 * @param string $key Config key (supports dot notation: 'ssh.options')
	 * @param mixed $default Default value if not found
	 * @return mixed
	 */
	public function get(string $key, $default = null)
	{
		$keys = explode('.', $key);
		$value = $this->config;

		foreach ($keys as $k) {
			if (!is_array($value) || !isset($value[$k])) {
				return $default;
			}
			$value = $value[$k];
		}

		return $value;
	}

	/**
	 * Get the full configuration
	 *
	 * @return array
	 */
	public function getConfig(): array
	{
		return $this->config;
	}

	/**
	 * Get local filesystem path
	 *
	 * @return string|null
	 */
	public function getPath(): ?string
	{
		return $this->get('path') ?? $this->get('root');
	}

	/**
	 * Get site URL/URI
	 *
	 * @return string|null
	 */
	public function getUri(): ?string
	{
		return $this->get('uri') ?? $this->get('url');
	}

	/**
	 * Get SSH host
	 *
	 * @return string|null
	 */
	public function getHost(): ?string
	{
		return $this->get('host');
	}

	/**
	 * Get SSH user
	 *
	 * @return string|null
	 */
	public function getUser(): ?string
	{
		return $this->get('user');
	}

	/**
	 * Get SSH options
	 *
	 * @return array|null
	 */
	public function getSsh(): ?array
	{
		return $this->get('ssh');
	}

	/**
	 * Get paths configuration
	 *
	 * @return array|null
	 */
	public function getPaths(): ?array
	{
		return $this->get('paths');
	}

	/**
	 * Check if this is a remote alias
	 *
	 * @return bool
	 */
	public function isRemote(): bool
	{
		return $this->getHost() !== null || $this->get('ssh') !== null;
	}

	/**
	 * Check if this is a local alias
	 *
	 * @return bool
	 */
	public function isLocal(): bool
	{
		return !$this->isRemote();
	}

	/**
	 * Validate alias configuration
	 *
	 * @return array Validation result with 'valid' and 'errors' keys
	 */
	public function validate(): array
	{
		$errors = [];

		// For local aliases, path is required
		if ($this->isLocal() && empty($this->getPath())) {
			$errors[] = "Path is required for local alias '{$this->name}'";
		}

		// For local aliases without globs, validate path exists
		if ($this->isLocal() && !$this->hasGlob() && $this->getPath() && !is_dir($this->getPath())) {
			$errors[] = "Path does not exist for alias '{$this->name}': {$this->getPath()}";
		}

		// For remote aliases, host is required
		if ($this->isRemote() && empty($this->getHost()) && empty($this->get('ssh.host'))) {
			$errors[] = "Host is required for remote alias '{$this->name}'";
		}

		return [
			'valid' => empty($errors),
			'errors' => $errors
		];
	}

	/**
	 * Convert alias to array for display
	 *
	 * @return array
	 */
	public function toArray(): array
	{
		return $this->config;
	}
}

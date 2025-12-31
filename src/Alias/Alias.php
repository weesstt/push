<?php

namespace Push\Alias;

/**
 * Represents a site alias
 */
class Alias
{
	/**
	 * @var string Alias name (e.g., '@local', '@staging')
	 */
	protected $name;

	/**
	 * @var string|null Local filesystem path
	 */
	protected $path;

	/**
	 * @var string Site URL
	 */
	protected $url;

	/**
	 * @var array|null SSH connection details
	 */
	protected $ssh;

	/**
	 * @var array|null Database connection details
	 */
	protected $database;

	/**
	 * @var bool Whether this is a multisite installation
	 */
	protected $multisite = false;

	/**
	 * @var array|null Nested aliases for multisite subsites
	 */
	protected $sites;

	/**
	 * Constructor
	 *
	 * @param string $name Alias name
	 * @param array $config Alias configuration
	 */
	public function __construct(string $name, array $config)
	{
		$this->name = $name;
		$this->path = $config['path'] ?? null;
		$this->url = $config['url'] ?? null;
		$this->ssh = $config['ssh'] ?? null;
		$this->database = $config['database'] ?? null;
		$this->multisite = $config['multisite'] ?? false;
		$this->sites = $config['sites'] ?? null;
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
	 * Get local filesystem path
	 *
	 * @return string|null
	 */
	public function getPath(): ?string
	{
		return $this->path;
	}

	/**
	 * Get site URL
	 *
	 * @return string|null
	 */
	public function getUrl(): ?string
	{
		return $this->url;
	}

	/**
	 * Get SSH connection details
	 *
	 * @return array|null
	 */
	public function getSsh(): ?array
	{
		return $this->ssh;
	}

	/**
	 * Get database connection details
	 *
	 * @return array|null
	 */
	public function getDatabase(): ?array
	{
		return $this->database;
	}

	/**
	 * Check if this is a multisite installation
	 *
	 * @return bool
	 */
	public function isMultisite(): bool
	{
		return $this->multisite;
	}

	/**
	 * Get nested sites for multisite
	 *
	 * @return array|null
	 */
	public function getSites(): ?array
	{
		return $this->sites;
	}

	/**
	 * Check if this is a remote alias
	 *
	 * @return bool
	 */
	public function isRemote(): bool
	{
		return $this->ssh !== null;
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

		// URL is required
		if (empty($this->url)) {
			$errors[] = "URL is required for alias '{$this->name}'";
		}

		// For local aliases, path is required
		if ($this->isLocal() && empty($this->path)) {
			$errors[] = "Path is required for local alias '{$this->name}'";
		}

		// For local aliases, validate path exists
		if ($this->isLocal() && $this->path && !is_dir($this->path)) {
			$errors[] = "Path does not exist for alias '{$this->name}': {$this->path}";
		}

		// For remote aliases, SSH details are required
		if ($this->isRemote()) {
			if (empty($this->ssh['host'])) {
				$errors[] = "SSH host is required for remote alias '{$this->name}'";
			}
			if (empty($this->ssh['user'])) {
				$errors[] = "SSH user is required for remote alias '{$this->name}'";
			}
		}

		return [
			'valid' => empty($errors),
			'errors' => $errors
		];
	}

	/**
	 * Convert alias to array
	 *
	 * @return array
	 */
	public function toArray(): array
	{
		$array = [
			'url' => $this->url,
		];

		if ($this->path !== null) {
			$array['path'] = $this->path;
		}

		if ($this->ssh !== null) {
			$array['ssh'] = $this->ssh;
		}

		if ($this->database !== null) {
			$array['database'] = $this->database;
		}

		if ($this->multisite) {
			$array['multisite'] = true;
		}

		if ($this->sites !== null) {
			$array['sites'] = $this->sites;
		}

		return $array;
	}
}


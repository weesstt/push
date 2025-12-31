<?php

namespace Push;

use Symfony\Component\Console\Application as SymfonyApplication;
use Push\Alias\AliasManager;
use Push\Command\VersionCommand;

/**
 * Main application class
 */
class Application extends SymfonyApplication
{
	/**
	 * @var AliasManager
	 */
	protected $aliasManager;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct('push', '1.0.0');
		$this->aliasManager = new AliasManager();
		
		// Register commands
		$this->add(new VersionCommand());
	}

	/**
	 * Get alias manager
	 *
	 * @return AliasManager
	 */
	public function getAliasManager(): AliasManager
	{
		return $this->aliasManager;
	}
}


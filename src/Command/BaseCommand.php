<?php

namespace Push\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Push\Bootstrap;
use Push\Application;

/**
 * Base command class that all push commands extend
 */
abstract class BaseCommand extends Command
{
	/**
	 * @var Bootstrap
	 */
	protected $bootstrap;

	/**
	 * @var string|null WordPress installation path
	 */
	protected $wpPath = null;

	/**
	 * @var bool Whether WordPress has been loaded
	 */
	protected $wpLoaded = false;

	/**
	 * Constructor
	 */
	public function __construct(?string $name = null)
	{
		parent::__construct($name);
		$this->bootstrap = new Bootstrap();
	}

	/**
	 * Configure the command
	 */
	protected function configure()
	{
		$this->addOption(
			'path',
			'p',
			\Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED,
			'Path to WordPress installation'
		);
	}

	/**
	 * Execute the command
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		// Get path option, if provided
		$path = $input->getOption('path');

		// Validate and bootstrap WordPress
		$validation = $this->bootstrap->validateInstallation($path);

		if (!$validation['valid']) {
			$output->writeln('<error>WordPress installation validation failed:</error>');
			foreach ($validation['errors'] as $error) {
				$output->writeln("  <error>• {$error}</error>");
			}
			return Command::FAILURE;
		}

		$this->wpPath = $validation['path'];
		$this->wpLoaded = $validation['loaded'];

		// Reload aliases from this WordPress installation's .push/ directory
		$app = $this->getApplication();
		if ($app instanceof Application) {
			$app->getAliasManager()->loadAliasesFromPath($this->wpPath);
		}

		// Execute the actual command
		return $this->executeCommand($input, $output);
	}

	/**
	 * Execute the actual command logic
	 * Override this method in subclasses
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	abstract protected function executeCommand(InputInterface $input, OutputInterface $output): int;

	/**
	 * Get WordPress installation path
	 *
	 * @return string|null
	 */
	protected function getWpPath(): ?string
	{
		return $this->wpPath;
	}

	/**
	 * Check if WordPress is loaded
	 *
	 * @return bool
	 */
	protected function isWpLoaded(): bool
	{
		return $this->wpLoaded;
	}

	/**
	 * Get Bootstrap instance
	 *
	 * @return Bootstrap
	 */
	protected function getBootstrap(): Bootstrap
	{
		return $this->bootstrap;
	}
}


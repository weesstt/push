<?php

namespace Push\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Version command - displays WordPress version
 */
class VersionCommand extends BaseCommand
{
	/**
	 * Configure the command
	 */
	protected function configure()
	{
		parent::configure();
		
		$this
			->setName('version')
			->setDescription('Display WordPress version')
			->setHelp('This command displays the WordPress version of the current installation.');
	}

	/**
	 * Execute the actual command logic
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function executeCommand(InputInterface $input, OutputInterface $output): int
	{
		if (!$this->isWpLoaded()) {
			$output->writeln('<error>WordPress is not loaded</error>');
			return self::FAILURE;
		}

		$version = get_bloginfo('version');
		
		if ($version) {
			$output->writeln($version);
			return self::SUCCESS;
		}

		$output->writeln('<error>Could not determine WordPress version</error>');
		return self::FAILURE;
	}
}


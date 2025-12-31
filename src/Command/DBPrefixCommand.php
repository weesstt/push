<?php

namespace Push\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Database prefix command - displays WordPress database table prefix
 */
class DBPrefixCommand extends BaseCommand
{
	/**
	 * Configure the command
	 */
	protected function configure()
	{
		parent::configure();
		
		$this
			->setName('db-prefix')
			->setDescription('Display WordPress database table prefix')
			->setHelp('This command displays the database table prefix used by WordPress.');
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

		global $wpdb;
		
		if (!isset($wpdb) || !($wpdb instanceof \wpdb)) {
			$output->writeln('<error>WordPress database object not available</error>');
			return self::FAILURE;
		}

		$prefix = $wpdb->prefix;
		
		if ($prefix) {
			$output->writeln($prefix);
			return self::SUCCESS;
		}

		$output->writeln('<error>Could not determine database prefix</error>');
		return self::FAILURE;
	}
}


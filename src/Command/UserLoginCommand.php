<?php

namespace Push\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Push\Auth\LoginToken;

/**
 * User login command - generates a one-time login URL
 * 
 * Full command: user-login
 * Short alias: uli
 */
class UserLoginCommand extends BaseCommand
{
	/**
	 * Configure the command
	 */
	protected function configure()
	{
		parent::configure();
		
		$this
			->setName('user-login')
			->setAliases(['uli'])
			->setDescription('Generate a one-time login URL for the admin user')
			->setHelp('This command generates a secure, one-time login URL that expires after 1 hour.');
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

		// Get the first admin user
		$adminUsers = get_users(['role' => 'administrator', 'number' => 1, 'orderby' => 'ID']);
		if (empty($adminUsers)) {
			$output->writeln('<error>No admin user found</error>');
			return self::FAILURE;
		}

		$user = $adminUsers[0];

		// Create login token
		$token = LoginToken::createLoginToken($user->ID);
		
		// Get the login URL
		$loginUrl = LoginToken::getLoginUrl($token);

		// Output the URL
		$output->writeln($loginUrl);

		return self::SUCCESS;
	}
}

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
			->setDescription('Generate a one-time login URL for a WordPress user')
			->setHelp('This command generates a secure, one-time login URL that expires after 1 hour.')
			->addOption(
				'uid',
				null,
				InputOption::VALUE_REQUIRED,
				'Login as user with this ID'
			)
			->addOption(
				'mail',
				null,
				InputOption::VALUE_REQUIRED,
				'Login as user with this email address'
			)
			->addOption(
				'name',
				null,
				InputOption::VALUE_REQUIRED,
				'Login as user with this username'
			);
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

		// Find user based on options
		$user = $this->findUser($input, $output);
		
		if (!$user) {
			return self::FAILURE;
		}

		// Create login token
		$token = LoginToken::createLoginToken($user->ID);
		
		// Get the login URL
		$loginUrl = LoginToken::getLoginUrl($token);

		// Output the URL
		$output->writeln($loginUrl);

		return self::SUCCESS;
	}

	/**
	 * Find user based on input options
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return \WP_User|null
	 */
	protected function findUser(InputInterface $input, OutputInterface $output)
	{
		$uid = $input->getOption('uid');
		$email = $input->getOption('mail');
		$name = $input->getOption('name');

		// By user ID
		if ($uid !== null) {
			$user = get_user_by('ID', (int) $uid);
			if (!$user) {
				$output->writeln("<error>User with ID '{$uid}' not found</error>");
				return null;
			}
			return $user;
		}

		// By email
		if ($email !== null) {
			$user = get_user_by('email', $email);
			if (!$user) {
				$output->writeln("<error>User with email '{$email}' not found</error>");
				return null;
			}
			return $user;
		}

		// By username
		if ($name !== null) {
			$user = get_user_by('login', $name);
			if (!$user) {
				$output->writeln("<error>User with username '{$name}' not found</error>");
				return null;
			}
			return $user;
		}

		// Default: first admin user
		$adminUsers = get_users(['role' => 'administrator', 'number' => 1, 'orderby' => 'ID']);
		if (empty($adminUsers)) {
			$output->writeln('<error>No admin user found</error>');
			return null;
		}

		return $adminUsers[0];
	}
}

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
			->setHelp('This command generates a secure, one-time login URL that expires after 1 hour.')
			->addOption(
				'browser',
				'b',
				InputOption::VALUE_OPTIONAL,
				'Open in browser (optionally specify browser name)',
				false
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

		// Optionally open in browser
		$browser = $input->getOption('browser');
		if ($browser !== false) {
			$this->openInBrowser($loginUrl, $browser ?: 'default', $output);
		}

		return self::SUCCESS;
	}

	/**
	 * Open URL in browser
	 *
	 * @param string $url URL to open
	 * @param string $browser Browser name or 'default'
	 * @param OutputInterface $output Output interface
	 */
	protected function openInBrowser(string $url, string $browser, OutputInterface $output): void
	{
		$command = null;

		if (PHP_OS_FAMILY === 'Darwin') {
			if ($browser === 'default') {
				$command = 'open';
			} else {
				$browserMap = [
					'chrome' => 'open -a "Google Chrome"',
					'firefox' => 'open -a Firefox',
					'safari' => 'open -a Safari',
					'edge' => 'open -a "Microsoft Edge"',
				];
				$command = $browserMap[strtolower($browser)] ?? 'open';
			}
		} elseif (PHP_OS_FAMILY === 'Linux') {
			if ($browser === 'default') {
				$command = 'xdg-open';
			} else {
				$browserMap = [
					'chrome' => 'google-chrome',
					'chromium' => 'chromium-browser',
					'firefox' => 'firefox',
				];
				$command = $browserMap[strtolower($browser)] ?? 'xdg-open';
			}
		}

		if ($command) {
			$fullCommand = escapeshellcmd($command) . ' ' . escapeshellarg($url);
			exec($fullCommand . ' > /dev/null 2>&1 &');
		} else {
			$output->writeln('<comment>Browser opening not supported on this platform</comment>');
		}
	}
}

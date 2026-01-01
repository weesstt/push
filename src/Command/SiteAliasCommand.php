<?php

namespace Push\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Push\Alias\AliasManager;

/**
 * Site alias command - list and display site aliases
 * 
 * Full command: site-alias
 * Short alias: sa
 */
class SiteAliasCommand extends BaseCommand
{
	/**
	 * @var AliasManager
	 */
	protected $aliasManager;

	/**
	 * Configure the command
	 */
	protected function configure()
	{
		parent::configure();
		
		$this
			->setName('site-alias')
			->setAliases(['sa'])
			->setDescription('List and display site aliases')
			->setHelp(
				"List all defined site aliases or show details for a specific alias.\n\n" .
				"Aliases support glob patterns where '*' is replaced with an environment value.\n" .
				"Example: @provider.mysite.* can be used as @provider.mysite.dev"
			)
			->addArgument(
				'alias',
				InputArgument::OPTIONAL,
				'Specific alias to display (e.g., @provider.mysite.dev)'
			)
			->addOption(
				'format',
				'f',
				InputOption::VALUE_REQUIRED,
				'Output format: yaml, json, or list',
				'list'
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
		$this->aliasManager = new AliasManager();
		
		$aliasArg = $input->getArgument('alias');
		$format = $input->getOption('format');

		// If a specific alias is requested, show just that one
		if ($aliasArg !== null) {
			return $this->showAlias($aliasArg, $format, $output);
		}

		// Otherwise, list all aliases
		return $this->listAliases($format, $output);
	}

	/**
	 * Show a specific alias
	 *
	 * @param string $aliasName
	 * @param string $format
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function showAlias(string $aliasName, string $format, OutputInterface $output): int
	{
		// Ensure @ prefix
		if (substr($aliasName, 0, 1) !== '@') {
			$aliasName = '@' . $aliasName;
		}

		$alias = $this->aliasManager->getAlias($aliasName);

		if ($alias === null) {
			$output->writeln("<error>Alias '{$aliasName}' not found</error>");
			
			// Show available aliases as hint
			$available = array_keys($this->aliasManager->getAliases());
			if (!empty($available)) {
				$output->writeln('');
				$output->writeln('<comment>Available aliases:</comment>');
				foreach ($available as $name) {
					$output->writeln("  {$name}");
				}
			}
			
			return self::FAILURE;
		}

		$this->outputAlias($alias->getName(), $alias->toArray(), $format, $output);

		return self::SUCCESS;
	}

	/**
	 * List all aliases
	 *
	 * @param string $format
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function listAliases(string $format, OutputInterface $output): int
	{
		$aliases = $this->aliasManager->getAliases();

		if (empty($aliases)) {
			$output->writeln('<comment>No aliases defined.</comment>');
			$output->writeln('');
			$output->writeln('Create aliases in <info>.push/aliases.yml</info> in your WordPress root.');
			$output->writeln('');
			$output->writeln('Example:');
			$output->writeln($this->getExampleYaml());
			
			return self::SUCCESS;
		}

		// Output all aliases
		foreach ($aliases as $name => $alias) {
			$this->outputAlias($name, $alias->toArray(), $format, $output);
			if ($format !== 'json') {
				$output->writeln('');
			}
		}

		return self::SUCCESS;
	}

	/**
	 * Output a single alias in the requested format
	 *
	 * @param string $name
	 * @param array $config
	 * @param string $format
	 * @param OutputInterface $output
	 */
	protected function outputAlias(string $name, array $config, string $format, OutputInterface $output): void
	{
		switch ($format) {
			case 'json':
				$output->writeln(json_encode([$name => $config], JSON_PRETTY_PRINT));
				break;

			case 'list':
				$output->writeln("<info>{$name}</info>");
				$this->outputConfigList($config, $output, '  ');
				break;

			case 'yaml':
			default:
				$output->writeln("<info>'{$name}':</info>");
				$output->writeln('');
				$yamlOutput = Yaml::dump($config, 4, 2);
				// Indent the YAML output
				$lines = explode("\n", rtrim($yamlOutput));
				foreach ($lines as $line) {
					$output->writeln('  ' . $line);
				}
				break;
		}
	}

	/**
	 * Output config as indented list
	 *
	 * @param array $config
	 * @param OutputInterface $output
	 * @param string $indent
	 */
	protected function outputConfigList(array $config, OutputInterface $output, string $indent = ''): void
	{
		foreach ($config as $key => $value) {
			if (is_array($value)) {
				$output->writeln("{$indent}<comment>{$key}:</comment>");
				$this->outputConfigList($value, $output, $indent . '  ');
			} else {
				$displayValue = is_bool($value) ? ($value ? 'true' : 'false') : $value;
				$output->writeln("{$indent}<comment>{$key}:</comment> {$displayValue}");
			}
		}
	}

	/**
	 * Get example YAML for new users
	 *
	 * @return string
	 */
	protected function getExampleYaml(): string
	{
		return <<<'YAML'
<info>
# Remote alias with glob pattern
'@provider.mysite.*':
  host: 'appserver.*.abc123.drush.in'
  user: '*.abc123'
  uri: '*-mysite.provider.io'
  paths:
    files: files
  ssh:
    options: '-p 2222 -o "AddressFamily inet"'
    tty: false
</info>
YAML;
	}
}


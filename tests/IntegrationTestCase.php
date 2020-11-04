<?php declare(strict_types = 1);

namespace AsyncConnection;

use AsyncConnection\Smtp\SmtpSettings;
use Exception;
use Nette\DI\ContainerLoader;
use function is_file;

class IntegrationTestCase extends TestCase
{

	protected function getSettings(): IntegrationTestSettings
	{
		$configurationFile = __DIR__ . '/config.neon';
		if (!is_file($configurationFile)) {
			throw new Exception('Please copy tests/config.neon.template to tests/config.neon and replace parameter placeholders with real data. Otherwise integration tests can not be run.');
		}
		$loader = new ContainerLoader(__DIR__ . '/../temp', true);
		$class = $loader->load(static function ($compiler) use ($configurationFile): void {
			$compiler->loadConfig($configurationFile);
		});
		$container = new $class();
		$parameters = $container->getParameters();
		$smtpSettings = $parameters['smtpSettings'];
		$testInboxSettings = $parameters['testInboxSettings'];

		return new IntegrationTestSettings(
			$parameters['skipIntegrationTests'],
			$parameters['ignoreTimeoutErrors'],
			$parameters['emailFrom'],
			$parameters['emailTo'],
			new SmtpSettings(
				$smtpSettings['host'],
				$smtpSettings['port'],
				$smtpSettings['hello'],
				$smtpSettings['username'],
				$smtpSettings['password'],
			),
			new TestInboxSettings(
				$testInboxSettings['mailbox'],
				$testInboxSettings['username'],
				$testInboxSettings['password'],
			),
		);
	}

}

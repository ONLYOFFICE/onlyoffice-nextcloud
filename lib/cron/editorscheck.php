<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2023
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

namespace OCA\Onlyoffice\Cron;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DocumentService;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;

class EditorsCheck extends TimedJob {

	/**
	 * Application name
	 *
	 * @var string
	 */
	private $appName;

	/**
	 * Url generator service
	 *
	 * @var IURLGenerator
	 */
	private $urlGenerator;

	/**
	 * Logger
	 *
	 * @var OCP\ILogger
	 */
	private $logger;

	/**
	 * Application configuration
	 *
	 * @var AppConfig
	 */
	private $config;

	/**
	 * @param string $AppName - application name
	 * @param ITimeFactory $time - time
	 * @param AppConfig $config - application configuration
	 */

	/**
	 * l10n service
	 *
	 * @var IL10N
	 */
	private $trans;

	/**
	 * Hash generator
	 *
	 * @var Crypt
	 */
	private $crypt;

	/**
	 * @param string $AppName - application name
	 * @param IURLGenerator $urlGenerator - url generator service
	 * @param ITimeFactory $time - time
	 * @param AppConfig $config - application configuration
	 * @param IL10N $trans - l10n service
	 * @param Crypt $crypt - crypt service
	 */
	public function __construct(string $AppName,
							IURLGenerator $urlGenerator,
							ITimeFactory $time,
							AppConfig $config,
							IL10N $trans,
							Crypt $crypt) {
		parent::__construct($time);
		$this->appName = $AppName;
		$this->urlGenerator = $urlGenerator;

		$this->logger = \OC::$server->getLogger();
		$this->config = $config;
		$this->trans = $trans;
		$this->crypt = $crypt;
		$this->setInterval($this->config->GetEditorsCheckInterval());
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);
	}

	protected function run($argument) {
		$documentService = new DocumentService($this->trans, $this->config);
		list ($error, $version) = $documentService->checkDocServiceUrl($this->urlGenerator, $this->crypt);

		if (!empty($error)) {
			$this->logger->info("Connection with OO-server lost!", array("app" => $this->appName));
		}
	}

}

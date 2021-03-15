<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2021
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

namespace OCA\Onlyoffice\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use OCP\IURLGenerator;
use OCP\IL10N;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\DocumentService;
use OCA\Onlyoffice\Crypt;

class DocumentServer extends Command {

    /**
     * Application configuration
     *
     * @var AppConfig
     */
    private $config;

    /**
     * l10n service
     *
     * @var IL10N
     */
    private $trans;

    /**
     * Url generator service
     *
     * @var IURLGenerator
     */
    private $urlGenerator;

    /**
     * Hash generator
     *
     * @var Crypt
     */
    private $crypt;

    /**
     * @param AppConfig $config - application configuration
     * @param IL10N $trans - l10n service
     * @param IURLGenerator $urlGenerator - url generator service
     * @param Crypt $crypt - hash generator
     */
    public function __construct(AppConfig $config,
                                IL10N $trans,
                                IURLGenerator $urlGenerator,
                                Crypt $crypt) {
        parent::__construct();
        $this->config = $config;
        $this->trans = $trans;
        $this->urlGenerator = $urlGenerator;
        $this->crypt = $crypt;
    }

    /**
     * Configures the current command.
     */
    protected function configure() {
        $this
            ->setName("onlyoffice:documentserver")
            ->setDescription("Manage document server")
            ->addOption("check",
                         null,
                         InputOption::VALUE_NONE,
                         "Check connection document server");
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface $input - input data
     * @param OutputInterface $output - output data
     *
     * @return int 0 if everything went fine, or an exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $check = $input->getOption("check");

        $documentserver = $this->config->GetDocumentServerUrl(true);
        if (empty($documentserver)) {
            $output->writeln("<info>Document server is not configured</info>");
            return 1;
        }

        if ($check) {
            $documentService = new DocumentService($this->trans, $this->config);

            list ($error, $version) = $documentService->checkDocServiceUrl($this->urlGenerator, $this->crypt);
            $this->config->SetSettingsError($error);

            if (!empty($error)) {
                $output->writeln("<error>Error connection: $error</error>");
                return 1;
            } else {
                $output->writeln("<info>Document server $documentserver version $version is successfully connected</info>");
                return 0;
            }
        }

        $output->writeln("<info>The current document server: $documentserver</info>");
        return 0;
    }
}
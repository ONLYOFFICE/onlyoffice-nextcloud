<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2025
 *
 * This program is a free software product.
 * You can redistribute it and/or modify it under the terms of the GNU Affero General Public License
 * (AGPL) version 3 as published by the Free Software Foundation.
 * In accordance with Section 7(a) of the GNU AGPL its Section 15 shall be amended to the effect
 * that Ascensio System SIA expressly excludes the warranty of non-infringement of any third-party rights.
 *
 * This program is distributed WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * For details, see the GNU AGPL at: http://www.gnu.org/licenses/agpl-3.0.html
 *
 * You can contact Ascensio System SIA at 20A-12 Ernesta Birznieka-Upisha street, Riga, Latvia, EU, LV-1050.
 *
 * The interactive user interfaces in modified source and object code versions of the Program
 * must display Appropriate Legal Notices, as required under Section 5 of the GNU AGPL version 3.
 *
 * Pursuant to Section 7(b) of the License you must retain the original Product logo when distributing the program.
 * Pursuant to Section 7(e) we decline to grant you any rights under trademark law for use of our trademarks.
 *
 * All the Product's GUI elements, including illustrations and icon sets, as well as technical
 * writing content are licensed under the terms of the Creative Commons Attribution-ShareAlike 4.0 International.
 * See the License terms at http://creativecommons.org/licenses/by-sa/4.0/legalcode
 *
 */

namespace OCA\Onlyoffice\Command;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DocumentService;
use OCP\IL10N;
use OCP\IURLGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
    public function __construct(
        AppConfig $config,
        IL10N $trans,
        IURLGenerator $urlGenerator,
        Crypt $crypt
    ) {
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
            ->addOption(
                "check",
                null,
                InputOption::VALUE_NONE,
                "Check connection document server"
            );
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

        $documentserver = $this->config->getDocumentServerUrl(true);
        if (empty($documentserver)) {
            $output->writeln("<info>Document server is not configured</info>");
            return 1;
        }

        if ($check) {
            $documentService = new DocumentService($this->trans, $this->config);

            list($error, $version) = $documentService->checkDocServiceUrl($this->urlGenerator, $this->crypt);
            $this->config->setSettingsError($error);

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

<?php
/*
 * Copyright (C) Ascensio System SIA, 2009-2026
 *
 * This program is a free software product. You can redistribute it and/or
 * modify it under the terms of the GNU Affero General Public License (AGPL)
 * version 3 as published by the Free Software Foundation, together with the
 * additional terms provided in the LICENSE file.
 *
 * This program is distributed WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. For
 * details, see the GNU AGPL at: https://www.gnu.org/licenses/agpl-3.0.html
 *
 * You can contact Ascensio System SIA by email at info@onlyoffice.com
 * or by postal mail at 20A-6 Ernesta Birznieka-Upisha Street, Riga,
 * LV-1050, Latvia, European Union.
 *
 * The interactive user interfaces in modified versions of the Program
 * are required to display Appropriate Legal Notices in accordance with
 * Section 5 of the GNU AGPL version 3.
 *
 * No trademark rights are granted under this License.
 *
 * All non-code elements of the Product, including illustrations,
 * icon sets, and technical writing content, are licensed under the
 * Creative Commons Attribution-ShareAlike 4.0 International License:
 * https://creativecommons.org/licenses/by-sa/4.0/legalcode
 *
 * This license applies only to such non-code elements and does not
 * modify or replace the licensing terms applicable to the Program's
 * source code, which remains licensed under the GNU Affero General
 * Public License v3.
 *
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\Onlyoffice\Command;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\DocumentService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DocumentServer extends Command {

    public function __construct(
        private readonly AppConfig $appConfig,
        private readonly DocumentService $documentService
    ) {
        parent::__construct();
    }

    /**
     * Configures the current command.
     */
    protected function configure(): void {
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
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $check = $input->getOption("check");

        $documentserver = $this->appConfig->getDocumentServerUrl(true);
        if (empty($documentserver)) {
            $output->writeln("<info>Document server is not configured</info>");
            return 1;
        }

        if ($check) {
            [$error, $version] = $this->documentService->checkDocServiceUrl();
            $this->appConfig->setSettingsError($error);

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

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

namespace OCA\Onlyoffice;

use OCP\App\IAppManager;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * Builds editor plugin configuration from the bundled plugin manifests.
 */
class PluginManager {

    private const AUTOFILL = "aiautofill";

    public function __construct(
        private readonly string $appName,
        private readonly IAppManager $appManager,
        private readonly IURLGenerator $urlGenerator,
        private readonly Crypt $crypt,
        private readonly LoggerInterface $logger
    ) {
    }

    public function isInstalled(string $name = self::AUTOFILL): bool {
        return $this->readConfig($name) !== null;
    }

    /**
     * Build the editorConfig.plugins block for the autofill plugin.
     *
     * @param array $payload - data encoded into the plugin code and read back by the data endpoint
     *
     * @return array|null - the plugins config, or null when the plugin is not installed
     */
    public function getAutofillConfig(array $payload): ?array {
        $config = $this->readConfig(self::AUTOFILL);
        if ($config === null || empty($config["guid"])) {
            $this->logger->debug("Autofill plugin is not installed or its manifest has no guid");
            return null;
        }

        $guid = $config["guid"];

        return [
            "autostart" => [$guid],
            "options" => [
                $guid => [
                    "code" => $this->crypt->getHash($payload),
                    "callback" => $this->urlGenerator->linkToRouteAbsolute($this->appName . ".plugin.data"),
                ],
            ],
            "pluginsData" => [
                $this->urlGenerator->getAbsoluteURL($this->urlGenerator->linkTo($this->appName, "assets/plugin-" . self::AUTOFILL . "/config.json")),
            ],
        ];
    }

    private function readConfig(string $name): ?array {
        $path = $this->appManager->getAppPath($this->appName) . "/assets/plugin-" . $name . "/config.json";
        if (!is_file($path)) {
            return null;
        }

        $config = json_decode(file_get_contents($path), true);
        return \is_array($config) ? $config : null;
    }
}

<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2022
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

namespace OCA\Onlyoffice\Migration;

use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCP\Files\IMimeTypeDetector;

use OC\Core\Command\Maintenance\Mimetype\GenerateMimetypeFileBuilder;

use Psr\Log\LoggerInterface;

use OCA\Onlyoffice\AppConfig;

class MimeRepair implements IRepairStep {

    private const MIMETYPELIST = "mimetypelist.js";
    private const CUSTOM_MIMETYPEALIASES = "mimetypealiases.json";

    private const DOCUMENT_ALIAS = "x-office/document";

    /**
     * Application name
     *
     * @var string
     */
    private static $appName = "onlyoffice";

    /**
     * Application configuration
     *
     * @var AppConfig
     */
    private $config;

    /**
     * Logger Interface
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Mimetype detector
     *
     * @var IMimeTypeDetector
     */
    protected $detector;

    public function __construct(LoggerInterface $logger,
                                IMimeTypeDetector $detector) {
        $this->logger = $logger;
        $this->detector = $detector;
        $this->config = new AppConfig(self::$appName);
    }

    /**
     * Returns the step's name
     */
    public function getName() {
        return self::$appName;
    }

    /**
     * @param IOutput $output
     */
    public function run(IOutput $output) {
        $this->logger->debug("Mimetypes repair run", ["app" => self::$appName]);

        $customAliasPath = \OC::$SERVERROOT . "/config/" . self::CUSTOM_MIMETYPEALIASES;

        $formats = $this->config->FormatsSetting();
        $mimes = [
            $formats["docxf"]["mime"] => self::DOCUMENT_ALIAS,
            $formats["oform"]["mime"] => self::DOCUMENT_ALIAS
        ];

        $customAlias = $mimes;
        if (file_exists($customAliasPath)) {
            $customAlias = json_decode(file_get_contents($customAliasPath), true);
            foreach ($mimes as $mime => $icon) {
                if (!isset($customAlias[$mime])) {
                    $customAlias[$mime] = $icon;
                }
            }
        }

        file_put_contents($customAliasPath, json_encode($customAlias, JSON_PRETTY_PRINT));

        //matches the command maintenance:mimetype:update-js
        //from OC\Core\Command\Maintenance\Mimetype\UpdateJS
        $aliases = $this->detector->getAllAliases();
        $generatedMimetypeFile = new GenerateMimetypeFileBuilder();
        file_put_contents(\OC::$SERVERROOT . "/core/js/" . self::MIMETYPELIST, $generatedMimetypeFile->generateFile($aliases));
    }
}
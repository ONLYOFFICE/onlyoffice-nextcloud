<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2020
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

namespace OCA\Onlyoffice\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\DirectEditing\RegisterDirectEditorEvent;
use OCP\Files\IMimeTypeDetector;
use OCP\Util;
use OCP\IPreview;

use OCA\Viewer\Event\LoadViewer;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Controller\CallbackController;
use OCA\Onlyoffice\Controller\EditorController;
use OCA\Onlyoffice\Controller\SettingsController;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DirectEditor;
use OCA\Onlyoffice\Hooks;
use OCA\Onlyoffice\Preview;

class Application extends App {

    /**
     * Application configuration
     *
     * @var AppConfig
     */
    public $appConfig;

    /**
     * Hash generator
     *
     * @var Crypt
     */
    public $crypt;

    public function __construct(array $urlParams = []) {
        $appName = "onlyoffice";

        parent::__construct($appName, $urlParams);

        $this->appConfig = new AppConfig($appName);
        $this->crypt = new Crypt($this->appConfig);

        // Default script and style if configured
        $eventDispatcher = \OC::$server->getEventDispatcher();
        $eventDispatcher->addListener("OCA\Files::loadAdditionalScripts",
            function () {
                if (!empty($this->appConfig->GetDocumentServerUrl())
                    && $this->appConfig->SettingsAreSuccessful()
                    && $this->appConfig->isUserAllowedToUse()) {
                    Util::addScript("onlyoffice", "desktop");
                    Util::addScript("onlyoffice", "main");

                    if ($this->appConfig->GetSameTab()) {
                        Util::addScript("onlyoffice", "listener");
                    }

                    Util::addStyle("onlyoffice", "main");
                }
            });

        if (class_exists(LoadViewer::class)) {
            $eventDispatcher->addListener(LoadViewer::class,
                function () {
                    if (!empty($this->appConfig->GetDocumentServerUrl())
                        && $this->appConfig->SettingsAreSuccessful()
                        && $this->appConfig->isUserAllowedToUse()) {
                        Util::addScript("onlyoffice", "viewer");
                        Util::addScript("onlyoffice", "listener");

                        Util::addStyle("onlyoffice", "viewer");

                        $csp = new ContentSecurityPolicy();
                        $csp->addAllowedFrameDomain("'self'");
                        $cspManager = $this->getContainer()->getServer()->getContentSecurityPolicyManager();
                        $cspManager->addDefaultPolicy($csp);
                    }
                });
        }

        $eventDispatcher->addListener("OCA\Files_Sharing::loadAdditionalScripts",
            function () {
                if (!empty($this->appConfig->GetDocumentServerUrl())
                    && $this->appConfig->SettingsAreSuccessful()) {
                    Util::addScript("onlyoffice", "main");

                    if ($this->appConfig->GetSameTab()) {
                        Util::addScript("onlyoffice", "listener");
                    }

                    Util::addStyle("onlyoffice", "main");
                }
            });

        require_once __DIR__ . "/../3rdparty/jwt/BeforeValidException.php";
        require_once __DIR__ . "/../3rdparty/jwt/ExpiredException.php";
        require_once __DIR__ . "/../3rdparty/jwt/SignatureInvalidException.php";
        require_once __DIR__ . "/../3rdparty/jwt/JWT.php";

        $container = $this->getContainer();

        //todo: remove in v20
        $detector = $container->query(IMimeTypeDetector::class);
        $detector->getAllMappings();
        $detector->registerType("ott", "application/vnd.oasis.opendocument.text-template");
        $detector->registerType("ots", "application/vnd.oasis.opendocument.spreadsheet-template");
        $detector->registerType("otp", "application/vnd.oasis.opendocument.presentation-template");

        $previewManager = $container->query(IPreview::class);
        $previewManager->registerProvider(Preview::getMimeTypeRegex(), function() use ($container) {
            return $container->query(Preview::class);
        });

        $container->registerService("L10N", function ($c) {
            return $c->query("ServerContainer")->getL10N($c->query("AppName"));
        });

        $container->registerService("RootStorage", function ($c) {
            return $c->query("ServerContainer")->getRootFolder();
        });

        $container->registerService("UserSession", function ($c) {
            return $c->query("ServerContainer")->getUserSession();
        });

        $container->registerService("UserManager", function ($c) {
            return $c->query("ServerContainer")->getUserManager();
        });

        $container->registerService("Logger", function ($c) {
            return $c->query("ServerContainer")->getLogger();
        });

        $container->registerService("URLGenerator", function ($c) {
            return $c->query("ServerContainer")->getURLGenerator();
        });

        if (class_exists("OCP\DirectEditing\RegisterDirectEditorEvent")) {
            $container->registerService("DirectEditor", function ($c) {
                return new DirectEditor(
                    $c->query("AppName"),
                    $c->query("URLGenerator"),
                    $c->query("L10N"),
                    $c->query("Logger"),
                    $this->appConfig,
                    $this->crypt
                );
            });

            $eventDispatcher->addListener(RegisterDirectEditorEvent::class,
                function (RegisterDirectEditorEvent $event) use ($container) {
                    if (!empty($this->appConfig->GetDocumentServerUrl())
                        && $this->appConfig->SettingsAreSuccessful()) {
                        $editor = $container->query("DirectEditor");
                        $event->register($editor);
                    }
                });
        }


        // Controllers
        $container->registerService("SettingsController", function ($c) {
            return new SettingsController(
                $c->query("AppName"),
                $c->query("Request"),
                $c->query("URLGenerator"),
                $c->query("L10N"),
                $c->query("Logger"),
                $this->appConfig,
                $this->crypt
            );
        });

        $container->registerService("EditorController", function ($c) {
            return new EditorController(
                $c->query("AppName"),
                $c->query("Request"),
                $c->query("RootStorage"),
                $c->query("UserSession"),
                $c->query("UserManager"),
                $c->query("URLGenerator"),
                $c->query("L10N"),
                $c->query("Logger"),
                $this->appConfig,
                $this->crypt,
                $c->query("IManager"),
                $c->query("Session")
            );
        });

        $container->registerService("CallbackController", function ($c) {
            return new CallbackController(
                $c->query("AppName"),
                $c->query("Request"),
                $c->query("RootStorage"),
                $c->query("UserSession"),
                $c->query("UserManager"),
                $c->query("L10N"),
                $c->query("Logger"),
                $this->appConfig,
                $this->crypt,
                $c->query("IManager")
            );
        });


        Hooks::connectHooks();
    }
}

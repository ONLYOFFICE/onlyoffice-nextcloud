<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2020
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

namespace OCA\Onlyoffice\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\DirectEditing\RegisterDirectEditorEvent;
use OCP\Util;

use OCA\Viewer\Event\LoadViewer;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Controller\CallbackController;
use OCA\Onlyoffice\Controller\EditorController;
use OCA\Onlyoffice\Controller\SettingsController;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DirectEditor;

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
            function() {
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
                function() {
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
            function() {
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

        $container->registerService("L10N", function($c) {
            return $c->query("ServerContainer")->getL10N($c->query("AppName"));
        });

        $container->registerService("RootStorage", function($c) {
            return $c->query("ServerContainer")->getRootFolder();
        });

        $container->registerService("UserSession", function($c) {
            return $c->query("ServerContainer")->getUserSession();
        });

        $container->registerService("UserManager", function($c) {
            return $c->query("ServerContainer")->getUserManager();
        });

        $container->registerService("Logger", function($c) {
            return $c->query("ServerContainer")->getLogger();
        });

        $container->registerService("URLGenerator", function($c) {
            return $c->query("ServerContainer")->getURLGenerator();
        });

        if (class_exists("OCP\DirectEditing\RegisterDirectEditorEvent")) {
            $container->registerService("DirectEditor", function($c) {
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
        $container->registerService("SettingsController", function($c) {
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

        $container->registerService("EditorController", function($c) {
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
                $c->query("Session"),
                $c->query("ClientService")
            );
        });

        $container->registerService("CallbackController", function($c) {
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
    }
}

<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2024
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

use OC\EventDispatcher\SymfonyAdapter;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\BackgroundJob\IJobList;
use OCP\Dashboard\RegisterWidgetEvent;
use OCP\DirectEditing\RegisterDirectEditorEvent;
use OCP\Files\Template\FileCreatedFromTemplateEvent;
use OCP\Files\Template\ITemplateManager;
use OCP\Files\Template\TemplateFileCreator;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\Lock\ILockManager;
use OCP\IL10N;
use OCP\IPreview;
use OCP\ITagManager;
use OCP\Notification\IManager;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\Viewer\Event\LoadViewer;
use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Controller\CallbackController;
use OCA\Onlyoffice\Controller\EditorController;
use OCA\Onlyoffice\Controller\EditorApiController;
use OCA\Onlyoffice\Controller\JobListController;
use OCA\Onlyoffice\Controller\SharingApiController;
use OCA\Onlyoffice\Controller\SettingsController;
use OCA\Onlyoffice\Controller\TemplateController;
use OCA\Onlyoffice\Listeners\CreateFromTemplateListener;
use OCA\Onlyoffice\Listeners\FilesListener;
use OCA\Onlyoffice\Listeners\FileSharingListener;
use OCA\Onlyoffice\Listeners\DirectEditorListener;
use OCA\Onlyoffice\Listeners\ViewerListener;
use OCA\Onlyoffice\Listeners\WidgetListener;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DirectEditor;
use OCA\Onlyoffice\Hooks;
use OCA\Onlyoffice\Notifier;
use OCA\Onlyoffice\Preview;
use OCA\Onlyoffice\TemplateProvider;
use OCA\Onlyoffice\SettingsData;
use Psr\Container\ContainerInterface;

class Application extends App implements IBootstrap {

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
    }

    public function register(IRegistrationContext $context): void {
        if (!class_exists('\\Firebase\\JWT\\BeforeValidException')) {
            require_once __DIR__ . "/../../3rdparty/jwt/BeforeValidException.php";
        }
        if (!class_exists('\\Firebase\\JWT\\ExpiredException')) {
            require_once __DIR__ . "/../../3rdparty/jwt/ExpiredException.php";
        }
        if (!class_exists('\\Firebase\\JWT\\SignatureInvalidException')) {
            require_once __DIR__ . "/../../3rdparty/jwt/SignatureInvalidException.php";
        }
        if (!class_exists('\\Firebase\\JWT\\CachedKeySet')) {
            require_once __DIR__ . "/../../3rdparty/jwt/CachedKeySet.php";
        }
        if (!class_exists('\\Firebase\\JWT\\JWT')) {
            require_once __DIR__ . "/../../3rdparty/jwt/JWT.php";
        }
        if (!class_exists('\\Firebase\\JWT\\JWK')) {
            require_once __DIR__ . "/../../3rdparty/jwt/JWK.php";
        }
        if (!class_exists('\\Firebase\\JWT\\Key')) {
            require_once __DIR__ . "/../../3rdparty/jwt/Key.php";
        }

        // Set the leeway for the JWT library in case the system clock is a second off
        \Firebase\JWT\JWT::$leeway = $this->appConfig->getJwtLeeway();

        $context->registerService("L10N", function (ContainerInterface $c) {
            return $c->get("ServerContainer")->getL10N($c->get("AppName"));
        });

        $context->registerService("RootStorage", function (ContainerInterface $c) {
            return $c->get("ServerContainer")->getRootFolder();
        });

        $context->registerService("UserSession", function (ContainerInterface $c) {
            return $c->get("ServerContainer")->getUserSession();
        });

        $context->registerService("UserManager", function (ContainerInterface $c) {
            return $c->get("ServerContainer")->getUserManager();
        });

        $context->registerService("Logger", function (ContainerInterface $c) {
            return $c->get("ServerContainer")->getLogger();
        });

        $context->registerService("URLGenerator", function (ContainerInterface $c) {
            return $c->get("ServerContainer")->getURLGenerator();
        });

        $context->registerService("DirectEditor", function (ContainerInterface $c) {
            return new DirectEditor(
                $c->get("AppName"),
                $c->get("URLGenerator"),
                $c->get("L10N"),
                $c->get("Logger"),
                $this->appConfig,
                $this->crypt
            );
        });

        $context->registerService("SettingsData", function (ContainerInterface $c) {
            return new SettingsData($this->appConfig);
        });

        // Controllers
        $context->registerService("SettingsController", function (ContainerInterface $c) {
            return new SettingsController(
                $c->get("AppName"),
                $c->get("Request"),
                $c->get("URLGenerator"),
                $c->get("L10N"),
                $c->get("Logger"),
                $this->appConfig,
                $this->crypt
            );
        });

        $context->registerService("EditorController", function (ContainerInterface $c) {
            return new EditorController(
                $c->get("AppName"),
                $c->get("Request"),
                $c->get("RootStorage"),
                $c->get("UserSession"),
                $c->get("UserManager"),
                $c->get("URLGenerator"),
                $c->get("L10N"),
                $c->get("Logger"),
                $this->appConfig,
                $this->crypt,
                $c->get("IManager"),
                $c->get("Session"),
                $c->get("GroupManager")
            );
        });

        $context->registerService("SharingApiController", function (ContainerInterface $c) {
            return new SharingApiController(
                $c->get("AppName"),
                $c->get("Request"),
                $c->get("RootStorage"),
                $c->get("Logger"),
                $c->get("UserSession"),
                $c->get("UserManager"),
                $c->get("IManager"),
                $this->appConfig
            );
        });

        $context->registerService("EditorApiController", function (ContainerInterface $c) {
            return new EditorApiController(
                $c->get("AppName"),
                $c->get("Request"),
                $c->get("RootStorage"),
                $c->get("UserSession"),
                $c->get("UserManager"),
                $c->get("URLGenerator"),
                $c->get("L10N"),
                $c->get("Logger"),
                $this->appConfig,
                $this->crypt,
                $c->get("IManager"),
                $c->get("Session"),
                $c->get(ITagManager::class),
                $c->get(ILockManager::class)
            );
        });

        $context->registerService("CallbackController", function (ContainerInterface $c) {
            return new CallbackController(
                $c->get("AppName"),
                $c->get("Request"),
                $c->get("RootStorage"),
                $c->get("UserSession"),
                $c->get("UserManager"),
                $c->get("L10N"),
                $c->get("Logger"),
                $this->appConfig,
                $this->crypt,
                $c->get("IManager"),
                $c->get(ILockManager::class)
            );
        });

        $context->registerService("TemplateController", function (ContainerInterface $c) {
            return new TemplateController(
                $c->get("AppName"),
                $c->get("Request"),
                $c->get("L10N"),
                $c->get("Logger"),
                $c->get(IPreview::class)
            );
        });

        $context->registerEventListener(FileCreatedFromTemplateEvent::class, CreateFromTemplateListener::class);
        $context->registerEventListener(LoadAdditionalScriptsEvent::class, FilesListener::class);
        $context->registerEventListener(RegisterDirectEditorEvent::class, DirectEditorListener::class);
        $context->registerEventListener(LoadViewer::class, ViewerListener::class);
        $context->registerEventListener(BeforeTemplateRenderedEvent::class, FileSharingListener::class);
        $context->registerEventListener(RegisterWidgetEvent::class, WidgetListener::class);

        if (interface_exists("OCP\Files\Template\ICustomTemplateProvider")) {
            $context->registerTemplateProvider(TemplateProvider::class);
        }

        $container = $this->getContainer();

        $previewManager = $container->query(IPreview::class);
        $previewManager->registerProvider(Preview::getMimeTypeRegex(), function () use ($container) {
            return $container->query(Preview::class);
        });

        $detector = $container->query(IMimeTypeDetector::class);
        $detector->getAllMappings();

        $checkBackgroundJobs = new JobListController(
            $container->query("AppName"),
            $container->query("Request"),
            $this->appConfig,
            $container->query(IJobList::class)
        );
        $checkBackgroundJobs->checkAllJobs();

        Hooks::connectHooks();
    }

    public function boot(IBootContext $context): void {

        $context->injectFn(function (IManager $notificationsManager) {
            $notificationsManager->registerNotifierService(Notifier::class);
        });

        if (class_exists("OCP\Files\Template\TemplateFileCreator")) {
            $context->injectFn(function (ITemplateManager $templateManager, IL10N $trans, $appName) {
                if (!empty($this->appConfig->getDocumentServerUrl())
                    && $this->appConfig->settingsAreSuccessful()
                    && $this->appConfig->isUserAllowedToUse()) {
                    $templateManager->registerTemplateFileCreator(function () use ($appName, $trans) {
                        $wordTemplate = new TemplateFileCreator($appName, $trans->t("New document"), ".docx");
                        $wordTemplate->addMimetype("application/vnd.openxmlformats-officedocument.wordprocessingml.document");
                        $wordTemplate->setIconClass("icon-onlyoffice-new-docx");
                        $wordTemplate->setRatio(21/29.7);
                        return $wordTemplate;
                    });

                    $templateManager->registerTemplateFileCreator(function () use ($appName, $trans) {
                        $cellTemplate = new TemplateFileCreator($appName, $trans->t("New spreadsheet"), ".xlsx");
                        $cellTemplate->addMimetype("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
                        $cellTemplate->setIconClass("icon-onlyoffice-new-xlsx");
                        $cellTemplate->setRatio(21/29.7);
                        return $cellTemplate;
                    });

                    $templateManager->registerTemplateFileCreator(function () use ($appName, $trans) {
                        $slideTemplate = new TemplateFileCreator($appName, $trans->t("New presentation"), ".pptx");
                        $slideTemplate->addMimetype("application/vnd.openxmlformats-officedocument.presentationml.presentation");
                        $slideTemplate->setIconClass("icon-onlyoffice-new-pptx");
                        $slideTemplate->setRatio(16/9);
                        return $slideTemplate;
                    });
                }
            });
        }
    }
}

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
use OCA\Onlyoffice\TemplateManager;
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
        \Firebase\JWT\JWT::$leeway = $this->appConfig->GetJwtLeeway();

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
        $previewManager->registerProvider(Preview::getMimeTypeRegex(), function() use ($container) {
            return $container->query(Preview::class);
        });

        $detector = $container->query(IMimeTypeDetector::class);
        $detector->getAllMappings();
        $detector->registerType("docxf", "application/vnd.openxmlformats-officedocument.wordprocessingml.document.docxf");
        $detector->registerType("oform", "application/vnd.openxmlformats-officedocument.wordprocessingml.document.oform");

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

        $context->injectFn(function (SymfonyAdapter $eventDispatcher) {

            if (class_exists("OCP\Files\Template\FileCreatedFromTemplateEvent")) {
                $eventDispatcher->addListener(FileCreatedFromTemplateEvent::class,
                    function (FileCreatedFromTemplateEvent $event) {
                        $template = $event->getTemplate();
                        if ($template === null) {
                            $targetFile = $event->getTarget();
                            $templateEmpty = TemplateManager::GetEmptyTemplate($targetFile->getName());
                            if ($templateEmpty) {
                                $targetFile->putContent($templateEmpty);
                            }
                        }
                    });
            }
        });

        $context->injectFn(function (IManager $notificationsManager) {
            $notificationsManager->registerNotifierService(Notifier::class);
        });

        if (class_exists("OCP\Files\Template\TemplateFileCreator")) {
            $context->injectFn(function(ITemplateManager $templateManager, IL10N $trans, $appName) {
                if (!empty($this->appConfig->GetDocumentServerUrl())
                    && $this->appConfig->SettingsAreSuccessful()
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

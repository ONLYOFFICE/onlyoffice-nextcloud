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

namespace OCA\Onlyoffice\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\DirectEditing\RegisterDirectEditorEvent;
use OCP\Files\Template\FileCreatedFromTemplateEvent;
use OCP\Files\Template\ITemplateManager;
use OCP\Files\Template\TemplateFileCreator;
use OCP\IL10N;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\Files_Versions\Events\VersionRestoredEvent;
use OCA\Viewer\Event\LoadViewer;
use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Controller\JobListController;
use OCA\Onlyoffice\Listeners\CreateFromTemplateListener;
use OCA\Onlyoffice\Listeners\FilesListener;
use OCA\Onlyoffice\Listeners\FileSharingListener;
use OCA\Onlyoffice\Listeners\DirectEditorListener;
use OCA\Onlyoffice\Listeners\ViewerListener;
use OCA\Onlyoffice\Events\DocumentUnsavedEvent;
use OCA\Onlyoffice\Hooks;
use OCA\Onlyoffice\Listeners\ContentSecurityPolicyListener;
use OCA\Onlyoffice\Listeners\DocumentUnsavedListener;
use OCA\Onlyoffice\Listeners\FileListener;
use OCA\Onlyoffice\Listeners\FileVersionsListener;
use OCA\Onlyoffice\Listeners\ShareListener;
use OCA\Onlyoffice\Listeners\UserListener;
use OCA\Onlyoffice\Middleware\DesktopMiddleware;
use OCA\Onlyoffice\Notifier;
use OCA\Onlyoffice\Preview;
use OCA\Onlyoffice\TemplateProvider;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Share\Events\ShareDeletedEvent;
use OCP\User\Events\UserDeletedEvent;
use OCP\Server;

class Application extends App implements IBootstrap {
    public const APP_ID = "onlyoffice";

    private readonly AppConfig $appConfig;

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);

        $this->appConfig = Server::get(AppConfig::class);
    }

    public function register(IRegistrationContext $context): void {
        require_once __DIR__ . "/../../vendor/autoload.php";

        // Set the leeway for the JWT library in case the system clock is a second off
        \Firebase\JWT\JWT::$leeway = $this->appConfig->getJwtLeeway();

        $context->registerMiddleware(DesktopMiddleware::class, true);

        $context->registerEventListener(FileCreatedFromTemplateEvent::class, CreateFromTemplateListener::class);
        $context->registerEventListener(LoadAdditionalScriptsEvent::class, FilesListener::class);
        $context->registerEventListener(RegisterDirectEditorEvent::class, DirectEditorListener::class);
        $context->registerEventListener(LoadViewer::class, ViewerListener::class);
        $context->registerEventListener(AddContentSecurityPolicyEvent::class, ContentSecurityPolicyListener::class);
        $context->registerEventListener(BeforeTemplateRenderedEvent::class, FileSharingListener::class);
        $context->registerEventListener(DocumentUnsavedEvent::class, DocumentUnsavedListener::class);
        $context->registerEventListener(NodeDeletedEvent::class, FileListener::class);
        $context->registerEventListener(NodeWrittenEvent::class, FileListener::class);
        $context->registerEventListener(ShareDeletedEvent::class, ShareListener::class);
        $context->registerEventListener(UserDeletedEvent::class, UserListener::class);
        $context->registerEventListener(VersionRestoredEvent::class, FileVersionsListener::class);

        if (interface_exists(\OCP\Files\Template\ICustomTemplateProvider::class)) {
            $context->registerTemplateProvider(TemplateProvider::class);
        }

        $context->registerPreviewProvider(Preview::class, Preview::getMimeTypeRegex());
        $context->registerNotifierService(Notifier::class);

        Server::get(JobListController::class)->checkAllJobs();
        Hooks::connectHooks();
    }

    public function boot(IBootContext $context): void {
        if (class_exists(TemplateFileCreator::class)) {
            $context->injectFn(function (ITemplateManager $templateManager, IL10N $trans, $appName): void {
                if (!empty($this->appConfig->getDocumentServerUrl())
                    && $this->appConfig->settingsAreSuccessful()
                    && $this->appConfig->isUserAllowedToUse()) {
                    $templateManager->registerTemplateFileCreator(function () use ($appName, $trans): TemplateFileCreator {
                        $wordTemplate = new TemplateFileCreator($appName, $trans->t("New document"), ".docx");
                        $wordTemplate->addMimetype("application/vnd.openxmlformats-officedocument.wordprocessingml.document");
                        $wordTemplate->setIconSvgInline(file_get_contents(__DIR__ . '/../../img/new-docx.svg'));
                        $wordTemplate->setRatio(21/29.7);
                        return $wordTemplate;
                    });

                    $templateManager->registerTemplateFileCreator(function () use ($appName, $trans): TemplateFileCreator {
                        $cellTemplate = new TemplateFileCreator($appName, $trans->t("New spreadsheet"), ".xlsx");
                        $cellTemplate->addMimetype("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
                        $cellTemplate->setIconSvgInline(file_get_contents(__DIR__ . '/../../img/new-xlsx.svg'));
                        $cellTemplate->setRatio(21/29.7);
                        return $cellTemplate;
                    });

                    $templateManager->registerTemplateFileCreator(function () use ($appName, $trans): TemplateFileCreator {
                        $slideTemplate = new TemplateFileCreator($appName, $trans->t("New presentation"), ".pptx");
                        $slideTemplate->addMimetype("application/vnd.openxmlformats-officedocument.presentationml.presentation");
                        $slideTemplate->setIconSvgInline(file_get_contents(__DIR__ . '/../../img/new-pptx.svg'));
                        $slideTemplate->setRatio(16/9);
                        return $slideTemplate;
                    });
                }
            });
        }
    }
}

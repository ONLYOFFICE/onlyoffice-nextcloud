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

use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\IAction;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;
use Psr\Log\LoggerInterface;

class Notifier implements INotifier {

    public function __construct(
        private readonly string $appName,
        private readonly IFactory $l10nFactory,
        private readonly IURLGenerator $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly IUserManager $userManager
    ) {}

    /**
     * Identifier of the notifier, only use [a-z0-9_]
     */
    public function getID(): string {
        return $this->appName;
    }

    /**
     * Human readable name describing the notifier
     */
    public function getName(): string {
        return $this->appName;
    }

    /**
     * @param INotification $notification - notification object
     * @param string $languageCode - the code of the language that should be used to prepare the notification
     */
    public function prepare(INotification $notification, string $languageCode): INotification {
        if ($notification->getApp() !== $this->appName) {
            throw new UnknownNotificationException("Notification not from " . $this->appName);
        }

        $parameters = $notification->getSubjectParameters();
        $trans = $this->l10nFactory->get($this->appName, $languageCode);
        switch ($notification->getObjectType()) {
            case "editorsCheck":
                $message = $trans->t("Please check the settings to resolve the problem.");
                $appSettingsLink = $this->urlGenerator->getAbsoluteURL("/settings/admin/".$this->appName);
                $action = $notification->createAction();
                $action->setLabel('View settings')
                    ->setParsedLabel($trans->t('View settings'))
                    ->setLink($appSettingsLink, IAction::TYPE_WEB)
                    ->setPrimary(false);
                $notification->addParsedAction($action);
                $notification->setParsedSubject($notification->getObjectId())
                    ->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath($this->appName, 'app-dark.svg')));
                $notification->setParsedMessage($message);
                break;
            case "mention":
                $notifierId = $parameters["notifierId"];
                $fileId = $parameters["fileId"];
                $fileName = $parameters["fileName"];
                $anchor = $parameters["anchor"];

                $this->logger->info("Notify prepare: from $notifierId about $fileId ");

                $notifier = $this->userManager->get($notifierId);
                $notifierName = $notifier->getDisplayName();

                $editorLink = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".editor.index", [
                    "fileId" => $fileId,
                    "anchor" => $anchor
                ]);

                $notification->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath($this->appName, "app-dark.svg")))
                ->setParsedSubject($trans->t("%1\$s mentioned in the %2\$s: \"%3\$s\".", [$notifierName, $fileName, $notification->getObjectId()]))
                ->setRichSubject($trans->t("{notifier} mentioned in the {file}: \"%1\$s\".", [$notification->getObjectId()]), [
                    "notifier" => [
                        "type" => "user",
                        "id" => $notifierId,
                        "name" => $notifierName
                    ],
                    "file" => [
                        "type" => "highlight",
                        "id" => (string)$fileId,
                        "name" => $fileName,
                        "link" => $editorLink
                    ]
                ]);
                break;
            case "document_unsaved":
                $fileId = $parameters["fileId"];
                $fileName = $parameters["fileName"];

                $this->logger->info("Notify prepare: unsaved document $fileId");

                $link = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".editor.index", ["fileId" => $fileId]);
                $action = $notification->createAction();
                $action->setLabel($trans->t('Open'))
                    ->setParsedLabel($trans->t('Open'))
                    ->setLink($link, IAction::TYPE_WEB)
                    ->setPrimary(true);
                $notification->addParsedAction($action);
                $notification->setParsedSubject($trans->t("%1\$s could not be saved. Please open the file again.", [$fileName]))
                    ->setRichSubject($trans->t("{file} could not be saved. Please open the file again."), [
                        "file" => [
                            "type" => "highlight",
                            "id" => (string)$fileId,
                            "name" => $fileName,
                        ]
                    ])
                    ->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath($this->appName, 'app-dark.svg')));
                break;
            default:
                $this->logger->info("Unsupported notification object: ".$notification->getObjectType());
        }
        return $notification;
    }
}

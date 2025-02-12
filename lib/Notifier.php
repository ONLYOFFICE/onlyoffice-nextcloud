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

namespace OCA\Onlyoffice;

use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\IAction;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use Psr\Log\LoggerInterface;

class Notifier implements INotifier {

    /**
     * Application name
     *
     * @var string
     */
    private $appName;

    /**
     * IFactory
     *
     * @var IFactory
     */
    private $l10nFactory;

    /**
     * Url generator service
     *
     * @var IURLGenerator
     */
    private $urlGenerator;

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * User manager
     *
     * @var IUserManager
     */
    private $userManager;

    /**
     * @param string $AppName - application name
     * @param IFactory $l10NFactory - l10n
     * @param IURLGenerator $urlGenerator - url generator service
     * @param LoggerInterface $logger - logger
     * @param IUserManager $userManager - user manager
     */
    public function __construct(
        string $appName,
        IFactory $l10nFactory,
        IURLGenerator $urlGenerator,
        LoggerInterface $logger,
        IUserManager $userManager
    ) {
        $this->appName = $appName;
        $this->l10nFactory = $l10nFactory;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
        $this->userManager = $userManager;
    }

    /**
     * Identifier of the notifier, only use [a-z0-9_]
     *
     * @return string
     */
    public function getID(): string {
        return $this->appName;
    }

    /**
     * Human readable name describing the notifier
     *
     * @return string
     */
    public function getName(): string {
        return $this->appName;
    }

    /**
     * @param INotification $notification - notification object
     * @param string $languageCode - the code of the language that should be used to prepare the notification
     *
     * @return INotification
     */
    public function prepare(INotification $notification, string $languageCode): INotification {
        if ($notification->getApp() !== $this->appName) {
            throw new \InvalidArgumentException("Notification not from " . $this->appName);
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
                        "id" => $fileId,
                        "name" => $fileName,
                        "link" => $editorLink
                    ]
                ]);
                break;
            default:
                $this->logger->info("Unsupported notification object: ".$notification->getObjectType());
        }
        return $notification;
    }
}

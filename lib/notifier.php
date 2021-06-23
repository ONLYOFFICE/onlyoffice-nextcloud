<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2021
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

namespace OCA\Onlyoffice;

use OCP\IURLGenerator;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

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
     * @var ILogger
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
     * @param ILogger $logger - logger
     * @param IUserManager $userManager - user manager
     */
    public function __construct(string $appName,
                                    IFactory $l10nFactory,
                                    IURLGenerator $urlGenerator,
                                    ILogger $logger,
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

        $notifierId = $parameters["notifierId"];
        $fileId = $parameters["fileId"];
        $fileName = $parameters["fileName"];
        $anchor = $parameters["anchor"];

        $this->logger->info("Notify prepare: from $notifierId about $fileId ", ["app" => $this->appName]);

        $notifier = $this->userManager->get($notifierId);
        $notifierName = $notifier->getDisplayName();
        $trans = $this->l10nFactory->get($this->appName, $languageCode);

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
                    "name" => $fileName
                ]
            ]);

        $editorLink = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".editor.index", [
            "fileId" => $fileId,
            "anchor" => $anchor
        ]);

        $notification->setLink($editorLink);

        return $notification;
    }
}
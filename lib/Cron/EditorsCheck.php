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

namespace OCA\Onlyoffice\Cron;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\DocumentService;
use OCA\Onlyoffice\EmailManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * Editors availability check background job
 *
 */
class EditorsCheck extends TimedJob {

    public function __construct(
        ITimeFactory $time,
        private readonly string $appName,
        private readonly IURLGenerator $urlGenerator,
        private readonly AppConfig $appConfig,
        private readonly IL10N $trans,
        private readonly IGroupManager $groupManager,
        private readonly EmailManager $emailManager,
        private readonly LoggerInterface $logger,
        private readonly DocumentService $documentService
    ) {
        parent::__construct($time);
        $this->setInterval($this->appConfig->getEditorsCheckInterval());
        $this->setTimeSensitivity(IJob::TIME_SENSITIVE);
    }

    /**
     * Makes the background check
     *
     * @param array $argument unused argument
     */
    protected function run($argument): void {
        if (empty($this->appConfig->getDocumentServerUrl())) {
            $this->logger->debug("Settings are empty");
            return;
        }
        if (!$this->appConfig->settingsAreSuccessful()) {
            $this->logger->debug("Settings are not correct");
            return;
        }
        $fileUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".callback.emptyfile");
        if (!$this->appConfig->useDemo() && !empty($this->appConfig->getStorageUrl())) {
            $fileUrl = str_replace($this->urlGenerator->getAbsoluteURL("/"), $this->appConfig->getStorageUrl(), $fileUrl);
        }
        $host = parse_url((string) $fileUrl)["host"];
        if ($host === "localhost" || $host === "127.0.0.1") {
            $this->logger->debug("Localhost is not alowed for cron editors availability check. Please provide server address for internal requests from ONLYOFFICE Docs");
            return;
        }

        $this->logger->debug("ONLYOFFICE check started by cron");

        [$error, $version] = $this->documentService->checkDocServiceUrl();

        if (!empty($error)) {
            $this->logger->info("ONLYOFFICE server is not available");
            $this->appConfig->setSettingsError($error);
            $this->notifyAdmins();
        } else {
            $this->logger->debug("ONLYOFFICE server availability check is finished successfully");
        }
    }

    /**
     * Get the list of users to notify
     *
     * @return string[]
     */
    private function getUsersToNotify(): array {
        $notifyGroups = ["admin"];
        $notifyUsers = [];

        foreach ($notifyGroups as $notifyGroup) {
            $group = $this->groupManager->get($notifyGroup);
            if ($group === null || !($group instanceof IGroup)) {
                continue;
            }
            $users = $group->getUsers();
            foreach ($users as $user) {
                $notifyUsers[] = $user->getUID();
            }
        }
        return $notifyUsers;
    }

    /**
     * Send notification to admins
     */
    private function notifyAdmins(): void {
        $notificationManager = \OCP\Server::get(\OCP\Notification\IManager::class);
        $notification = $notificationManager->createNotification();
        $notification->setApp($this->appName)
            ->setDateTime(new \DateTime())
            ->setObject("editorsCheck", $this->trans->t("ONLYOFFICE server is not available"))
            ->setSubject("editorscheck_info");
        foreach ($this->getUsersToNotify() as $uid) {
            $notification->setUser($uid);
            $notificationManager->notify($notification);
            if ($this->appConfig->getEmailNotifications()) {
                $this->emailManager->notifyEditorsCheckEmail($uid);
            }
        }
    }
}

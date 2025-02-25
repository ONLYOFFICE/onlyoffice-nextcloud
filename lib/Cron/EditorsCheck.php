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

namespace OCA\Onlyoffice\Cron;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DocumentService;
use OCA\Onlyoffice\EmailManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use OCP\Mail\IMailer;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Editors availability check background job
 *
 */
class EditorsCheck extends TimedJob {

    /**
     * Application name
     *
     * @var string
     */
    private $appName;

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
     * Application configuration
     *
     * @var AppConfig
     */
    private $config;

    /**
     * l10n service
     *
     * @var IL10N
     */
    private $trans;

    /**
     * Hash generator
     *
     * @var Crypt
     */
    private $crypt;

    /**
     * Group manager
     *
     * @var IGroupManager
     */
    private $groupManager;

    /**
     * Email manager
     *
     * @var EmailManager
     */
    private $emailManager;

    /**
     * @param string $AppName - application name
     * @param IURLGenerator $urlGenerator - url generator service
     * @param ITimeFactory $time - time
     * @param AppConfig $config - application configuration
     * @param IL10N $trans - l10n service
     * @param Crypt $crypt - crypt service
     */
    public function __construct(
        string $AppName,
        IURLGenerator $urlGenerator,
        ITimeFactory $time,
        AppConfig $config,
        IL10N $trans,
        Crypt $crypt,
        IGroupManager $groupManager
    ) {
        parent::__construct($time);
        $this->appName = $AppName;
        $this->urlGenerator = $urlGenerator;

        $this->logger = \OCP\Server::get(LoggerInterface::class);
        $this->config = $config;
        $this->trans = $trans;
        $this->crypt = $crypt;
        $this->groupManager = $groupManager;
        $this->setInterval($this->config->getEditorsCheckInterval());
        $this->setTimeSensitivity(IJob::TIME_SENSITIVE);
        $mailer = \OCP\Server::get(IMailer::class);
        $userManager = \OCP\Server::get(IUserManager::class);
        $this->emailManager = new EmailManager($AppName, $trans, $this->logger, $mailer, $userManager, $urlGenerator);
    }

    /**
     * Makes the background check
     *
     * @param array $argument unused argument
     */
    protected function run($argument) {
        if (empty($this->config->getDocumentServerUrl())) {
            $this->logger->debug("Settings are empty");
            return;
        }
        if (!$this->config->settingsAreSuccessful()) {
            $this->logger->debug("Settings are not correct");
            return;
        }
        $fileUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".callback.emptyfile");
        if (!$this->config->useDemo() && !empty($this->config->getStorageUrl())) {
            $fileUrl = str_replace($this->urlGenerator->getAbsoluteURL("/"), $this->config->getStorageUrl(), $fileUrl);
        }
        $host = parse_url($fileUrl)["host"];
        if ($host === "localhost" || $host === "127.0.0.1") {
            $this->logger->debug("Localhost is not alowed for cron editors availability check. Please provide server address for internal requests from ONLYOFFICE Docs");
            return;
        }

        $this->logger->debug("ONLYOFFICE check started by cron");

        $documentService = new DocumentService($this->trans, $this->config);
        list($error, $version) = $documentService->checkDocServiceUrl($this->urlGenerator, $this->crypt);

        if (!empty($error)) {
            $this->logger->info("ONLYOFFICE server is not available");
            $this->config->setSettingsError($error);
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
    private function getUsersToNotify() {
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
     * @return void
     */
    private function notifyAdmins() {
        $notificationManager = \OC::$server->getNotificationManager();
        $notification = $notificationManager->createNotification();
        $notification->setApp($this->appName)
            ->setDateTime(new \DateTime())
            ->setObject("editorsCheck", $this->trans->t("ONLYOFFICE server is not available"))
            ->setSubject("editorscheck_info");
        foreach ($this->getUsersToNotify() as $uid) {
            $notification->setUser($uid);
            $notificationManager->notify($notification);
            if ($this->config->getEmailNotifications()) {
                $this->emailManager->notifyEditorsCheckEmail($uid);
            }
        }
    }
}

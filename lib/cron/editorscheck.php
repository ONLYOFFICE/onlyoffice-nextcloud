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

namespace OCA\Onlyoffice\Cron;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DocumentService;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\IGroup;
use OCP\IGroupManager;

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
     * @var OCP\ILogger
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
     * Users group to notify
     *
     * @var array <array-key, bool>
     */
    private $notifyUsers;

    /**
     * @param string $AppName - application name
     * @param IURLGenerator $urlGenerator - url generator service
     * @param ITimeFactory $time - time
     * @param AppConfig $config - application configuration
     * @param IL10N $trans - l10n service
     * @param Crypt $crypt - crypt service
     */
    public function __construct(string $AppName,
                            IURLGenerator $urlGenerator,
                            ITimeFactory $time,
                            AppConfig $config,
                            IL10N $trans,
                            Crypt $crypt,
                            IGroupManager $groupManager) {
        parent::__construct($time);
        $this->appName = $AppName;
        $this->urlGenerator = $urlGenerator;

        $this->logger = \OC::$server->getLogger();
        $this->config = $config;
        $this->trans = $trans;
        $this->notifyUsers = [];
        $this->crypt = $crypt;
        $this->groupManager = $groupManager;
        $this->setInterval($this->config->GetEditorsCheckInterval());
        $this->setTimeSensitivity(IJob::TIME_SENSITIVE);
    }

    protected function run($argument) {
        $documentService = new DocumentService($this->trans, $this->config);
        list ($error, $version) = $documentService->checkDocServiceUrl($this->urlGenerator, $this->crypt);

        if (!empty($error)) {
            $this->logger->info($this->trans->t("OnlyOffice server is not available"), array("app" => $this->appName));
            $this->notifyAdmins($error);
        }
    }

    /**
     * Get the list of users to notify
     * @return string[]
     */
    protected function getUsersToNotify($needleGroup = '["admin"]') {
        if (!empty($this->notifyUsers)) {
            return array_keys($this->notifyUsers);
        }

        $groups = \OC::$server->getConfig()->getAppValue($this->appName, "notification_groups", $needleGroup);
        $groups = json_decode($groups, true);

        if ($groups === null) {
            return [];
        }

        foreach ($groups as $gid) {
            $group = $this->groupManager->get($gid);
            if (!($group instanceof IGroup)) {
                continue;
            }

            $users = $group->getUsers();
            foreach ($users as $user) {
                $uid = $user->getUID();
                if (isset($this->notifyUsers[$uid])) {
                    continue;
                }

                $this->notifyUsers[$uid] = true;
            }
        }

        return array_keys($this->notifyUsers);
    }

    private function notifyAdmins($error) {
        $notificationManager = \OC::$server->getNotificationManager();
        $notification = $notificationManager->createNotification();
        $notification->setApp($this->appName)
            ->setDateTime(new \DateTime())
            ->setObject("editorsCheck", "OnlyOffice server is not available")
            ->setSubject("editorsCheck_info", [
                "error" => $error
            ]);
        foreach ($this->getUsersToNotify() as $uid) {
            $notification->setUser($uid);
            $notificationManager->notify($notification);
        }
    }

}

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

namespace OCA\Onlyoffice\Controller;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Cron\EditorsCheck;
use OCP\AppFramework\Controller;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\IJobList;
use OCP\IRequest;

/**
 * Class JobListController
 *
 * @package OCA\Onlyoffice\Controller
 */
class JobListController extends Controller {

    /**
     * Job list
     *
     * @var IJobList
     */
    private $jobList;

    /**
     * Application configuration
     *
     * @var AppConfig
     */
    private $config;

    /**
     * JobListController constructor.
     *
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param AppConfig $config - application configuration
     * @param IJobList $jobList - job list
     */
    public function __construct($AppName, IRequest $request, AppConfig $config, IJobList $jobList) {
        parent::__construct($AppName, $request);
        $this->config = $config;
        $this->jobList = $jobList;
    }

    /**
     * Add a job to list
     *
     * @param IJob|string $job
     */
    private function addJob($job) {
        if (!$this->jobList->has($job, null)) {
            $this->jobList->add($job);
            \OCP\Log\logger('onlyoffice')->debug("Job '".$job."' added to JobList.", ["app" => $this->appName]);
        }
    }

    /**
     * Remove a job from list
     *
     * @param IJob|string $job
     */
    private function removeJob($job) {
        if ($this->jobList->has($job, null)) {
            $this->jobList->remove($job);
            \OCP\Log\logger('onlyoffice')->debug("Job '".$job."' removed from JobList.", ["app" => $this->appName]);
        }
    }

    /**
     * Add or remove EditorsCheck job depending on the value of _editors_check_interval
     *
     */
    private function checkEditorsCheckJob() {
        if (!$this->config->getCronChecker()) {
            $this->removeJob(EditorsCheck::class);
            return;
        }
        if ($this->config->getEditorsCheckInterval() > 0) {
            $this->addJob(EditorsCheck::class);
        } else {
            $this->removeJob(EditorsCheck::class);
        }
    }

    /**
     * Method for sequentially calling checks of all jobs
     *
     */
    public function checkAllJobs() {
        $this->checkEditorsCheckJob();
    }
}

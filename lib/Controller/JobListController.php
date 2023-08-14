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

namespace OCA\Onlyoffice\Controller;

use OC\AppFramework\Http;
use OC\BackgroundJob\TimedJob;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\IJobList;
use OCP\IRequest;

use OCA\Onlyoffice\Cron\EditorsCheck;
use OCA\Onlyoffice\AppConfig;

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
            \OC::$server->getLogger()->debug("Job '".$job."' added to JobList.", ["app" => $this->appName]);
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
            \OC::$server->getLogger()->debug("Job '".$job."' removed from JobList.", ["app" => $this->appName]);
        }
    }

    /**
     * Add or remove EditorsCheck job depending on the value of _editors_check_interval
     *
     */
    private function checkEditorsCheckJob() {
        if ($this->config->GetEditorsCheckInterval() > 0) {
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

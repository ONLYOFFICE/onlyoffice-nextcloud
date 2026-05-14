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

    public function __construct(
        string $appName,
        IRequest $request,
        private readonly AppConfig $appConfig,
        private readonly IJobList $jobList
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Add a job to list
     *
     * @param IJob|string $job
     */
    private function addJob(IJob|string $job): void {
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
    private function removeJob(IJob|string $job): void {
        if ($this->jobList->has($job, null)) {
            $this->jobList->remove($job);
            \OCP\Log\logger('onlyoffice')->debug("Job '".$job."' removed from JobList.", ["app" => $this->appName]);
        }
    }

    /**
     * Add or remove EditorsCheck job depending on the value of _editors_check_interval
     *
     */
    private function checkEditorsCheckJob(): void {
        if (!$this->appConfig->getCronChecker()) {
            $this->removeJob(EditorsCheck::class);
            return;
        }
        if ($this->appConfig->getEditorsCheckInterval() > 0) {
            $this->addJob(EditorsCheck::class);
        } else {
            $this->removeJob(EditorsCheck::class);
        }
    }

    /**
     * Method for sequentially calling checks of all jobs
     *
     */
    public function checkAllJobs(): void {
        $this->checkEditorsCheckJob();
    }
}

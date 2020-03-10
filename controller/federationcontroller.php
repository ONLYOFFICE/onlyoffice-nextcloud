<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2020
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

use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\Share\IManager;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\FileUtility;

/**
 * OCS handler
 */
class FederationController extends OCSController {

    /**
     * Logger
     *
     * @var ILogger
     */
    private $logger;

    /**
     * Share manager
     *
     * @var IManager
     */
    private $shareManager;

    /**
     * Application configuration
     *
     * @var AppConfig
     */
    public $config;

    /**
     * File utility
     *
     * @var FileUtility
     */
    private $fileUtility;

    /**
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param IL10N $trans - l10n service
     * @param ILogger $logger - logger
     * @param IManager $shareManager - Share manager
     * @param IManager $ISession - Session
     */
    public function __construct($AppName,
                                    IRequest $request,
                                    IL10N $trans,
                                    ILogger $logger,
                                    IManager $shareManager,
                                    ISession $session
                                    ) {
        parent::__construct($AppName, $request);

        $this->logger = $logger;
        $this->shareManager = $shareManager;

        $this->config = new AppConfig($this->appName);
        $this->fileUtility = new FileUtility($AppName, $trans, $logger, $this->config, $shareManager, $session);
    }

    /**
     * Returns the origin document key for editor
     *
     * @param string $shareToken - access token
     * @param string $path - file path
     *
     * @return DataResponse
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     */
    public function key($shareToken, $path) {
        list ($file, $error, $share) = $this->fileUtility->getFileByToken(null, $shareToken, $path);

        if (isset($error)) {
            $this->logger->error("Federated getFileByToken: $error", ["app" => $this->appName]);
            return new DataResponse(["error" => $error]);
        }

        $key = $this->fileUtility->getKey($file, true);

        $this->logger->debug("Federated request get for " . $file->getId() . " key $key", ["app" => $this->appName]);

        return new DataResponse(["key" => $key]);
    }
}

<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2022
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

use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCSController;
use OCP\ILogger;
use OCP\IRequest;
use OCP\Files\IRootFolder;

/**
 * OCS handler
 */
class DesktopApiController extends OCSController {

    /**
     * Root folder
     *
     * @var IRootFolder
     */
    private $root;

    /**
     * Logger
     *
     * @var ILogger
     */
    private $logger;

    /**
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param IRootFolder $root - root folder
     * @param ILogger $logger - logger
     */
    public function __construct($AppName,
                                    IRequest $request,
                                    IRootFolder $root,
                                    ILogger $logger
                                    ) {
        parent::__construct($AppName, $request);

        $this->root = $root;
        $this->logger = $logger;
    }

    /**
     * Get shares for file
     *
     * @return JSONResponse
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function upload() {
        $this->logger->debug("desktopapi request", ["app" => $this->appName]);

        $contentDisposition = $this->request->getHeader("Content-Disposition");
        $fileName = $this->getFileNameFromContentDisposition($contentDisposition);

        // save to user folder

        $response = [
            "id" => "",
            "url" => ""
        ]
        return new JSONResponse($response);
    }

    /**
     * Get file name from Content-Disposition header value
     *
     * @param string $contentDisposition - Content-Disposition header
     *
     * @return string
     */
    private function getFileNameFromContentDisposition($contentDisposition) {
        if (empty($contentDisposition)) {
            return "";
        }

        $splitItems = explode(";", $contentDisposition);

        foreach($splitItems as $item) {
            $keyValue = explode("=", $item);
            if (count($keyValue) === 2) {
                $key = trim($keyValue[0]);
                if (strtolower($key) === "filename") {
                    $value = trim($keyValue[1]);
                    $value = trim($value, "'\"");
                    return $value;
                }
            }
        }

        return "";
    }
}

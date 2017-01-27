<?php
/**
 *
 * (c) Copyright Ascensio System Limited 2010-2017
 *
 * This program is freeware. You can redistribute it and/or modify it under the terms of the GNU 
 * General Public License (GPL) version 3 as published by the Free Software Foundation (https://www.gnu.org/copyleft/gpl.html). 
 * In accordance with Section 7(a) of the GNU GPL its Section 15 shall be amended to the effect that 
 * Ascensio System SIA expressly excludes the warranty of non-infringement of any third-party rights.
 *
 * THIS PROGRAM IS DISTRIBUTED WITHOUT ANY WARRANTY; WITHOUT EVEN THE IMPLIED WARRANTY OF MERCHANTABILITY OR
 * FITNESS FOR A PARTICULAR PURPOSE. For more details, see GNU GPL at https://www.gnu.org/copyleft/gpl.html
 *
 * You can contact Ascensio System SIA by email at sales@onlyoffice.com
 *
 * The interactive user interfaces in modified source and object code versions of ONLYOFFICE must display 
 * Appropriate Legal Notices, as required under Section 5 of the GNU GPL version 3.
 *
 * Pursuant to Section 7 § 3(b) of the GNU GPL you must retain the original ONLYOFFICE logo which contains 
 * relevant author attributions when distributing the software. If the display of the logo in its graphic 
 * form is not reasonably feasible for technical reasons, you must include the words "Powered by ONLYOFFICE" 
 * in every copy of the program you distribute. 
 * Pursuant to Section 7 § 3(e) we decline to grant you any rights under trademark law for use of our trademarks.
 *
*/

namespace OCA\Onlyoffice\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DocumentService;
use OCA\Onlyoffice\DownloadResponse;
use OCA\Onlyoffice\ErrorResponse;

/**
 * Callback handler for the document server.
 * Download the file without authentication.
 * Save the file without authentication.
 */
class CallbackController extends Controller {

    /**
     * Root folder
     *
     * @var IRootFolder
     */
    private $root;

    /**
     * User session
     *
     * @var IUserSession
     */
    private $userSession;

    /**
     * User manager
     *
     * @var IUserManager
     */
    private $userManager;

    /**
     * l10n service
     *
     * @var IL10N
     */
    private $trans;

    /**
     * Application configuration
     *
     * @var OCA\Onlyoffice\AppConfig
     */
    private $config;

    /**
     * Hash generator
     *
     * @var OCA\Onlyoffice\Crypt
     */
    private $crypt;

    /**
     * Status of the document
     *
     * @var Array
     */
    private $_trackerStatus = array(
        0 => "NotFound",
        1 => "Editing",
        2 => "MustSave",
        3 => "Corrupted",
        4 => "Closed"
    );

    /**
     * @param string $AppName application name
     * @param IRequest $request request object
     * @param IRootFolder $root root folder
     * @param IUserSession $userSession user session
     * @param IUserManager $userManager user manager
     * @param IL10N $l10n l10n service
     * @param OCA\Onlyoffice\Crypt $crypt hash generator
     */
    public function __construct($AppName, 
                                    IRequest $request,
                                    IRootFolder $root,
                                    IUserSession $userSession,
                                    IUserManager $userManager,
                                    IL10N $trans,
                                    AppConfig $config,
                                    Crypt $crypt
                                    ) {
        parent::__construct($AppName, $request);

        $this->root = $root;
        $this->userSession = $userSession;
        $this->userManager = $userManager;
        $this->trans = $trans;
        $this->config = $config;
        $this->crypt = $crypt;
    }


    /**
     * Downloading file by the document service
     *
     * @param string $doc - verification token with the file identifier
     *
     * @return OCA\Onlyoffice\DownloadResponse
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function download($doc) {

        $hashData = $this->crypt->ReadHash($doc);
        if ($hashData === NULL) {
            return new ErrorResponse(Http::STATUS_FORBIDDEN, $this->trans->t("Access deny"));
        }
        if ($hashData->action !== "download") {
            return new ErrorResponse(Http::STATUS_BAD_REQUEST, $this->trans->t("Invalid request"));
        }

        $fileId = $hashData->fileId;
        $ownerId = $hashData->ownerId;

        $files = $this->root->getUserFolder($ownerId)->getById($fileId);
        if(empty($files)) {
            return new ErrorResponse(Http::STATUS_NOT_FOUND, $this->trans->t("Files not found"));
        }
        $file = $files[0];

        if (! $file instanceof File) {
            return new ErrorResponse(Http::STATUS_NOT_FOUND, $this->trans->t("File not found"));
        }

        try {
            return new DownloadResponse($file);
        } catch(\OCP\Files\NotPermittedException  $e) {
            return new ErrorResponse(Http::STATUS_FORBIDDEN, $this->trans->t("Not permitted"));
        }
        return new ErrorResponse(Http::STATUS_INTERNAL_SERVER_ERROR, $this->trans->t("Download failed"));
    }

    /**
     * Handle request from the document server with the document status information
     *
     * @param string $doc - verification token with the file identifier
     * @param array $users - the list of the identifiers of the users
     * @param string $key - the edited document identifier
     * @param string $url - the link to the edited document to be saved
     *
     * @return array
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     * @CORS
     */
    public function track($doc, $users, $key, $status, $url) {

        $hashData = $this->crypt->ReadHash($doc);
        if ($hashData === NULL) {
            return ["message" => $this->trans->t("Access deny")];
        }
        if ($hashData->action !== "track") {
            return ["message" => $this->trans->t("Invalid request")];
        }

        $trackerStatus = $this->_trackerStatus[$status];

        $error = 1;
        switch ($trackerStatus) {
            case "MustSave":
            case "Corrupted":

                $fileId = $hashData->fileId;
                $ownerId = $hashData->ownerId;

                $files = $this->root->getUserFolder($ownerId)->getById($fileId);
                if(empty($files)) {
                    return ["message" => $this->trans->t("Files not found")];
                }
                $file = $files[0];

                if (! $file instanceof File) {
                    return ["message" => $this->trans->t("File not found")];
                }

                $fileName = $file->getName();
                $curExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $downloadExt = strtolower(pathinfo($url, PATHINFO_EXTENSION));

                if ($downloadExt !== $curExt) {
                    $documentService = new DocumentService($this->trans, $this->config);
                    $key =  DocumentService::GenerateRevisionId($fileId . $url);

                    try {
                        $newFileUri;
                        $documentService->GetConvertedUri($url, $downloadExt, $curExt, $key, FALSE, $newFileUri);
                        $url = $newFileUri;
                    } catch (\Exception $e) {
                        return ["message" => $e->getMessage()];
                    }
                }

                if (($newData = file_get_contents($url))) {

                    $this->userSession->setUser($this->userManager->get($users[0]));

                    $file->putContent($newData);
                    $error = 0;
                }
                break;

            case "Editing":
                $error = 0;
                break;
        }

        return ["error" => $error];
    }
}
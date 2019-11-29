<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2019
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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AutoloadNotAllowedException;
use OCP\Constants;
use OCP\Files\FileInfo;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;

use OC\Files\Filesystem;

use OCA\Files\Helper;

use OCA\Onlyoffice\AppConfig;
use OCA\Onlyoffice\Crypt;
use OCA\Onlyoffice\DocumentService;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Controller with the main functions
 */
class EditorController extends Controller {

    /**
     * Current user session
     *
     * @var IUserSession
     */
    private $userSession;

    /**
     * Root folder
     *
     * @var IRootFolder
     */
    private $root;

    /**
     * Url generator service
     *
     * @var IURLGenerator
     */
    private $urlGenerator;

    /**
     * l10n service
     *
     * @var IL10N
     */
    private $trans;

    /**
     * Logger
     *
     * @var ILogger
     */
    private $logger;

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
     * Share manager
     *
     * @var IManager
     */
    private $shareManager;

    /**
     * Session
     *
     * @var ISession
     */
    private $session;

	/**
	 * EventDispatcherInterface
	 *
	 *@var EventDispatcherInterface
	 */
	private $dispatcher;

    /**
     * Mobile regex from https://github.com/ONLYOFFICE/CommunityServer/blob/v9.1.1/web/studio/ASC.Web.Studio/web.appsettings.config#L35
     */
    const USER_AGENT_MOBILE = "/android|avantgo|playbook|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od|ad)|iris|kindle|lge |maemo|midp|mmp|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\\/|plucker|pocket|psp|symbian|treo|up\\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i";

    /**
     * @param string $AppName - application name
     * @param IRequest $request - request object
     * @param IRootFolder $root - root folder
     * @param IUserSession $userSession - current user session
     * @param IURLGenerator $urlGenerator - url generator service
     * @param IL10N $trans - l10n service
     * @param ILogger $logger - logger
     * @param OCA\Onlyoffice\AppConfig $config - application configuration
     * @param OCA\Onlyoffice\Crypt $crypt - hash generator
     * @param IManager $shareManager - Share manager
     * @param IManager $ISession - Session
     */
    public function __construct($AppName,
                                    IRequest $request,
                                    IRootFolder $root,
                                    IUserSession $userSession,
                                    IURLGenerator $urlGenerator,
                                    IL10N $trans,
                                    ILogger $logger,
                                    AppConfig $config,
                                    Crypt $crypt,
                                    IManager $shareManager,
                                    ISession $session,
									EventDispatcherInterface $dispatcher
                                    ) {
        parent::__construct($AppName, $request);

        $this->userSession = $userSession;
        $this->root = $root;
        $this->urlGenerator = $urlGenerator;
        $this->trans = $trans;
        $this->logger = $logger;
        $this->config = $config;
        $this->crypt = $crypt;
        $this->shareManager = $shareManager;
        $this->session = $session;
		$this->dispatcher = $dispatcher;
    }

    /**
     * Create new file in folder
     *
     * @param string $name - file name
     * @param string $dir - folder path
     * @param string $shareToken - access token
     *
     * @return array
     *
     * @NoAdminRequired
     * @PublicPage
     */
    public function create($name, $dir, $shareToken = NULL) {
        $this->logger->debug("Create: $name", array("app" => $this->appName));

        if (empty($shareToken) && !$this->config->isUserAllowedToUse()) {
            return ["error" => $this->trans->t("Not permitted")];
        }

        if (empty($shareToken)) {
            $userId = $this->userSession->getUser()->getUID();
            $userFolder = $this->root->getUserFolder($userId);
        } else {
            list ($userFolder, $error, $share) = $this->getNodeByToken($shareToken);

            if (isset($error)) {
                $this->logger->error("Create: $error", array("app" => $this->appName));
                return ["error" => $error];
            }

            if ($userFolder instanceof File) {
                return ["error" => $this->trans->t("You don't have enough permission to create")];
            }

            if (!empty($shareToken) && ($share->getPermissions() & Constants::PERMISSION_CREATE) === 0) {
                $this->logger->error("Create in public folder without access: $fileId", array("app" => $this->appName));
                return ["error" => $this->trans->t("You do not have enough permissions to view the file")];
            }
        }

        $folder = $userFolder->get($dir);

        if ($folder === NULL) {
            $this->logger->error("Folder for file creation was not found: $dir", array("app" => $this->appName));
            return ["error" => $this->trans->t("The required folder was not found")];
        }
        if (!$folder->isCreatable()) {
            $this->logger->error("Folder for file creation without permission: $dir", array("app" => $this->appName));
            return ["error" => $this->trans->t("You don't have enough permission to create")];
        }

        $ext = strtolower("." . pathinfo($name, PATHINFO_EXTENSION));

        $lang = \OC::$server->getL10NFactory("")->get("")->getLanguageCode();

        $templatePath = $this->getTemplatePath($lang, $ext);
        if (!file_exists($templatePath)) {
            $lang = "en";
            $templatePath = $this->getTemplatePath($lang, $ext);
        }

        $template = file_get_contents($templatePath);
        if (!$template) {
            $this->logger->error("Template for file creation not found: $templatePath", array("app" => $this->appName));
            return ["error" => $this->trans->t("Template not found")];
        }

        $name = $folder->getNonExistingName($name);

        try {
            $file = $folder->newFile($name);

            $file->putContent($template);
        } catch (NotPermittedException $e) {
            $this->logger->error("Can't create file: $name", array("app" => $this->appName));
            return ["error" => $this->trans->t("Can't create file")];
        }

        $fileInfo = $file->getFileInfo();

        $result = Helper::formatFileInfo($fileInfo);
        return $result;
    }

    /**
     * Get template path
     *
     * @param string $lang - language
     * @param string $ext - file extension
     *
     * @return string
     */
    private function getTemplatePath($lang, $ext) {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . "assets" . DIRECTORY_SEPARATOR . $lang . DIRECTORY_SEPARATOR . "new" . $ext;
    }

    /**
     * Conversion file to Office Open XML format
     *
     * @param integer $fileId - file identifier
     * @param string $shareToken - access token
     *
     * @return array
     *
     * @NoAdminRequired
     * @PublicPage
     */
    public function convert($fileId, $shareToken = NULL) {
        $this->logger->debug("Convert: $fileId", array("app" => $this->appName));

        if (empty($shareToken) && !$this->config->isUserAllowedToUse()) {
            return ["error" => $this->trans->t("Not permitted")];
        }

        $user = $this->userSession->getUser();
        $userId = NULL;
        if (!empty($user)) {
            $userId = $user->getUID();
        }

        list ($file, $error, $share) = empty($shareToken) ? $this->getFile($userId, $fileId) : $this->getFileByToken($fileId, $shareToken);

        if (isset($error)) {
            $this->logger->error("Convertion: $fileId $error", array("app" => $this->appName));
            return ["error" => $error];
        }

        if (!empty($shareToken) && ($share->getPermissions() & Constants::PERMISSION_CREATE) === 0) {
            $this->logger->error("Convertion in public folder without access: $fileId", array("app" => $this->appName));
            return ["error" => $this->trans->t("You do not have enough permissions to view the file")];
        }

        $fileName = $file->getName();
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $format = $this->config->FormatsSetting()[$ext];
        if (!isset($format)) {
            $this->logger->info("Format for convertion not supported: $fileName", array("app" => $this->appName));
            return ["error" => $this->trans->t("Format is not supported")];
        }

        if (!isset($format["conv"]) || $format["conv"] !== true) {
            $this->logger->info("Conversion is not required: $fileName", array("app" => $this->appName));
            return ["error" => $this->trans->t("Conversion is not required")];
        }

        $internalExtension = "docx";
        switch ($format["type"]) {
            case "spreadsheet":
                $internalExtension = "xlsx";
                break;
            case "presentation":
                $internalExtension = "pptx";
                break;
        }

        $newFileUri;
        $documentService = new DocumentService($this->trans, $this->config);
        $key = $this->getKey($file);
        $fileUrl = $this->getUrl($file, $shareToken);
        try {
            $newFileUri = $documentService->GetConvertedUri($fileUrl, $ext, $internalExtension, $key);
        } catch (\Exception $e) {
            $this->logger->error("GetConvertedUri: " . $file->getId() . " " . $e->getMessage(), array("app" => $this->appName));
            return ["error" => $e->getMessage()];
        }

        $folder = $file->getParent();
        if (!$folder->isCreatable()) {
            $folder = $this->root->getUserFolder($userId);
        }

        try {
            $newData = $documentService->Request($newFileUri);
        } catch (\Exception $e) {
            $this->logger->error("Failed to download converted file: $newFileUri" . " " . $e->getMessage(), array("app" => $this->appName));
            return ["error" => $this->trans->t("Failed to download converted file")];
        }

        $fileNameWithoutExt = substr($fileName, 0, strlen($fileName) - strlen($ext) - 1);
        $newFileName = $folder->getNonExistingName($fileNameWithoutExt . "." . $internalExtension);

        try {
            $file = $folder->newFile($newFileName);

            $file->putContent($newData);
        } catch (NotPermittedException $e) {
            $this->logger->error("Can't create file: $newFileName", array("app" => $this->appName));
            return ["error" => $this->trans->t("Can't create file")];
        }

        $fileInfo = $file->getFileInfo();

        $result = Helper::formatFileInfo($fileInfo);
        return $result;
    }

    /**
     * Save file to folder
     *
     * @param string $name - file name
     * @param string $dir - folder path
     * @param string $url - file url
     *
     * @return array
     *
     * @NoAdminRequired
     */
    public function save($name, $dir, $url) {
        $this->logger->debug("Save: $name", array("app" => $this->appName));

        if (!$this->config->isUserAllowedToUse()) {
            return ["error" => $this->trans->t("Not permitted")];
        }

        $userId = $this->userSession->getUser()->getUID();
        $userFolder = $this->root->getUserFolder($userId);

        $folder = $userFolder->get($dir);

        if ($folder === NULL) {
            $this->logger->error("Folder for saving file was not found: $dir", array("app" => $this->appName));
            return ["error" => $this->trans->t("The required folder was not found")];
        }
        if (!$folder->isCreatable()) {
            $this->logger->error("Folder for saving file without permission: $dir", array("app" => $this->appName));
            return ["error" => $this->trans->t("You don't have enough permission to create")];
        }

        $url = $this->config->ReplaceDocumentServerUrlToInternal($url);

        try {
            $documentService = new DocumentService($this->trans, $this->config);
            $newData = $documentService->Request($url);
        } catch (\Exception $e) {
            $this->logger->error("Failed to download file for saving: $url " . $e->getMessage(), array("app" => $this->appName));
            return ["error" => $this->trans->t("Download failed")];
        }

        $name = $folder->getNonExistingName($name);

        try {
            $file = $folder->newFile($name);

            $file->putContent($newData);
        } catch (NotPermittedException $e) {
            $this->logger->error("Can't save file: $name", array("app" => $this->appName));
            return ["error" => $this->trans->t("Can't create file")];
        }

        $fileInfo = $file->getFileInfo();

        $result = Helper::formatFileInfo($fileInfo);
        return $result;
    }

    /**
     * Get presigned url to file
     *
     * @param string $filePath - file path
     *
     * @return array
     *
     * @NoAdminRequired
     */
    public function url($filePath) {
        $this->logger->debug("Save: $name", array("app" => $this->appName));

        if (!$this->config->isUserAllowedToUse()) {
            return ["error" => $this->trans->t("Not permitted")];
        }

        $userId = $this->userSession->getUser()->getUID();
        $userFolder = $this->root->getUserFolder($userId);

        $file = $userFolder->get($filePath);

        if ($file === NULL) {
            $this->logger->error("File for generate presigned url was not found: $dir", array("app" => $this->appName));
            return ["error" => $this->trans->t("File not found")];
        }
        if (!$file->isReadable()) {
            $this->logger->error("Folder for saving file without permission: $dir", array("app" => $this->appName));
            return ["error" => $this->trans->t("You do not have enough permissions to view the file")];
        }

        $fileName = $file->getName();
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileUrl = $this->getUrl($file);

        $result = [
            "fileType" => $ext,
            "url" => $fileUrl
        ];

        if (!empty($this->config->GetDocumentServerSecret())) {
            $token = \Firebase\JWT\JWT::encode($result, $this->config->GetDocumentServerSecret());
            $result["token"] = $token;
        }

        return $result;
    }

    /**
     * Print editor section
     *
     * @param integer $fileId - file identifier
     * @param string $shareToken - access token
     * @param string $filePath - file path
     *
     * @return TemplateResponse|RedirectResponse
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index($fileId, $shareToken = NULL, $filePath = NULL) {
        $this->logger->debug("Open: $fileId $filePath", array("app" => $this->appName));

        if (empty($shareToken) && !$this->userSession->isLoggedIn()) {
            $redirectUrl = $this->urlGenerator->linkToRoute("core.login.showLoginForm", [
                "redirect_url" => $this->request->getRequestUri()
            ]);
            return new RedirectResponse($redirectUrl);
        }

        if (empty($shareToken) && !$this->config->isUserAllowedToUse()) {
            return $this->renderError($this->trans->t("Not permitted"));
        }

        $documentServerUrl = $this->config->GetDocumentServerUrl();

        if (empty($documentServerUrl)) {
            $this->logger->error("documentServerUrl is empty", array("app" => $this->appName));
            return $this->renderError($this->trans->t("ONLYOFFICE app is not configured. Please contact admin"));
        }

        $params = [
            "documentServerUrl" => $documentServerUrl,
            "fileId" => $fileId,
            "filePath" => $filePath,
            "shareToken" => $shareToken
        ];

        $response = new TemplateResponse($this->appName, "editor", $params);

        $csp = new ContentSecurityPolicy();
        $csp->allowInlineScript(true);

        if (preg_match("/^https?:\/\//i", $documentServerUrl)) {
            $csp->addAllowedScriptDomain($documentServerUrl);
            $csp->addAllowedFrameDomain($documentServerUrl);
        } else {
            $csp->addAllowedFrameDomain($this->urlGenerator->getAbsoluteURL("/"));
        }
        $response->setContentSecurityPolicy($csp);

        return $response;
    }

    /**
     * Print public editor section
     *
     * @param integer $fileId - file identifier
     * @param string $shareToken - access token
     *
     * @return TemplateResponse
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     */
    public function PublicPage($fileId, $shareToken) {
        return $this->index($fileId, $shareToken);
    }

    /**
     * Collecting the file parameters for the document service
     *
     * @param integer $fileId - file identifier
     * @param string $filePath - file path
     * @param string $shareToken - access token
     * @param bool $desktop - desktop label
     *
     * @return array
     *
     * @NoAdminRequired
     * @PublicPage
     */
    public function config($fileId, $filePath = NULL, $shareToken = NULL, $desktop = false) {

        if (empty($shareToken) && !$this->config->isUserAllowedToUse()) {
            return ["error" => $this->trans->t("Not permitted")];
        }

        $user = $this->userSession->getUser();
        $userId = NULL;
        if (!empty($user)) {
            $userId = $user->getUID();
        }

        list ($file, $error, $share) = empty($shareToken) ? $this->getFile($userId, $fileId, $filePath) : $this->getFileByToken($fileId, $shareToken);

        if (isset($error)) {
            $this->logger->error("Config: $fileId $error", array("app" => $this->appName));
            return ["error" => $error];
        }

        $fileName = $file->getName();
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $format = $this->config->FormatsSetting()[$ext];
        if (!isset($format)) {
            $this->logger->info("Format is not supported for editing: $fileName", array("app" => $this->appName));
            return ["error" => $this->trans->t("Format is not supported")];
        }

        $fileUrl = $this->getUrl($file, $shareToken);
        $key = $this->getKey($file);

        $params = [
            "document" => [
                "fileType" => $ext,
                "key" => DocumentService::GenerateRevisionId($key),
                "permissions" => [],
                "title" => $fileName,
                "url" => $fileUrl,
            ],
            "documentType" => $format["type"],
            "editorConfig" => [
                "lang" => str_replace("_", "-", \OC::$server->getL10NFactory("")->get("")->getLanguageCode()),
                //"region" => str_replace("_", "-", \OC::$server->getL10NFactory("")->findLocale())
            ]
        ];

        $permissions_modifyFilter = $this->config->GetSystemValue($this->config->_permissions_modifyFilter);
        if (isset($permissions_modifyFilter)) {
            $params["document"]["permissions"]["modifyFilter"] = $permissions_modifyFilter;
        }

        $canEdit = isset($format["edit"]) && $format["edit"];
        $editable = $file->isUpdateable()
                    && (empty($token) || ($share->getPermissions() & Constants::PERMISSION_UPDATE) === Constants::PERMISSION_UPDATE);

		$event = new GenericEvent($fileId, ['token' => $token]);
		$this->dispatcher->dispatch(self::class . '::getEditable', $event);
		if ($event->hasArgument('editable')) {
			$editable = $event->getArgument('editable');
		}

        $params["document"]["permissions"]["edit"] = $editable;


        if ($editable && $canEdit) {
            $ownerId = NULL;
            $owner = $file->getOwner();
            if (!empty($owner)) {
                $ownerId = $owner->getUID();
            }

            $hashCallback = $this->crypt->GetHash(["fileId" => $file->getId(), "ownerId" => $ownerId, "shareToken" => $shareToken, "action" => "track"]);
            $callback = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".callback.track", ["doc" => $hashCallback]);

            if (!empty($this->config->GetStorageUrl())) {
                $callback = str_replace($this->urlGenerator->getAbsoluteURL("/"), $this->config->GetStorageUrl(), $callback);
            }

            $params["editorConfig"]["callbackUrl"] = $callback;
        } else {
            $params["editorConfig"]["mode"] = "view";
        }

        if (\OC::$server->getRequest()->isUserAgent([$this::USER_AGENT_MOBILE])) {
            $params["type"] = "mobile";
        }

        if (!empty($userId)) {
            $params["editorConfig"]["user"] = [
                "id" => $userId,
                "name" => $user->getDisplayName()
            ];
        }
        else {
			$event = new GenericEvent($fileId, ['token' => $token]);
			$this->dispatcher->dispatch(self::class . '::getName', $event);
			if ($event->hasArgument('name')) {
				$name = $event->getArgument('name');
            	$params["editorConfig"]["user"] = [
		            "name" => $name
		        ];
			}
        }

        $folderLink = NULL;

        if (!empty($shareToken)) {
            if (method_exists($share, "getHideDownload") && $share->getHideDownload()) {
                $params["document"]["permissions"]["download"] = false;
                $params["document"]["permissions"]["print"] = false;
            }

            $node = $share->getNode();
            if ($node instanceof Folder) {
                $sharedFolder = $node;
                $folderPath = $sharedFolder->getRelativePath($file->getParent()->getPath());
                if (!empty($folderPath)) {
                    $linkAttr = [
                        "path" => $folderPath,
                        "scrollto" => $file->getName(),
                        "token" => $shareToken
                    ];
                    $folderLink = $this->urlGenerator->linkToRouteAbsolute("files_sharing.sharecontroller.showShare", $linkAttr);
                }
            }
        } else if (!empty($userId)) {
            $userFolder = $this->root->getUserFolder($userId);
            $folderPath = $userFolder->getRelativePath($file->getParent()->getPath());
            if (!empty($folderPath)) {
                $linkAttr = [
                    "dir" => $folderPath,
                    "scrollto" => $file->getName()
                ];
                $folderLink = $this->urlGenerator->linkToRouteAbsolute("files.view.index", $linkAttr);
            }
        }

        if ($folderLink !== NULL) {
            $params["editorConfig"]["customization"]["goback"] = [
                "url"  => $folderLink
            ];

            if (!$desktop) {
                if ($this->config->GetSameTab()) {
                    $params["editorConfig"]["customization"]["goback"]["blank"] = false;
                }
            }
        }

        $params = $this->setCustomization($params);

        $params = $this->setWatermark($params, !empty($shareToken), $userId, $file);

        if ($this->config->UseDemo()) {
            $params["editorConfig"]["tenant"] = $this->config->GetSystemValue("instanceid", true);
        }

        if (!empty($this->config->GetDocumentServerSecret())) {
            $token = \Firebase\JWT\JWT::encode($params, $this->config->GetDocumentServerSecret());
            $params["token"] = $token;
        }

        $this->logger->debug("Config is generated for: $fileId with key $key", array("app" => $this->appName));

        return $params;
    }

    /**
     * Getting file by identifier
     *
     * @param string $userId - user identifier
     * @param integer $fileId - file identifier
     * @param string $filePath - file path
     *
     * @return array
     */
    private function getFile($userId, $fileId, $filePath = NULL) {
        if (empty($fileId)) {
            return [NULL, $this->trans->t("FileId is empty"), NULL];
        }

        try {
            $files = $this->root->getUserFolder($userId)->getById($fileId);
        } catch (\Exception $e) {
            $this->logger->error("getFile: $fileId " . $e->getMessage(), array("app" => $this->appName));
            return [NULL, $this->trans->t("Invalid request"), NULL];
        }

        if (empty($files)) {
            $this->logger->info("Files not found: $fileId", array("app" => $this->appName));
            return [NULL, $this->trans->t("File not found"), NULL];
        }

        $file = $files[0];

        if (count($files) > 1 && !empty($filePath)) {
            $filePath = "/" . $userId . "/files" . $filePath;
            foreach ($files as $curFile) {
                if ($curFile->getPath() === $filePath) {
                    $file = $curFile;
                }
            }
        }

        if (!$file->isReadable()) {
            return [NULL, $this->trans->t("You do not have enough permissions to view the file"), NULL];
        }

        return [$file, NULL, NULL];
    }

    /**
     * Getting file by token
     *
     * @param integer $fileId - file identifier
     * @param string $shareToken - access token
     *
     * @return array
     */
    private function getFileByToken($fileId, $shareToken) {
        list ($node, $error, $share) = $this->getNodeByToken($shareToken);

        if (isset($error)) {
            return [NULL, $error, NULL];
        }

        if ($node instanceof Folder) {
            try {
                $files = $node->getById($fileId);
            } catch (\Exception $e) {
                $this->logger->error("getFileByToken: $fileId " . $e->getMessage(), array("app" => $this->appName));
                return [NULL, $this->trans->t("Invalid request"), NULL];
            }

            if (empty($files)) {
                $this->logger->info("Files not found: $fileId", array("app" => $this->appName));
                return [NULL, $this->trans->t("File not found"), NULL];
            }
            $file = $files[0];
        } else {
            $file = $node;
        }

        return [$file, NULL, $share];
    }

    /**
     * Getting file by token
     *
     * @param string $shareToken - access token
     *
     * @return array
     */
    private function getNodeByToken($shareToken) {
        list ($share, $error) = $this->getShare($shareToken);

        if (isset($error)) {
            return [NULL, $error, NULL];
        }

        if (($share->getPermissions() & Constants::PERMISSION_READ) === 0) {
            return [NULL, $this->trans->t("You do not have enough permissions to view the file"), NULL];
        }

        try {
            $node = $share->getNode();
        } catch (NotFoundException $e) {
            $this->logger->error("getFileByToken error: " . $e->getMessage(), array("app" => $this->appName));
            return [NULL, $this->trans->t("File not found"), NULL];
        }

        return [$node, NULL, $share];
    }

    /**
     * Getting share by token
     *
     * @param string $shareToken - access token
     *
     * @return array
     */
    private function getShare($shareToken) {
        if (empty($shareToken)) {
            return [NULL, $this->trans->t("FileId is empty")];
        }

        $share;
        try {
            $share = $this->shareManager->getShareByToken($shareToken);
        } catch (ShareNotFound $e) {
            $this->logger->error("getShare error: " . $e->getMessage(), array("app" => $this->appName));
            $share = NULL;
        }

        if ($share === NULL || $share === false) {
            return [NULL, $this->trans->t("You do not have enough permissions to view the file")];
        }

        if ($share->getPassword()
            && (!$this->session->exists("public_link_authenticated")
                || $this->session->get("public_link_authenticated") !== (string) $share->getId())) {
            return [NULL, $this->trans->t("You do not have enough permissions to view the file")];
        }

        return [$share, NULL];
    }

    /**
     * Generate unique document identifier
     *
     * @param File $file - file
     *
     * @return string
     */
    private function getKey($file) {
        $instanceId = $this->config->GetSystemValue("instanceid", true);

        $fileId = $file->getId();

        $key = $instanceId . "_" . $fileId . "_" . $file->getMtime();

        return $key;
    }

    /**
     * Generate secure link to download document
     *
     * @param integer $file - file
     * @param string $shareToken - access token
     *
     * @return string
     */
    private function getUrl($file, $shareToken = NULL) {

        $user = $this->userSession->getUser();
        $userId = NULL;
        if (!empty($user)) {
            $userId = $user->getUID();
        }

        $ownerId = NULL;
        $owner = $file->getOwner();
        if (!empty($owner)) {
            $ownerId = $owner->getUID();
        }

        $hashUrl = $this->crypt->GetHash(["fileId" => $file->getId(), "userId" => $userId, "ownerId" => $ownerId, "shareToken" => $shareToken, "action" => "download"]);

        $fileUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".callback.download", ["doc" => $hashUrl]);

        if (!empty($this->config->GetStorageUrl())) {
            $fileUrl = str_replace($this->urlGenerator->getAbsoluteURL("/"), $this->config->GetStorageUrl(), $fileUrl);
        }

        return $fileUrl;
    }

    /**
     * Set customization parameters
     *
     * @param array params - file parameters
     *
     * @return array
     */
    private function setCustomization($params) {
        //default is true
        if ($this->config->GetCustomizationChat() === false) {
            $params["editorConfig"]["customization"]["chat"] = false;
        }

        //default is false
        if ($this->config->GetCustomizationCompactHeader() === true) {
            $params["editorConfig"]["customization"]["compactHeader"] = true;
        }

        //default is false
        if ($this->config->GetCustomizationFeedback() === true) {
            $params["editorConfig"]["customization"]["feedback"] = true;
        }

        //default is true
        if ($this->config->GetCustomizationHelp() === false) {
            $params["editorConfig"]["customization"]["help"] = false;
        }

        //default is false
        if ($this->config->GetCustomizationToolbarNoTabs() === true) {
            $params["editorConfig"]["customization"]["toolbarNoTabs"] = true;
        }


        /* from system config */

        $customer = $this->config->GetSystemValue($this->config->_customization_customer);
        if (isset($customer)) {
            $params["editorConfig"]["customization"]["customer"] = $customer;
        }

        $feedback = $this->config->GetSystemValue($this->config->_customization_feedback);
        if (isset($feedback)) {
            $params["editorConfig"]["customization"]["feedback"] = $feedback;
        }

        $loaderLogo = $this->config->GetSystemValue($this->config->_customization_loaderLogo);
        if (isset($loaderLogo)) {
            $params["editorConfig"]["customization"]["loaderLogo"] = $loaderLogo;
        }

        $loaderName = $this->config->GetSystemValue($this->config->_customization_loaderName);
        if (isset($loaderName)) {
            $params["editorConfig"]["customization"]["loaderName"] = $loaderName;
        }

        $logo = $this->config->GetSystemValue($this->config->_customization_logo);
        if (isset($logo)) {
            $params["editorConfig"]["customization"]["logo"] = $logo;
        }

        return $params;
    }

    /**
     * Set watermark parameters
     *
     * @param array params - file parameters
     * @param bool isPublic - with access token
     * @param string userId - user identifier
     * @param string file - file
     *
     * @return array
     */
    private function setWatermark($params, $isPublic, $userId, $file) {
        $watermarkTemplate = $this->getWatermarkText($isPublic, $userId, $file,
            $params["document"]["permissions"]["edit"] !== false,
            !array_key_exists("download", $params["document"]["permissions"]) || $params["document"]["permissions"]["download"] !== false);

        if ($watermarkTemplate !== false) {
            $replacements = [
                "userId" => $userId,
                "date" => (new \DateTime())->format("Y-m-d H:i:s"),
                "themingName" => \OC::$server->getThemingDefaults()->getName()
            ];
            $watermarkTemplate = preg_replace_callback("/{(.+?)}/", function($matches) use ($replacements)
                {
                    return $replacements[$matches[1]];
                }, $watermarkTemplate);

            $params["document"]["options"] = [
                "watermark_on_draw" => [
                    "align" => 1,
                    "height" => 100,
                    "paragraphs" => array([
                        "align" => 2,
                        "runs" => array([
                            "fill" => [182, 182, 182],
                            "font-size" => 70,
                            "text" => $watermarkTemplate,
                        ])
                    ]),
                    "rotate" => -45,
                    "width" => 250,
                ]
            ];
        }

        return $params;
    }

    /**
     * Should watermark
     *
     * @param bool isPublic - with access token
     * @param string userId - user identifier
     * @param string file - file
     * @param bool canEdit - edit permission
     * @param bool canDownload - download permission
     *
     * @return bool|string
     */
    private function getWatermarkText($isPublic, $userId, $file, $canEdit, $canDownload) {
        $watermarkSettings = $this->config->GetWatermarkSettings();
        if (!$watermarkSettings["enabled"]) {
            return false;
        }

        $watermarkText = $watermarkSettings["text"];
        $fileId = $file->getId();

        if ($isPublic) {
            if ($watermarkSettings["linkAll"]) {
                return $watermarkText;
            }
            if ($watermarkSettings["linkRead"] && !$canEdit) {
                return $watermarkText;
            }
            if ($watermarkSettings["linkSecure"] && !$canDownload) {
                return $watermarkText;
            }
            if ($watermarkSettings["linkTags"]) {
                $tags = $watermarkSettings["linkTagsList"];
                $fileTags = \OC::$server->getSystemTagObjectMapper()->getTagIdsForObjects([$fileId], "files")[$fileId];
                foreach ($fileTags as $tagId) {
                    if (in_array($tagId, $tags, true)) {
                        return $watermarkText;
                    }
                }
            }
        } else {
            if ($watermarkSettings["shareAll"] && $file->getOwner()->getUID() !== $userId) {
                return $watermarkText;
            }
            if ($watermarkSettings["shareRead"] && !$canEdit) {
                return $watermarkText;
            }
        }
        if ($watermarkSettings["allGroups"]) {
            $groups = $watermarkSettings["allGroupsList"];
            foreach ($groups as $group) {
                if (\OC::$server->getGroupManager()->isInGroup($userId, $group)) {
                    return $watermarkText;
                }
            }
        }
        if ($watermarkSettings["allTags"]) {
            $tags = $watermarkSettings["allTagsList"];
            $fileTags = \OC::$server->getSystemTagObjectMapper()->getTagIdsForObjects([$fileId], "files")[$fileId];
            foreach ($fileTags as $tagId) {
                if (in_array($tagId, $tags, true)) {
                    return $watermarkText;
                }
            }
        }

        return false;
    }

    /**
     * Print error page
     *
     * @param string $error - error message
     * @param string $hint - error hint
     *
     * @return TemplateResponse
     */
    private function renderError($error, $hint = "") {
        return new TemplateResponse("", "error", array(
                "errors" => array(array(
                "error" => $error,
                "hint" => $hint
            ))
        ), "error");
    }
}

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

use OCA\Onlyoffice\TemplateManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IPreview;
use OCP\IRequest;
use OCP\Preview\IMimeIconProvider;
use Psr\Log\LoggerInterface;

/**
 * OCS handler
 */
class TemplateController extends Controller {

    /**
     * l10n service
     *
     * @var IL10N
     */
    private $trans;

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Preview manager
     *
     * @var IPreview
     */
    private $preview;

    /**
     * Mime icon provider
     *
     * @var IMimeIconProvider
     */
    private $mimeIconProvider;

    /**
     * @param string $AppName - application name
     * @param LoggerInterface $logger - logger
     * @param IL10N $trans - l10n service
     * @param IMimeIconProvider $mimeIconProvider - mime icon provider
     */
    public function __construct(
        $AppName,
        IRequest $request,
        IL10N $trans,
        LoggerInterface $logger,
        IPreview $preview,
        IMimeIconProvider $mimeIconProvider,
    ) {
        parent::__construct($AppName, $request);

        $this->trans = $trans;
        $this->logger = $logger;
        $this->preview = $preview;
        $this->mimeIconProvider = $mimeIconProvider;
    }

    /**
     * Get templates
     *
     * @return array
     */
    #[NoAdminRequired]
    public function getTemplates() {
        $templatesList = TemplateManager::getGlobalTemplates();

        $templates = [];
        foreach ($templatesList as $templatesItem) {
            $template = [
                "id" => $templatesItem->getId(),
                "name" => $templatesItem->getName(),
                "type" => TemplateManager::getTypeTemplate($templatesItem->getMimeType()),
                "icon" => $this->mimeIconProvider->getMimeIconUrl($templatesItem->getMimeType())
            ];
            array_push($templates, $template);
        }

        return $templates;
    }

    /**
     * Add global template
     *
     * @return array
     */
    public function addTemplate() {

        $file = $this->request->getUploadedFile("file");

        if (!is_null($file)) {
            if (is_uploaded_file($file["tmp_name"]) && $file["error"] === 0) {
                if (!TemplateManager::isTemplateType($file["name"])) {
                    return [
                        "error" => $this->trans->t("Template must be in OOXML format")
                    ];
                }

                $templateDir = TemplateManager::getGlobalTemplateDir();
                if ($templateDir->nodeExists($file["name"])) {
                    return [
                        "error" => $this->trans->t("Template already exists")
                    ];
                }

                $templateContent = file_get_contents($file["tmp_name"]);

                $template = $templateDir->newFile($file["name"]);
                $template->putContent($templateContent);

                $fileInfo = $template->getFileInfo();
                $result = [
                    "id" => $fileInfo->getId(),
                    "name" => $fileInfo->getName(),
                    "type" => TemplateManager::getTypeTemplate($fileInfo->getMimeType()),
                    "icon" => $this->mimeIconProvider->getMimeIconUrl($fileInfo->getMimeType())
                ];

                return $result;
            }
        }

        return [
            "error" => $this->trans->t("Invalid file provided")
        ];
    }

    /**
     * Delete template
     *
     * @param string $templateId - file identifier
     *
     * @return array
     */
    public function deleteTemplate($templateId) {
        $templateDir = TemplateManager::getGlobalTemplateDir();

        try {
            $templates = $templateDir->getById($templateId);
        } catch (\Exception $e) {
            $this->logger->error("deleteTemplate: $templateId", ["exception" => $e]);
            return [
                "error" => $this->trans->t("Failed to delete template")
            ];
        }

        if (empty($templates)) {
            $this->logger->info("Template not found: $templateId");
            return [
                "error" => $this->trans->t("Failed to delete template")
            ];
        }

        $templates[0]->delete();

        $this->logger->debug("Template: deleted " . $templates[0]->getName());
        return [];
    }

    /**
     * Returns the origin document key for editor
     *
     * @param string $fileId - file identifier
     * @param int $x - x
     * @param int $y - y
     * @param bool $crop - crop
     * @param string $mode - mode
     *
     * @return DataResponse|FileDisplayResponse
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function preview($fileId, $x = 256, $y = 256, $crop = false, $mode = IPreview::MODE_FILL) {
        if (empty($fileId) || $x === 0 || $y === 0) {
            return new DataResponse([], Http::STATUS_BAD_REQUEST);
        }

        $template = TemplateManager::getTemplate($fileId);
        if (empty($template)) {
            $this->logger->error("Template not found: $fileId");
            return new DataResponse([], Http::STATUS_NOT_FOUND);
        }

        try {
            $f = $this->preview->getPreview($template, $x, $y, $crop, $mode);
            $response = new FileDisplayResponse($f, Http::STATUS_OK, ["Content-Type" => $f->getMimeType()]);
            $response->cacheFor(3600 * 24);

            return $response;
        } catch (NotFoundException $e) {
            return new DataResponse([], Http::STATUS_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse([], Http::STATUS_BAD_REQUEST);
        }
    }
}

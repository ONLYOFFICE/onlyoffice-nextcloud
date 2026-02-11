<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2026
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

    public function __construct(
        string $appName,
        IRequest $request,
        private readonly IL10N $trans,
        private readonly LoggerInterface $logger,
        private readonly IPreview $preview,
        private readonly IMimeIconProvider $mimeIconProvider,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Get templates
     */
    #[NoAdminRequired]
    public function getTemplates(): DataResponse {
        $templatesList = TemplateManager::getGlobalTemplates();

        $templates = [];
        foreach ($templatesList as $templatesItem) {
            $template = [
                "id" => $templatesItem->getId(),
                "name" => $templatesItem->getName(),
                "type" => TemplateManager::getTypeTemplate($templatesItem->getMimeType()),
                "icon" => $this->mimeIconProvider->getMimeIconUrl($templatesItem->getMimeType())
            ];
            $templates[] = $template;
        }

        return new DataResponse($templates);
    }

    /**
     * Add global template
     *
     * @return DataResponse
     */
    public function addTemplate(): DataResponse {

        $file = $this->request->getUploadedFile("file");

        if (!empty($file) && is_uploaded_file($file["tmp_name"]) && $file["error"] === 0) {
            if (!TemplateManager::isTemplateType($file["name"])) {
                return new DataResponse([
                    "error" => $this->trans->t("Template must be in OOXML format")
                ]);
            }

            $templateDir = TemplateManager::getGlobalTemplateDir();
            if ($templateDir->nodeExists($file["name"])) {
                return new DataResponse([
                    "error" => $this->trans->t("Template already exists")
                ]);
            }

            $templateContent = file_get_contents($file["tmp_name"]);

            $template = $templateDir->newFile($file["name"]);
            $template->putContent($templateContent);

            return new DataResponse([
                "id" => $template->getId(),
                "name" => $template->getName(),
                "type" => TemplateManager::getTypeTemplate($template->getMimeType()),
                "icon" => $this->mimeIconProvider->getMimeIconUrl($template->getMimeType())
            ]);
        }

        return new DataResponse(["error" => $this->trans->t("Invalid file provided")]);
    }

    /**
     * Delete template
     *
     * @param int $templateId - file identifier
     *
     * @return DataResponse
     */
    public function deleteTemplate(int $templateId): DataResponse {
        $templateDir = TemplateManager::getGlobalTemplateDir();

        try {
            $templates = $templateDir->getById($templateId);
        } catch (\Exception $e) {
            $this->logger->error("deleteTemplate: $templateId", ["exception" => $e]);
            return new DataResponse([
                "error" => $this->trans->t("Failed to delete template")
            ]);
        }

        if (empty($templates)) {
            $this->logger->info("Template not found: $templateId");
            return new DataResponse([
                "error" => $this->trans->t("Failed to delete template")
            ]);
        }

        $templates[0]->delete();

        $this->logger->debug("Template: deleted " . $templates[0]->getName());
        return new DataResponse();
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
    public function preview(
        int $fileId,
        int $x = 256,
        int $y = 256,
        bool $crop = false,
        string $mode = IPreview::MODE_FILL
    ): DataResponse|FileDisplayResponse {
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
        } catch (NotFoundException) {
            return new DataResponse([], Http::STATUS_NOT_FOUND);
        } catch (\InvalidArgumentException) {
            return new DataResponse([], Http::STATUS_BAD_REQUEST);
        }
    }
}

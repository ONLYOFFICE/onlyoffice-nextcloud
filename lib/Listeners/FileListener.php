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

namespace OCA\Onlyoffice\Listeners;

use OC\Files\Node\Node;
use OCA\Onlyoffice\AppInfo\Application;
use OCA\Onlyoffice\ExtraPermissions;
use OCA\Onlyoffice\FileVersions;
use OCA\Onlyoffice\KeyManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;
use OCP\Files\File;

/**
 * OCP\Files events listener
 */
class FileListener implements IEventListener {

    /**
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function handle(Event $event): void {
        if ($event instanceof NodeDeletedEvent) {
            $this->nodeDeleted($event->getNode());
        }

        if ($event instanceof NodeWrittenEvent) {
            $this->nodeWritten($event->getNode());
        }
    }

    public function nodeDeleted(Node $node) {
        if (!$node instanceof File) {
            return;
        }

        try {
            $owner = $node->getOwner();
            if (empty($owner)) {
                return;
            }
            $ownerId = $owner->getUID();

            $fileId = $node->getId();

            KeyManager::delete($fileId, true);

            FileVersions::deleteAllVersions($ownerId, $node);

            $shares = [];
            $shareTypes = [
                IShare::TYPE_USER,
                IShare::TYPE_GROUP,
                IShare::TYPE_LINK,
                IShare::TYPE_ROOM,
            ];
            $shareManager = \OC::$server->get(\OCP\Share\IManager::class);

            foreach ($shareTypes as $shareType) {
                $shares = array_merge($shares, $shareManager->getSharesBy($ownerId, $shareType, $node));
            }
            $shareIds = array_map(fn(IShare $share) => $share->getId(), $shares);
            if (!empty($shareIds)) {
                ExtraPermissions::deleteList($shareIds);
            }
        } catch (\Exception $e) {
            $this->logger->error(
                "BeforeNodeDeletedEvent: " . json_encode($node),
                ["exception" => $e, "app" => Application::APP_ID]
            );
        }
    }

    public function nodeWritten(Node $node) {
        if (!$node instanceof File) {
            return;
        }

        KeyManager::delete($node->getId());

        $this->logger->debug(
            "NodeWrittenEvent: " . json_encode($node),
            ["app" => Application::APP_ID]
        );
    }
}

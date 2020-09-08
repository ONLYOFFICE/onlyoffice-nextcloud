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

namespace OCA\Onlyoffice;

use OCP\IDBConnection;
use OCP\ILogger;
use OCP\AppFramework\Utility\ITimeFactory;

use OC\Lock\DBLockingProvider;

/**
 * OnlyOffice locking provider
 *
 * @package OCA\Onlyoffice
 */
class LockingProvider extends DBLockingProvider {

    /**
	 * @param IDBConnection $connection - database provider
	 * @param ILogger $logger - logger
	 * @param ITimeFactory $timeFactory - timeFactory
	 * @param int $ttl - ttl
	 */
	public function __construct(IDBConnection $connection, ILogger $logger, ITimeFactory $timeFactory, int $ttl = 60) {
		parent::__construct($connection, $logger, $timeFactory, $ttl);
        $this->connection = $connection;
		$this->timeFactory = $timeFactory;
		$this->ttl = $ttl;
	}

	/**
	 * @param string $path - path to file
	 * @param int $type - lock type
	 */
    public function releaseLock($path, $type) {
		if ($type === self::LOCK_SHARED) {
			$this->connection->executeUpdate(
				'UPDATE `*PREFIX*file_locks` SET `lock` = 0 WHERE `key` = ? AND `lock` >= 1',
				[$path]
			);
		}

		parent::releaseLock($path, $type);
	}
}
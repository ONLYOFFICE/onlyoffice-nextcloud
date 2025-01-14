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

namespace OCA\Onlyoffice;

/**
 * Token generator
 *
 * @package OCA\Onlyoffice
 */
class Crypt {

    /**
     * Application configuration
     *
     * @var AppConfig
     */
    private $config;

    /**
     * @param AppConfig $config - application configutarion
     */
    public function __construct(AppConfig $appConfig) {
        $this->config = $appConfig;
    }

    /**
     * Generate token for the object
     *
     * @param array $object - object to signature
     *
     * @return string
     */
    public function getHash($object) {
        return \Firebase\JWT\JWT::encode($object, $this->config->getSKey(), "HS256");
    }

    /**
     * Create an object from the token
     *
     * @param string $token - token
     *
     * @return array
     */
    public function readHash($token) {
        $result = null;
        $error = null;
        if ($token === null) {
            return [$result, "token is empty"];
        }
        try {
            $result = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($this->config->getSKey(), "HS256"));
        } catch (\UnexpectedValueException $e) {
            $error = $e->getMessage();
        }
        return [$result, $error];
    }
}

<?php
/**
 *
 * (c) Copyright Ascensio System Limited 2010-2018
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
 * You can contact Ascensio System SIA at 17-2 Elijas street, Riga, Latvia, EU, LV-1021.
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

use OCA\Onlyoffice\AppConfig;

/**
 * Hash generator
 *
 * @package OCA\Onlyoffice
 */
class Crypt {

    /**
     * The secret key from the application configuration
     *
     * @var string
     */
    private $skey;

    /**
     * @param OCA\Onlyoffice\AppConfig $config - application configutarion
     */
    public function __construct(AppConfig $appConfig) {
        $this->skey = $appConfig->GetSKey();
    }

    /**
     * Generate base64 hash for the object
     *
     * @param array $object - object to signature hash
     *
     * @return string
     */
    public function GetHash($object) {
        $primaryKey = json_encode($object);
        $hash = $this->SignatureCreate($primaryKey);
        return $hash;
    }

    /**
     * Create an object from the base64 hash
     *
     * @param string $hash - base64 hash
     *
     * @return array
     */
    public function ReadHash($hash) {
        $result = NULL;
        $error = NULL;
        if ($hash === NULL) {
            return [$result, "hash is empty"];
        }
        try {
            $payload = base64_decode($hash);
            $payloadParts = explode("?", $payload, 2);

            if (count($payloadParts) === 2) {
                $encode = base64_encode( hash( "sha256", ($payloadParts[1] . $this->skey), true ) );

                if ($payloadParts[0] === $encode) {
                    $result = json_decode($payloadParts[1]);
                } else {
                    $error = "hash not equal";
                }
            } else {
                $error = "incorrect hash";
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        return [$result, $error];
    }

    /**
     * Generate base64 hash for the object
     *
     * @param string $primary_key - string to the signature hash
     *
     * @return string
     */
    private function SignatureCreate($primary_key) {
        $payload = base64_encode( hash( "sha256", ($primary_key . $this->skey), true ) ) . "?" . $primary_key;
        $base64Str = base64_encode($payload);

        return $base64Str;
    }
}

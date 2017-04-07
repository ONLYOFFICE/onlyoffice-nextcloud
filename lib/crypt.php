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
            $payloadParts = explode("?", $payload);

            $encode = base64_encode( hash( "sha256", ($payloadParts[1] . $this->skey), true ) );

            if ($payloadParts[0] === $encode) {
                $result = json_decode($payloadParts[1]);
            } else {
                $error = "hash not equal";
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

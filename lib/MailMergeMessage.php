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

namespace OCA\Onlyoffice;

class MailMergeMessage {

    public function __construct(private array $data = []) {}

    /**
     * Set from email address
     * @param string $value
     * @return MailMergeMessage
     */
    public function setFrom(string $value): self {
        $this->data['from'] = $value;
        return $this;
    }

    /**
     * Get from email address
     * @return string
     */
    public function getFrom(): string {
        return $this->data['from'] ?? '';
    }

    /**
     * Set to email address
     * @param string $value
     * @return MailMergeMessage
     */
    public function setTo(string $value): self {
        $this->data['to'] = $value;
        return $this;
    }

    /**
     * Get to email address
     * @return string
     */
    public function getTo(): string {
        return $this->data['to'] ?? '';
    }

    /**
     * Set subject
     * @param string $value
     * @return MailMergeMessage
     */
    public function setSubject(string $value): self {
        $this->data['subject'] = $value;
        return $this;
    }

    /**
     * Get subject
     * @return string
     */
    public function getSubject(): string {
        return $this->data['subject'] ?? '';
    }
    
    /**
     * Set plain message body
     * @param string $value
     * @return MailMergeMessage
     */
    public function setBodyPlain(string $value): self {
        $this->data['bodyPlain'] = $value;
        return $this;
    }

    /**
     * Get plain message body
     * @return string
     */
    public function getBodyPlain(): string {
        return $this->data['bodyPlain'] ?? '';
    }

    /**
     * Set html message body
     * @param string $value
     * @return MailMergeMessage
     */
    public function setBodyHtml(string $value): self {
        $this->data['bodyHtml'] = $value;
        return $this;
    }

    /**
     * Get html message body
     * @return string
     */
    public function getBodyHtml(): string {
        return $this->data['bodyHtml'] ?? '';
    }

    /**
     * Set attachment
     * @param \OCA\Onlyoffice\MailMergeAttachment $attachment
     * @return MailMergeMessage
     */
    public function setAttachment(MailMergeAttachment $attachment): self {
        $this->data['attachment'] = $attachment;
        return $this;
    }

    /**
     * Get attachment
     * @return MailMergeAttachment|null
     */
    public function getAttachment(): ?MailMergeAttachment {
        return $this->data['attachment'] ?? null;
    }

    /**
     * Check if MailMergeMessage has attachment
     * @return bool
     */
    public function hasAttachment(): bool {
        return $this->getAttachment() !== null;
    }
}

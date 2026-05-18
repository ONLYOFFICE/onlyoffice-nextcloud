<?php
/*
 * Copyright (C) Ascensio System SIA, 2009-2026
 *
 * This program is a free software product. You can redistribute it and/or
 * modify it under the terms of the GNU Affero General Public License (AGPL)
 * version 3 as published by the Free Software Foundation, together with the
 * additional terms provided in the LICENSE file.
 *
 * This program is distributed WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. For
 * details, see the GNU AGPL at: https://www.gnu.org/licenses/agpl-3.0.html
 *
 * You can contact Ascensio System SIA by email at info@onlyoffice.com
 * or by postal mail at 20A-6 Ernesta Birznieka-Upisha Street, Riga,
 * LV-1050, Latvia, European Union.
 *
 * The interactive user interfaces in modified versions of the Program
 * are required to display Appropriate Legal Notices in accordance with
 * Section 5 of the GNU AGPL version 3.
 *
 * No trademark rights are granted under this License.
 *
 * All non-code elements of the Product, including illustrations,
 * icon sets, and technical writing content, are licensed under the
 * Creative Commons Attribution-ShareAlike 4.0 International License:
 * https://creativecommons.org/licenses/by-sa/4.0/legalcode
 *
 * This license applies only to such non-code elements and does not
 * modify or replace the licensing terms applicable to the Program's
 * source code, which remains licensed under the GNU Affero General
 * Public License v3.
 *
 * SPDX-License-Identifier: AGPL-3.0-only
 */

    style("onlyoffice", "editor");
    \OCP\Util::addScript("onlyoffice", "onlyoffice-desktop", 'core');
if (!empty($_["directToken"])) {
    \OCP\Util::addScript("onlyoffice", "onlyoffice-directeditor", 'core');
}
    \OCP\Util::addStyle("onlyoffice", "onlyoffice-editor");
    \OCP\Util::addScript("onlyoffice", "onlyoffice-editor", 'core');
?>

<div id="app"
    <?php if (!empty($_["inviewer"])) { ?>
        class="onlyoffice-inviewer"
    <?php } ?>>

    <div id="iframeEditor"
        data-id="<?php p($_["fileId"]) ?>"
        data-path="<?php p($_["filePath"]) ?>"
        data-sharetoken="<?php p($_["shareToken"]) ?>"
        data-directtoken="<?php p($_["directToken"]) ?>"
        data-template="<?php p($_["isTemplate"]) ?>"
        data-anchor="<?php p($_["anchor"]) ?>"
        data-inframe="<?php p($_["inframe"]) ?>"
        data-inviewer="<?php p($_["inviewer"]) ?>"></div>
</div>

<?php if (!empty($_["directToken"])) { ?>
<script>
    document.querySelector("meta[name='viewport']")
        .setAttribute("content", "width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no");
</script>
<?php } ?>

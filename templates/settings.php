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

    style("onlyoffice", "settings");
    script("onlyoffice", "settings");
?>
<div class="section section-onlyoffice">
    <h2>ONLYOFFICE</h2>
    <a target="_blank" class="icon-info svg" title="" href="https://api.onlyoffice.com/editors/owncloud" data-original-title="<?php p($l->t("Documentation")) ?>"></a>

    <p><?php p($l->t("ONLYOFFICE Document Service Location specifies the address of the server with the document services installed. Please change the '<documentserver>' for the server address in the below line.")) ?></p>

    <p class="onlyoffice-header"><?php p($l->t("Document Editing Service address")) ?></p>
    <input id="onlyofficeUrl" value="<?php p($_["documentserver"]) ?>" placeholder="https://<documentserver>" type="text">

    <a id="onlyofficeAdv" class="onlyoffice-link-action onlyoffice-header"><?php p($l->t("Advanced server settings")) ?></a>
    <div id="onlyofficeSecretPanel" class="onlyoffice-hide">
        <p class="onlyoffice-header"><?php p($l->t("Document Editing Service address for internal requests from the server")) ?></p>
        <input id="onlyofficeInternalUrl" value="<?php p($_["documentserverInternal"]) ?>" placeholder="https://<documentserver>" type="text">

        <p class="onlyoffice-header"><?php p($l->t("Server address for internal requests from the Document Editing Service")) ?></p>
        <input id="onlyofficeStorageUrl" value="<?php p($_["storageUrl"]) ?>" placeholder="<?php p($_["currentServer"]) ?>" type="text">

        <p class="onlyoffice-header"><?php p($l->t("Secret key (leave blank to disable)")) ?></p>
        <input id="onlyofficeSecret" value="<?php p($_["secret"]) ?>" placeholder="secret" type="text">
    </div>
    <br id="onlyofficeSaveBreak" />

    <p class="onlyoffice-header">
        <input type="checkbox" class="checkbox" id="onlyofficeSameTab"
            <?php if ($_["sameTab"]) { ?>checked="checked"<?php } ?> />
        <label for="onlyofficeSameTab"><?php p($l->t("Open file in the same tab")) ?></label>
    </p>
    <br />

    <h3 class="onlyoffice-header"><?php p($l->t("The default application for opening the format")) ?></h3>
    <?php foreach ($_["defFormats"] as $format => $setting) { ?>
    <p>
        <input type="checkbox" class="checkbox"
            id="onlyofficeDefFormat<?php p($format) ?>"
            name="<?php p($format) ?>"
            <?php if ($setting) { ?>checked="checked"<?php } ?> />
        <label for="onlyofficeDefFormat<?php p($format) ?>"><?php p($format) ?></label>
    </p>
    <?php } ?>

    <a id="onlyofficeSave" class="button onlyoffice-header"><?php p($l->t("Save")) ?></a>
</div>
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

    style("onlyoffice", "settings");
    style("onlyoffice", "template");
    script("onlyoffice", "onlyoffice-settings");
    script("onlyoffice", "onlyoffice-template");

if ($_["tagsEnabled"]) {
    script("core", [
        "dist/systemtags",
    ]);
}
?>
<div class="section section-onlyoffice section-onlyoffice-addr">
    <h2>
        ONLYOFFICE
        <a target="_blank" class="icon-info svg" title="" href="https://helpcenter.onlyoffice.com/integration/nextcloud.aspx" data-original-title="<?php p($l->t("Documentation")) ?>"></a>
    </h2>

    <div id="onlyofficeAddrSettings">
        <h2><?php p($l->t("Server settings")) ?></h2>
        <p class="settings-hint"><?php p($l->t("ONLYOFFICE Docs Location specifies the address of the server with the document services installed. Please change the '<documentserver>' for the server address in the below line.")) ?></p>

        <p><?php p($l->t("ONLYOFFICE Docs address")) ?></p>
        <p><input id="onlyofficeUrl" value="<?php p($_["documentserver"]) ?>" placeholder="https://<documentserver>/" type="text"></p>

        <p>
            <input type="checkbox" class="checkbox" id="onlyofficeVerifyPeerOff"
                <?php if ($_["verifyPeerOff"]) { ?>checked="checked"<?php } ?> />
            <label for="onlyofficeVerifyPeerOff"><?php p($l->t("Disable certificate verification (insecure)")) ?></label>
        </p>

        <p class="onlyoffice-header"><?php p($l->t("Secret key (leave blank to disable)")) ?></p>
        <p class="groupbottom">
            <input id="onlyofficeSecret" value="<?php p($_["secret"]) ?>" placeholder="secret" type="password" />
            <input type="checkbox" id="personal-show" class="hidden-visually" name="show" />
            <label id="onlyofficeSecret-show" for="personal-show" class="personal-show-label"></label>
        </p>

        <p>
            <a id="onlyofficeAdv" class="onlyoffice-header">
                <?php p($l->t("Advanced server settings")) ?>
                <span class="icon icon-triangle-s"></span>
            </a>
        </p>
        <div id="onlyofficeSecretPanel" class="onlyoffice-hide">
            <p class="onlyoffice-header"><?php p($l->t("Authorization header (leave blank to use default header)")) ?></p>
            <p><input id="onlyofficeJwtHeader" value="<?php p($_["jwtHeader"]) ?>" placeholder="Authorization" type="text"></p>

            <p class="onlyoffice-header"><?php p($l->t("ONLYOFFICE Docs address for internal requests from the server")) ?></p>
            <p><input id="onlyofficeInternalUrl" value="<?php p($_["documentserverInternal"]) ?>" placeholder="https://<documentserver>/" type="text"></p>

            <p class="onlyoffice-header"><?php p($l->t("Server address for internal requests from ONLYOFFICE Docs")) ?></p>
            <p><input id="onlyofficeStorageUrl" value="<?php p($_["storageUrl"]) ?>" placeholder="<?php p($_["currentServer"]) ?>" type="text"></p>
        </div>
    </div>

    <br />
    <div>
        <button id="onlyofficeAddrSave" class="button"><?php p($l->t("Save")) ?></button>

        <div class="onlyoffice-demo">
            <input type="checkbox" class="checkbox" id="onlyofficeDemo"
                <?php if ($_["demo"]["enabled"]) { ?>checked="checked"<?php } ?>
                <?php if (!$_["demo"]["available"]) { ?>disabled="disabled"<?php } ?> />
            <label for="onlyofficeDemo"><?php p($l->t("Connect to demo ONLYOFFICE Docs server")) ?></label>

            <br />
            <?php if ($_["demo"]["available"]) { ?>
            <em><?php p($l->t("This is a public test server, please do not use it for private sensitive data. The server will be available during a 30-day period.")) ?></em>
            <?php } else { ?>
            <em><?php p($l->t("The 30-day test period is over, you can no longer connect to demo ONLYOFFICE Docs server.")) ?></em>
            <?php } ?>
        </div>
    </div>

</div>

<div class="section section-onlyoffice section-onlyoffice-common <?php if (empty($_["documentserver"]) && !$_["demo"]["enabled"] || !$_["successful"]) { ?>onlyoffice-hide<?php } ?>">
    <h2><?php p($l->t("Common settings")) ?></h2>

    <p>
        <input type="checkbox" class="checkbox" id="onlyofficeGroups"
            <?php if (count($_["limitGroups"]) > 0) { ?>checked="checked"<?php } ?> />
        <label for="onlyofficeGroups"><?php p($l->t("Allow the following groups to access the editors")) ?></label>
        <input type="hidden" id="onlyofficeLimitGroups" value="<?php p(implode("|", $_["limitGroups"])) ?>" style="display: block" />
    </p>

    <p>
        <input type="checkbox" class="checkbox" id="onlyofficePreview"
            <?php if ($_["preview"]) { ?>checked="checked"<?php } ?> />
        <label for="onlyofficePreview"><?php p($l->t("Use ONLYOFFICE to generate a document preview (it will take up disk space)")) ?></label>
    </p>

    <p>
        <input type="checkbox" class="checkbox" id="onlyofficeSameTab"
            <?php if ($_["sameTab"]) { ?>checked="checked"<?php } ?> />
        <label for="onlyofficeSameTab"><?php p($l->t("Open file in the same tab")) ?></label>
    </p>

    <p <?php if ($_["sameTab"]) { ?> style="display: none" <?php } ?> id="onlyofficeEnableSharingBlock">
        <input type="checkbox" class="checkbox" id="onlyofficeEnableSharing"
            <?php if ($_["enableSharing"]) { ?>checked="checked"<?php } ?> />
        <label for="onlyofficeEnableSharing"><?php p($l->t("Enable sharing (might increase editors loading time)")) ?></label>
    </p>

    <p>
        <input type="checkbox" class="checkbox" id="onlyofficeAdvanced"
            <?php if ($_["advanced"]) { ?>checked="checked"<?php } ?> />
        <label for="onlyofficeAdvanced"><?php p($l->t("Provide advanced document permissions using ONLYOFFICE Docs")) ?></label>
    </p>

    <p>
        <input type="checkbox" class="checkbox" id="onlyofficeVersionHistory"
            <?php if ($_["versionHistory"]) { ?>checked="checked"<?php } ?> />
        <label for="onlyofficeVersionHistory"><?php p($l->t("Keep metadata for each version once the document is edited (it will take up disk space)")) ?></label>
        <button id="onlyofficeClearVersionHistory" class="button"><?php p($l->t("Clear")) ?></button>
    </p>

    <p>
        <input type="checkbox" class="checkbox" id="onlyofficeCronChecker"
            <?php if ($_["cronChecker"]) { ?>checked="checked"<?php } ?> />
        <label for="onlyofficeCronChecker"><?php p($l->t("Enable background connection check to the editors")) ?></label>
    </p>

    <p>
        <input type="checkbox" class="checkbox" id="onlyofficeEmailNotifications"
            <?php if ($_["emailNotifications"]) { ?>checked="checked"<?php } ?> />
        <label for="onlyofficeEmailNotifications"><?php p($l->t("Enable e-mail notifications")) ?></label>
    </p>

    <p class="onlyoffice-header">
        <?php p($l->t("Unknown author display name")) ?>
    </p>
    <p><input id="onlyofficeUnknownAuthor" value="<?php p($_["unknownAuthor"]) ?>" placeholder="" type="text"></p>

    <p class="onlyoffice-header"><?php p($l->t("The default application for opening the format")) ?></p>
    <div class="onlyoffice-exts">
        <?php foreach ($_["formats"] as $format => $setting) { ?>
            <?php if (array_key_exists("mime", $setting)) { ?>
            <div>
                <input type="checkbox" class="checkbox"
                    id="onlyofficeDefFormat<?php p($format) ?>"
                    name="<?php p($format) ?>"
                    <?php if (array_key_exists("def", $setting) && $setting["def"]) { ?>checked="checked"<?php } ?> />
                <label for="onlyofficeDefFormat<?php p($format) ?>"><?php p($format) ?></label>
            </div>
            <?php } ?>
        <?php } ?>
    </div>

    <p class="onlyoffice-header">
        <?php p($l->t("Open the file for editing (due to format restrictions, the data might be lost when saving to the formats from the list below)")) ?>
    </p>
    <div class="onlyoffice-exts">
        <?php foreach ($_["formats"] as $format => $setting) { ?>
            <?php if (array_key_exists("editable", $setting) && $setting["editable"]) { ?>
            <div>
                <input type="checkbox" class="checkbox"
                    id="onlyofficeEditFormat<?php p($format) ?>"
                    name="<?php p($format) ?>"
                    <?php if (array_key_exists("edit", $setting) && $setting["edit"]) { ?>checked="checked"<?php } ?> />
                <label for="onlyofficeEditFormat<?php p($format) ?>"><?php p($format) ?></label>
            </div>
            <?php } ?>
        <?php } ?>
    </div>
    <br />

    <h2>
        <?php p($l->t("Editor customization settings")) ?>
    </h2>

    <p>
        <input type="checkbox" class="checkbox" id="onlyofficeForcesave"
            <?php if ($_["forcesave"]) { ?>checked="checked"<?php } ?> />
        <label for="onlyofficeForcesave"><?php p($l->t("Keep intermediate versions when editing (forcesave)")) ?></label>
    </p>

    <p>
        <input type="checkbox" class="checkbox" id="onlyofficeLiveViewOnShare"
            <?php if ($_["liveViewOnShare"]) { ?>checked="checked"<?php } ?> />
        <label for="onlyofficeLiveViewOnShare"><?php p($l->t("Enable live-viewing mode when accessing file by public link")) ?></label>
    </p>

    <p class="onlyoffice-header">
        <?php p($l->t("The customization section allows personalizing the editor interface")) ?>
    </p>

    <p>
        <input type="checkbox" class="checkbox" id="onlyofficeChat"
            <?php if ($_["chat"]) { ?>checked="checked"<?php } ?> />
        <label for="onlyofficeChat"><?php p($l->t("Display Chat menu button")) ?></label>
    </p>

    <p>
        <input type="checkbox" class="checkbox" id="onlyofficeCompactHeader"
            <?php if ($_["compactHeader"]) { ?>checked="checked"<?php } ?> />
        <label for="onlyofficeCompactHeader"><?php p($l->t("Display the header more compact")) ?></label>
    </p>

    <p>
        <input type="checkbox" class="checkbox" id="onlyofficeFeedback"
            <?php if ($_["feedback"]) { ?>checked="checked"<?php } ?> />
        <label for="onlyofficeFeedback"><?php p($l->t("Display Feedback & Support menu button")) ?></label>
    </p>

    <p>
        <input type="checkbox" class="checkbox" id="onlyofficeHelp"
            <?php if ($_["help"]) { ?>checked="checked"<?php } ?> />
        <label for="onlyofficeHelp"><?php p($l->t("Display Help menu button")) ?></label>
    </p>

    <p class="onlyoffice-header">
        <?php p($l->t("REVIEW mode for viewing")) ?>
    </p>
    <div class="onlyoffice-tables">
        <div>
            <input type="radio" class="radio"
                id="onlyofficeReviewDisplay_markup"
                name="reviewDisplay"
                <?php if ($_["reviewDisplay"] === "markup") { ?>checked="checked"<?php } ?> />
            <label for="onlyofficeReviewDisplay_markup"><?php p($l->t("Markup")) ?></label>
        </div>
        <div>
            <input type="radio" class="radio"
                id="onlyofficeReviewDisplay_final"
                name="reviewDisplay"
                <?php if ($_["reviewDisplay"] === "final") { ?>checked="checked"<?php } ?> />
            <label for="onlyofficeReviewDisplay_final"><?php p($l->t("Final")) ?></label>
        </div>
        <div>
            <input type="radio" class="radio"
                id="onlyofficeReviewDisplay_original"
                name="reviewDisplay"
                <?php if ($_["reviewDisplay"] === "original") { ?>checked="checked"<?php } ?> />
            <label for="onlyofficeReviewDisplay_original"><?php p($l->t("Original")) ?></label>
        </div>
    </div>

    <p class="onlyoffice-header">
        <?php p($l->t("Default editor theme")) ?>
    </p>
    <div class="onlyoffice-tables">
        <div>
            <input type="radio" class="radio"
                id="onlyofficeTheme_theme-system"
                name="theme"
                <?php if ($_["theme"] === "theme-system") { ?>checked="checked"<?php } ?> />
            <label for="onlyofficeTheme_theme-system"><?php p($l->t("Same as system")) ?></label>
        </div>
        <div>
            <input type="radio" class="radio"
                id="onlyofficeTheme_default-light"
                name="theme"
                <?php if ($_["theme"] === "default-light") { ?>checked="checked"<?php } ?> />
            <label for="onlyofficeTheme_default-light"><?php p($l->t("Light")) ?></label>
        </div>
        <div>
            <input type="radio" class="radio"
                id="onlyofficeTheme_default-dark"
                name="theme"
                <?php if ($_["theme"] === "default-dark") { ?>checked="checked"<?php } ?> />
            <label for="onlyofficeTheme_default-dark"><?php p($l->t("Dark")) ?></label>
        </div>
    </div>

    <br />
    <p><button id="onlyofficeSave" class="button"><?php p($l->t("Save")) ?></button></p>
</div>

<div class="section section-onlyoffice section-onlyoffice-templates <?php if (empty($_["documentserver"]) && !$_["demo"]["enabled"] || !$_["successful"]) { ?>onlyoffice-hide<?php } ?>">

    <h2>
        <?php p($l->t("Common templates")) ?>
        <input id="onlyofficeAddTemplate" type="file" class="hidden-visually" />
        <label for="onlyofficeAddTemplate" class="icon-add" title="<?php p($l->t("Add a new template")) ?>"></label>
    </h2>
    <ul class="onlyoffice-template-container">
        <?php foreach ($_["templates"] as $template) { ?>
            <li data-id=<?php p($template["id"]) ?> class="onlyoffice-template-item" >
                <img src="<?php p($template["icon"]) ?>" />
                <p><?php p($template["name"]) ?></p>
                <span class="onlyoffice-template-download"></span>
                <span class="onlyoffice-template-delete icon-delete"></span>
            </li>
        <?php } ?>
    </ul>

</div>

<div class="section section-onlyoffice section-onlyoffice-watermark <?php if (empty($_["documentserver"]) && !$_["demo"]["enabled"] || !$_["successful"]) { ?>onlyoffice-hide<?php } ?>">
    <h2><?php p($l->t("Security")) ?></h2>

    <p>
        <input type="checkbox" class="checkbox" id="onlyofficePlugins"
            <?php if ($_["plugins"]) { ?>checked="checked"<?php } ?> />
        <label for="onlyofficePlugins"><?php p($l->t("Enable plugins")) ?></label>
    </p>

    <p>
        <input type="checkbox" class="checkbox" id="onlyofficeMacros"
            <?php if ($_["macros"]) { ?>checked="checked"<?php } ?> />
        <label for="onlyofficeMacros"><?php p($l->t("Run document macros")) ?></label>
    </p>

    <p class="onlyoffice-header">
        <?php p($l->t("Enable document protection for")) ?>
    </p>
    <div class="onlyoffice-tables">
        <div>
            <input type="radio" class="radio"
                id="onlyofficeProtection_all"
                name="protection"
                <?php if ($_["protection"] === "all") { ?>checked="checked"<?php } ?> />
            <label for="onlyofficeProtection_all"><?php p($l->t("All users")) ?></label>
        </div>
        <div>
            <input type="radio" class="radio"
                id="onlyofficeProtection_owner"
                name="protection"
                <?php if ($_["protection"] === "owner") { ?>checked="checked"<?php } ?> />
            <label for="onlyofficeProtection_owner"><?php p($l->t("Owner only")) ?></label>
        </div>
    </div>

    <br />
    <p class="settings-hint"><?php p($l->t("Secure view enables you to secure documents by embedding a watermark")) ?></p>

    <p>
        <input type="checkbox" class="checkbox" id="onlyofficeWatermark_enabled"
            <?php if ($_["watermark"]["enabled"]) { ?>checked="checked"<?php } ?> />
        <label for="onlyofficeWatermark_enabled"><?php p($l->t("Enable watermarking")) ?></label>
    </p>

    <div id="onlyofficeWatermarkSettings" <?php if (!$_["watermark"]["enabled"]) { ?>class="onlyoffice-hide"<?php } ?> >
        <br />
        <p><?php p($l->t("Watermark text")) ?></p>
        <br />
        <p class="settings-hint"><?php p($l->t("Supported placeholders")) ?>: {userId}, {date}, {themingName}</p>
        <p><input id="onlyofficeWatermark_text" value="<?php p($_["watermark"]["text"]) ?>" placeholder="<?php p($l->t("DO NOT SHARE THIS")) ?> {userId} {date}" type="text"></p>

        <br />
        <?php if ($_["tagsEnabled"]) { ?>
        <p>
            <input type="checkbox" class="checkbox" id="onlyofficeWatermark_allTags"
                <?php if ($_["watermark"]["allTags"]) { ?>checked="checked"<?php } ?> />
            <label for="onlyofficeWatermark_allTags"><?php p($l->t("Show watermark on tagged files")) ?></label>
            <input type="hidden" id="onlyofficeWatermark_allTagsList" value="<?php p(implode("|", $_["watermark"]["allTagsList"])) ?>" style="display: block" />
        </p>
        <?php } ?>

        <p>
            <input type="checkbox" class="checkbox" id="onlyofficeWatermark_allGroups"
                <?php if ($_["watermark"]["allGroups"]) { ?>checked="checked"<?php } ?> />
            <label for="onlyofficeWatermark_allGroups"><?php p($l->t("Show watermark for users of groups")) ?></label>
            <input type="hidden" id="onlyofficeWatermark_allGroupsList" value="<?php p(implode("|", $_["watermark"]["allGroupsList"])) ?>" style="display: block" />
        </p>

        <p>
            <input type="checkbox" class="checkbox" id="onlyofficeWatermark_shareAll"
                <?php if ($_["watermark"]["shareAll"]) { ?>checked="checked"<?php } ?> />
            <label for="onlyofficeWatermark_shareAll"><?php p($l->t("Show watermark for all shares")) ?></label>
        </p>

        <p <?php if ($_["watermark"]["shareAll"]) { ?>class="onlyoffice-hide"<?php } ?> >
            <input type="checkbox" class="checkbox" id="onlyofficeWatermark_shareRead"
                <?php if ($_["watermark"]["shareRead"]) { ?>checked="checked"<?php } ?> />
            <label for="onlyofficeWatermark_shareRead"><?php p($l->t("Show watermark for read only shares")) ?></label>
        </p>

        <br />
        <p><?php p($l->t("Link shares")) ?></p>
        <p>
            <input type="checkbox" class="checkbox" id="onlyofficeWatermark_linkAll"
                <?php if ($_["watermark"]["linkAll"]) { ?>checked="checked"<?php } ?> />
            <label for="onlyofficeWatermark_linkAll"><?php p($l->t("Show watermark for all link shares")) ?></label>
        </p>

        <div id="onlyofficeWatermark_link_sensitive" <?php if ($_["watermark"]["linkAll"]) { ?>class="onlyoffice-hide"<?php } ?> >
            <p>
                <input type="checkbox" class="checkbox" id="onlyofficeWatermark_linkSecure"
                    <?php if ($_["watermark"]["linkSecure"]) { ?>checked="checked"<?php } ?> />
                <label for="onlyofficeWatermark_linkSecure"><?php p($l->t("Show watermark for download hidden shares")) ?></label>
            </p>

            <p>
                <input type="checkbox" class="checkbox" id="onlyofficeWatermark_linkRead"
                    <?php if ($_["watermark"]["linkRead"]) { ?>checked="checked"<?php } ?> />
                <label for="onlyofficeWatermark_linkRead"><?php p($l->t("Show watermark for read only link shares")) ?></label>
            </p>

            <?php if ($_["tagsEnabled"]) { ?>
            <p>
                <input type="checkbox" class="checkbox" id="onlyofficeWatermark_linkTags"
                    <?php if ($_["watermark"]["linkTags"]) { ?>checked="checked"<?php } ?> />
                <label for="onlyofficeWatermark_linkTags"><?php p($l->t("Show watermark on link shares with specific system tags")) ?></label>
                <input type="hidden" id="onlyofficeWatermark_linkTagsList" value="<?php p(implode("|", $_["watermark"]["linkTagsList"])) ?>" style="display: block" />
            </p>
            <?php } ?>
        </div>
    </div>

    <br />
    <p><button id="onlyofficeSecuritySave" class="button"><?php p($l->t("Save")) ?></button></p>

    <input type ="hidden" id="onlyofficeSettingsState" value="<?php p($_["settingsError"]) ?>" />
</div>

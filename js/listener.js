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

(function (OCA) {

    OCA.Onlyoffice = _.extend({
            AppName: "onlyoffice",
            context: null,
            folderUrl: null,
            frameSelector: null,
            canExpandHeader: true,
        }, OCA.Onlyoffice);

    OCA.Onlyoffice.ShowHeaderButton = function () {
        if (!OCA.Onlyoffice.canExpandHeader) {
            return;
        }

        var wrapper = $("<div id='onlyofficeHeader' />")

        var btnClose = $("<a class='icon icon-close-white'></a>");
        btnClose.on("click", function() {
            OCA.Onlyoffice.CloseEditor();
        });
        wrapper.prepend(btnClose);

        if (!$("#isPublic").val()) {
            var btnShare = $("<a class='icon icon-shared icon-white'></a>");
            btnShare.on("click", function () {
                OCA.Onlyoffice.OpenShareDialog();
            })
            wrapper.prepend(btnShare);
        }

        if (!$("#header .header-right").length) {
            $("#header").append("<div class='header-right'></div>");
        }
        wrapper.prependTo(".header-right");
    };

    OCA.Onlyoffice.CloseEditor = function () {
        $("body").removeClass("onlyoffice-inline");

        $(OCA.Onlyoffice.frameSelector).remove();
        $("#onlyofficeHeader").remove();
        if (OCA.Viewer && OCA.Viewer.close) {
            OCA.Viewer.close();
        }

        OCA.Onlyoffice.context = null;

        var url = OCA.Onlyoffice.folderUrl;
        if (!!url) {
            window.history.pushState(null, null, url);
            OCA.Onlyoffice.folderUrl = null;
        }
    };

    OCA.Onlyoffice.OpenShareDialog = function () {
        if (OCA.Onlyoffice.context) {
            if (!$("#app-sidebar").is(":visible")) {
                OCA.Onlyoffice.context.fileList.showDetailsView(OCA.Onlyoffice.context.fileName, "shareTabView");
                OC.Apps.showAppSidebar();
            } else {
                OC.Apps.hideAppSidebar();
            }
        }
    };

    OCA.Onlyoffice.onRequestSaveAs = function (saveData) {
        OC.dialogs.filepicker(t(OCA.Onlyoffice.AppName, "Save as"),
            function (fileDir) {
                saveData.dir = fileDir;
                $(OCA.Onlyoffice.frameSelector)[0].contentWindow.OCA.Onlyoffice.editorSaveAs(saveData);
            },
            false,
            "httpd/unix-directory");
    };

    OCA.Onlyoffice.onRequestInsertImage = function (imageMimes) {
        OC.dialogs.filepicker(t(OCA.Onlyoffice.AppName, "Insert image"),
            $(OCA.Onlyoffice.frameSelector)[0].contentWindow.OCA.Onlyoffice.editorInsertImage,
            false,
            imageMimes);
    };

    OCA.Onlyoffice.onRequestMailMergeRecipients = function (recipientMimes) {
        OC.dialogs.filepicker(t(OCA.Onlyoffice.AppName, "Select recipients"),
            $(OCA.Onlyoffice.frameSelector)[0].contentWindow.OCA.Onlyoffice.editorSetRecipient,
            false,
            recipientMimes);
    };

    OCA.Onlyoffice.onRequestCompareFile = function (revisedMimes) {
        OC.dialogs.filepicker(t(OCA.Onlyoffice.AppName, "Select file to compare"),
            $(OCA.Onlyoffice.frameSelector)[0].contentWindow.OCA.Onlyoffice.editorSetRevised,
            false,
            revisedMimes);
    };

    window.addEventListener("message", function(event) {
        if ($(OCA.Onlyoffice.frameSelector)[0].contentWindow !== event.source
            || !event.data["method"]) {
            return;
        }
        switch (event.data.method) {
            case "editorShowHeaderButton":
                OCA.Onlyoffice.ShowHeaderButton();
                break;
            case "editorRequestClose":
                OCA.Onlyoffice.CloseEditor();
                break;
            case "editorRequestSharingSettings":
                OCA.Onlyoffice.OpenShareDialog();
                break;
            case "editorRequestSaveAs":
                OCA.Onlyoffice.onRequestSaveAs(event.data.param);
                break;
            case "editorRequestInsertImage":
                OCA.Onlyoffice.onRequestInsertImage(event.data.param);
                break;
            case "editorRequestMailMergeRecipients":
                OCA.Onlyoffice.onRequestMailMergeRecipients(event.data.param);
                break;
            case "editorRequestCompareFile":
                OCA.Onlyoffice.onRequestCompareFile(event.data.param);
                break;
        }
    }, false);

})(OCA);

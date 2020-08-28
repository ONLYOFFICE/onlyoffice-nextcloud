/**
 *
 * (c) Copyright Ascensio System SIA 2020
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
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

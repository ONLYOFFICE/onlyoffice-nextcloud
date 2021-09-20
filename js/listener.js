/**
 *
 * (c) Copyright Ascensio System SIA 2021
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
            frameSelector: null,
        }, OCA.Onlyoffice);

    OCA.Onlyoffice.onRequestClose = function () {

        $(OCA.Onlyoffice.frameSelector).remove();

        if (OCA.Viewer && OCA.Viewer.close) {
            OCA.Viewer.close();
        }

        if (OCA.Onlyoffice.CloseEditor) {
            OCA.Onlyoffice.CloseEditor();
        }
    };

    OCA.Onlyoffice.onRequestSaveAs = function (saveData) {

        OC.dialogs.filepicker(t(OCA.Onlyoffice.AppName, "Save as"),
            function (fileDir) {
                saveData.dir = fileDir;
                $(OCA.Onlyoffice.frameSelector)[0].contentWindow.OCA.Onlyoffice.editorSaveAs(saveData);
            },
            false,
            "httpd/unix-directory",
            true,
            OC.dialogs.FILEPICKER_TYPE_CHOOSE,
            saveData.dir);
    };

    OCA.Onlyoffice.onRequestInsertImage = function (imageMimes) {
        OC.dialogs.filepicker(t(OCA.Onlyoffice.AppName, "Insert image"),
            $(OCA.Onlyoffice.frameSelector)[0].contentWindow.OCA.Onlyoffice.editorInsertImage,
            false,
            imageMimes,
            true);
    };

    OCA.Onlyoffice.onRequestMailMergeRecipients = function (recipientMimes) {
        OC.dialogs.filepicker(t(OCA.Onlyoffice.AppName, "Select recipients"),
            $(OCA.Onlyoffice.frameSelector)[0].contentWindow.OCA.Onlyoffice.editorSetRecipient,
            false,
            recipientMimes,
            true);
    };

    OCA.Onlyoffice.onRequestCompareFile = function (revisedMimes) {
        OC.dialogs.filepicker(t(OCA.Onlyoffice.AppName, "Select file to compare"),
            $(OCA.Onlyoffice.frameSelector)[0].contentWindow.OCA.Onlyoffice.editorSetRevised,
            false,
            revisedMimes,
            true);
    };

    OCA.Onlyoffice.onDocumentReady = function (documentType) {
        if (documentType === "word") {
            if (OCA.Onlyoffice.bindVersionClick) {
                OCA.Onlyoffice.bindVersionClick();
            }
        } else if (OCA.Onlyoffice.unbindVersionClick) {
            OCA.Onlyoffice.unbindVersionClick();
        }
    };

    OCA.Onlyoffice.changeFavicon = function (favicon) {
        $('link[rel="icon"]').attr("href", favicon);
    };

    OCA.Onlyoffice.onShowMessage = function (messageObj) {
        switch (messageObj.type) {
            case "success":
                OCP.Toast.success(messageObj.message, messageObj.props);
                break;
            case "error":
                OCP.Toast.error(messageObj.message, messageObj.props);
                break;
        }
    }

    window.addEventListener("message", function (event) {
        if (!$(OCA.Onlyoffice.frameSelector).length
            || $(OCA.Onlyoffice.frameSelector)[0].contentWindow !== event.source
            || !event.data["method"]) {
            return;
        }
        switch (event.data.method) {
            case "editorRequestClose":
                OCA.Onlyoffice.onRequestClose();
                break;
            case "editorRequestSharingSettings":
                if (OCA.Onlyoffice.OpenShareDialog) {
                    OCA.Onlyoffice.OpenShareDialog();
                }
                break;
            case "onRefreshVersionsDialog":
                if (OCA.Onlyoffice.RefreshVersionsDialog) {
                    OCA.Onlyoffice.RefreshVersionsDialog();
                }
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
            case "onDocumentReady":
                OCA.Onlyoffice.onDocumentReady(event.data.param);
                break;
            case "changeFavicon":
                OCA.Onlyoffice.changeFavicon(event.data.param);
                break;
            case "onShowMessage":
                OCA.Onlyoffice.onShowMessage(event.data.param);
        }
    }, false);

    window.addEventListener("popstate", function (event) {
        if ($(OCA.Onlyoffice.frameSelector).length) {
            OCA.Onlyoffice.onRequestClose();
        }
    });

    window.addEventListener("DOMNodeRemoved", function(event) {
        if ($(event.target).length && $(OCA.Onlyoffice.frameSelector).length
            && ($(event.target)[0].id === "viewer" || $(event.target)[0].id === $(OCA.Onlyoffice.frameSelector)[0].id)) {
            OCA.Onlyoffice.changeFavicon($(OCA.Onlyoffice.frameSelector)[0].contentWindow.OCA.Onlyoffice.faviconBase);
        }
    });
})(OCA);

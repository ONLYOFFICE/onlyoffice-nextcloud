/**
 *
 * (c) Copyright Ascensio System SIA 2019
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

(function ($, OCA) {

    OCA.Onlyoffice = _.extend({
            AppName: "onlyoffice"
        }, OCA.Onlyoffice);

    OCA.Onlyoffice.InitEditor = function () {
        var displayError = function (error) {
            OC.Notification.show(error, {
                type: "error"
            });
        };

        var fileId = $("#iframeEditor").data("id");
        var filePath = $("#iframeEditor").data("path");
        var fileToken = $("#iframeEditor").data("token");
        if (!fileId && !fileToken) {
            displayError(t(OCA.Onlyoffice.AppName, "FileId is empty"));
            return;
        }

        if (typeof DocsAPI === "undefined") {
            displayError(t(OCA.Onlyoffice.AppName, "ONLYOFFICE cannot be reached. Please contact admin"));
            return;
        }

        var configUrl = OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/config/{fileId}",
            {
                fileId: fileId || 0
            });

        var params = [];
        if (filePath) {
            params.push("filePath=" + encodeURIComponent(filePath));
        }
        if (fileToken) {
            params.push("token=" + encodeURIComponent(fileToken));
        }
        if (OCA.Onlyoffice.Desktop) {
            params.push("desktop=true");
        }
        if (params.length) {
            configUrl += "?" + params.join("&");
        }

        $.ajax({
            url: configUrl,
            success: function onSuccess(config) {
                if (config) {
                    if (config.error != null) {
                        displayError(config.error);
                        return;
                    }

                    if (config.editorConfig.tenant) {
                        displayError(t(OCA.Onlyoffice.AppName, "You are using public demo ONLYOFFICE Document Server. Please do not store private sensitive data."));
                    }

                    var docIsChanged = null;
                    var docIsChangedTimeout = null;

                    var setPageTitle = function(event) {
                        clearTimeout(docIsChangedTimeout);

                        if (docIsChanged !== event.data) {
                            var titleChange = function () {
                                window.document.title = config.document.title + (event.data ? " *" : "") + " - " + oc_defaults.title;
                                docIsChanged = event.data;
                            };

                            if (event === false || event.data) {
                                titleChange();
                            } else {
                                docIsChangedTimeout = setTimeout(titleChange, 500);
                            }
                        }
                    };
                    setPageTitle(false);

                    config.events = {
                        "onDocumentStateChange": setPageTitle,
                    };

                    if (OC.currentUser) {
                        config.events.onRequestSaveAs = OCA.Onlyoffice.onRequestSaveAs;
                        config.events.onRequestInsertImage = OCA.Onlyoffice.onRequestInsertImage;
                        config.events.onRequestMailMergeRecipients = OCA.Onlyoffice.onRequestMailMergeRecipients;
                    }

                    OCA.Onlyoffice.docEditor = new DocsAPI.DocEditor("iframeEditor", config);

                    if (config.type === "mobile" && $("#app > iframe").css("position") === "fixed") {
                        $("#app > iframe").css("height", "calc(100% - 50px)");
                    }
                }
            }
        });
    };

    OCA.Onlyoffice.onRequestSaveAs = function(event) {
        var title = event.data.title;
        var url = event.data.url;

        var saveAs = function(fileDir) {
            var saveData = {
                name: title,
                dir: fileDir,
                url: url
            };

            $.post(OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/save"),
                saveData,
                function onSuccess(response) {
                    if (response.error) {
                        OC.Notification.show(response.error, {
                            type: "error",
                            timeout: 3
                        });
                        return;
                    }

                    OC.Notification.show(t(OCA.Onlyoffice.AppName, "File saved") + " (" + response.name + ")", {
                        timeout: 3
                    });
                });
        };

        OC.dialogs.filepicker(t(OCA.Onlyoffice.AppName, "Save as"), saveAs, false, "httpd/unix-directory");
    };

    OCA.Onlyoffice.onRequestInsertImage = function() {

        var insertImage = function(filePath) {
            $.get(OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/url?filePath={filePath}",
                {
                    filePath: filePath
                }),
                function onSuccess(response) {
                    if (response.error) {
                        OC.Notification.show(response.error, {
                            type: "error",
                            timeout: 3
                        });
                        return;
                    }

                    OCA.Onlyoffice.docEditor.insertImage(response);
                });
        };

        var imageMimes = [
            "image/bmp", "image/x-bmp", "image/x-bitmap", "application/bmp",
            "image/gif",
            "image/jpeg", "image/jpg", "application/jpg", "application/x-jpg",
            "image/png", "image/x-png", "application/png", "application/x-png"
        ];

        OC.dialogs.filepicker(t(OCA.Onlyoffice.AppName, "Insert image"), insertImage, false, imageMimes);
    };

    OCA.Onlyoffice.onRequestMailMergeRecipients = function() {

        var setRecipient = function(filePath) {
            $.get(OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/url?filePath={filePath}",
                {
                    filePath: filePath
                }),
                function onSuccess(response) {
                    if (response.error) {
                        OC.Notification.show(response.error, {
                            type: "error",
                            timeout: 3
                        });
                        return;
                    }

                    OCA.Onlyoffice.docEditor.setMailMergeRecipients(response);
                });
        };

        var recipientMimes = [
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        ];

        OC.dialogs.filepicker(t(OCA.Onlyoffice.AppName, "Select recipients"), setRecipient, false, recipientMimes);
    };

    $(document).ready(OCA.Onlyoffice.InitEditor);

})(jQuery, OCA);

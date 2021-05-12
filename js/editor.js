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

(function ($, OCA) {

    OCA.Onlyoffice = _.extend({
            AppName: "onlyoffice",
            inframe: false,
            fileId: null,
            shareToken: null,
            insertImageType: null
        }, OCA.Onlyoffice);

    OCA.Onlyoffice.InitEditor = function () {
        var displayError = function (error) {
            OCP.Toast.error(error, {
                timeout: -1
            });
        };

        OCA.Onlyoffice.fileId = $("#iframeEditor").data("id");
        OCA.Onlyoffice.shareToken = $("#iframeEditor").data("sharetoken");
        OCA.Onlyoffice.version = $("#iframeEditor").data("version");
        var directToken = $("#iframeEditor").data("directtoken");
        OCA.Onlyoffice.template = $("#iframeEditor").data("template");
        OCA.Onlyoffice.inframe = !!$("#iframeEditor").data("inframe");
        OCA.Onlyoffice.filePath = $("#iframeEditor").data("path");
        var guestName = localStorage.getItem("nick");
        if (!OCA.Onlyoffice.fileId && !OCA.Onlyoffice.shareToken && !directToken) {
            displayError(t(OCA.Onlyoffice.AppName, "FileId is empty"));
            return;
        }

        if (typeof DocsAPI === "undefined") {
            displayError(t(OCA.Onlyoffice.AppName, "ONLYOFFICE cannot be reached. Please contact admin"));
            return;
        }

        var configUrl = OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/config/{fileId}",
            {
                fileId: OCA.Onlyoffice.fileId || 0
            });

        var params = [];
        var filePath = $("#iframeEditor").data("path");
        if (filePath) {
            params.push("filePath=" + encodeURIComponent(filePath));
        }
        if (OCA.Onlyoffice.shareToken) {
            params.push("shareToken=" + encodeURIComponent(OCA.Onlyoffice.shareToken));
        }
        if (directToken) {
            $("html").addClass("onlyoffice-full-page");
            params.push("directToken=" + encodeURIComponent(directToken));
        }
        if (OCA.Onlyoffice.version > 0) {
            params.push("version=" + OCA.Onlyoffice.version);
        }
        if (OCA.Onlyoffice.template) {
            params.push("template=true");
        }
        if (guestName) {
            params.push("guestName=" + encodeURIComponent(guestName));
        }

        if (OCA.Onlyoffice.inframe || directToken) {
            params.push("inframe=true");
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

                    if (config.redirectUrl) {
                        location.href = config.redirectUrl;
                        return;
                    }

                    var docIsChanged = null;
                    var docIsChangedTimeout = null;

                    var setPageTitle = function (event) {
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

                    OCA.Onlyoffice.documentType = config.documentType;

                    config.events = {
                        "onDocumentStateChange": setPageTitle,
                        "onRequestHistory": OCA.Onlyoffice.onRequestHistory,
                        "onRequestHistoryData": OCA.Onlyoffice.onRequestHistoryData,
                        "onDocumentReady": OCA.Onlyoffice.onDocumentReady,
                    };

                    if (!OCA.Onlyoffice.version) {
                        config.events.onRequestHistoryClose = OCA.Onlyoffice.onRequestHistoryClose;
                    }

                    if (config.editorConfig.tenant) {
                        config.events.onAppReady = function () {
                            OCA.Onlyoffice.docEditor.showMessage(t(OCA.Onlyoffice.AppName, "You are using public demo ONLYOFFICE Document Server. Please do not store private sensitive data."));
                        };
                    }

                    if (OCA.Onlyoffice.inframe && !OCA.Onlyoffice.shareToken
                        || OC.currentUser) {
                        config.events.onRequestSaveAs = OCA.Onlyoffice.onRequestSaveAs;
                        config.events.onRequestInsertImage = OCA.Onlyoffice.onRequestInsertImage;
                        config.events.onRequestMailMergeRecipients = OCA.Onlyoffice.onRequestMailMergeRecipients;
                        config.events.onRequestCompareFile = OCA.Onlyoffice.onRequestCompareFile;
                    }

                    if (OCA.Onlyoffice.directEditor || OCA.Onlyoffice.inframe) {
                        config.events.onRequestClose = OCA.Onlyoffice.onRequestClose;
                    }

                    if (OCA.Onlyoffice.inframe
                        && config._files_sharing && !OCA.Onlyoffice.shareToken
                        && window.parent.OCA.Onlyoffice.context) {
                        config.events.onRequestSharingSettings = OCA.Onlyoffice.onRequestSharingSettings;
                    }

                    OCA.Onlyoffice.docEditor = new DocsAPI.DocEditor("iframeEditor", config);

                    if (OCA.Onlyoffice.directEditor) {
                        OCA.Onlyoffice.directEditor.loaded();
                    }

                    if (!OCA.Onlyoffice.directEditor
                        && config.type === "mobile" && $("#app > iframe").css("position") === "fixed") {
                        $("#app > iframe").css("height", "calc(100% - 50px)");
                    }
                }
            }
        });
    };

    OCA.Onlyoffice.onRequestHistory = function (version) {
        $.get(OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/history?fileId={fileId}&shareToken={shareToken}",
            {
                fileId: OCA.Onlyoffice.fileId || 0,
                shareToken: OCA.Onlyoffice.shareToken || "",
            }),
            function onSuccess(response) {
                if (response.error) {
                    var data = {error: response.error};
                } else {
                    var currentVersion = 0;
                    $.each(response, function (i, fileVersion) {
                        if (fileVersion.version >= currentVersion) {
                            currentVersion = fileVersion.version;
                        }

                        fileVersion.created = moment(fileVersion.created * 1000).format("L LTS");
                        if (fileVersion.changes) {
                            $.each(fileVersion.changes, function (j, change) {
                                change.created = moment(change.created + "+00:00").format("L LTS");
                            });
                        }
                    });

                    if (version) {
                        currentVersion = Math.min(currentVersion, version);
                    }

                    data = {
                        currentVersion: currentVersion,
                        history: response,
                    };
                }
                OCA.Onlyoffice.docEditor.refreshHistory(data);
        });
    };

    OCA.Onlyoffice.onRequestHistoryData = function (event) {
        var version = event.data;

        $.get(OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/version?fileId={fileId}&version={version}&shareToken={shareToken}",
            {
                fileId: OCA.Onlyoffice.fileId || 0,
                version: version,
                shareToken: OCA.Onlyoffice.shareToken || "",
            }),
            function onSuccess(response) {
                if (response.error) {
                    response = {
                        error: response.error,
                        version: version,
                    };
                }
                OCA.Onlyoffice.docEditor.setHistoryData(response);
        });
    };

    OCA.Onlyoffice.onRequestHistoryClose = function () {
        location.reload(true);
    };

    OCA.Onlyoffice.onDocumentReady = function() {
        if (OCA.Onlyoffice.inframe) {
            window.parent.postMessage({
                method: "onDocumentReady",
                param: OCA.Onlyoffice.documentType
            },
            "*");
        }

        if (OCA.Onlyoffice.version > 0) {
            OCA.Onlyoffice.onRequestHistory(OCA.Onlyoffice.version);
        }
    };

    OCA.Onlyoffice.onRequestSaveAs = function (event) {
        var saveData = {
            name: event.data.title,
            url: event.data.url
        };

        var arrayPath = OCA.Onlyoffice.filePath.split("/");
        arrayPath.pop();
        arrayPath.shift();
        var currentDir = "/" + arrayPath.join("/");

        if (OCA.Onlyoffice.inframe) {
            window.parent.postMessage({
                method: "editorRequestSaveAs",
                param: saveData
            },
            "*");
        } else {
            OC.dialogs.filepicker(t(OCA.Onlyoffice.AppName, "Save as"),
                function (fileDir) {
                    saveData.dir = fileDir;
                    OCA.Onlyoffice.editorSaveAs(saveData);
                },
                false,
                "httpd/unix-directory",
                false,
                OC.dialogs.FILEPICKER_TYPE_CHOOSE,
                currentDir);
        }
    };

    OCA.Onlyoffice.editorSaveAs = function (saveData) {
        $.post(OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/save"),
            saveData,
            function onSuccess(response) {
                if (response.error) {
                    OCP.Toast.error(response.error);
                    return;
                }

                OCP.Toast.success(t(OCA.Onlyoffice.AppName, "File saved") + " (" + response.name + ")");
            });
    };

    OCA.Onlyoffice.onRequestInsertImage = function (event) {
        var imageMimes = [
            "image/bmp", "image/x-bmp", "image/x-bitmap", "application/bmp",
            "image/gif",
            "image/jpeg", "image/jpg", "application/jpg", "application/x-jpg",
            "image/png", "image/x-png", "application/png", "application/x-png"
        ];

        if (event.data) {
            OCA.Onlyoffice.insertImageType = event.data.c;
        }

        if (OCA.Onlyoffice.inframe) {
            window.parent.postMessage({
                method: "editorRequestInsertImage",
                param: imageMimes
            },
            "*");
        } else {
            OC.dialogs.filepicker(t(OCA.Onlyoffice.AppName, "Insert image"), OCA.Onlyoffice.editorInsertImage, false, imageMimes);
        }
    };

    OCA.Onlyoffice.editorInsertImage = function (filePath) {
        $.get(OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/url?filePath={filePath}",
            {
                filePath: filePath
            }),
            function onSuccess(response) {
                if (response.error) {
                    OCP.Toast.error(response.error);
                    return;
                }

                if (OCA.Onlyoffice.insertImageType) {
                    response.c = OCA.Onlyoffice.insertImageType;
                }

                OCA.Onlyoffice.docEditor.insertImage(response);
            });
    };

    OCA.Onlyoffice.onRequestMailMergeRecipients = function () {
        var recipientMimes = [
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        ];

        if (OCA.Onlyoffice.inframe) {
            window.parent.postMessage({
                method: "editorRequestMailMergeRecipients",
                param: recipientMimes
            },
            "*");
        } else {
            OC.dialogs.filepicker(t(OCA.Onlyoffice.AppName, "Select recipients"), OCA.Onlyoffice.editorSetRecipient, false, recipientMimes);
        }
    };

    OCA.Onlyoffice.editorSetRecipient = function (filePath) {
        $.get(OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/url?filePath={filePath}",
            {
                filePath: filePath
            }),
            function onSuccess(response) {
                if (response.error) {
                    OCP.Toast.error(response.error);
                    return;
                }

                OCA.Onlyoffice.docEditor.setMailMergeRecipients(response);
            });
    };

    OCA.Onlyoffice.onRequestClose = function () {
        if (OCA.Onlyoffice.directEditor) {
            OCA.Onlyoffice.directEditor.close();
            return;
        }

        OCA.Onlyoffice.docEditor.destroyEditor();

        window.parent.postMessage({
            method: "editorRequestClose"
        },
        "*");
    };

    OCA.Onlyoffice.onRequestSharingSettings = function () {
        window.parent.postMessage({
            method: "editorRequestSharingSettings"
        },
        "*");
    };

    OCA.Onlyoffice.onRequestCompareFile = function () {
        var revisedMimes = [
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
        ];

        if (OCA.Onlyoffice.inframe) {
            window.parent.postMessage({
                method: "editorRequestCompareFile",
                param: revisedMimes
            },
            "*");
        } else {
            OC.dialogs.filepicker(t(OCA.Onlyoffice.AppName, "Select file to compare"), OCA.Onlyoffice.editorSetRevised, false, revisedMimes);
        }
    };

    OCA.Onlyoffice.editorSetRevised = function (filePath) {
        $.get(OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/url?filePath={filePath}",
            {
                filePath: filePath
            }),
            function onSuccess(response) {
                if (response.error) {
                    OCP.Toast.error(response.error);
                    return;
                }

                OCA.Onlyoffice.docEditor.setRevisedFile(response);
            });
    };

    OCA.Onlyoffice.InitEditor();

})(jQuery, OCA);

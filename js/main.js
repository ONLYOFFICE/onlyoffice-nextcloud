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
        }, OCA.Onlyoffice);

    OCA.Onlyoffice.setting = {};

    OCA.Onlyoffice.CreateFile = function (name, fileList) {
        var dir = fileList.getCurrentDirectory();

        if (!OCA.Onlyoffice.setting.sameTab || OCA.Onlyoffice.Desktop) {
            $loaderUrl = OCA.Onlyoffice.Desktop ? "" : OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/loader");
            var winEditor = window.open($loaderUrl);
        }

        var createData = {
            name: name,
            dir: dir
        };

        if ($("#isPublic").val()) {
            createData.shareToken = encodeURIComponent($("#sharingToken").val());
        }

        $.post(OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/new"),
            createData,
            function onSuccess(response) {
                if (response.error) {
                    if (winEditor) {
                        winEditor.close();
                    }
                    OCP.Toast.error(response.error);
                    return;
                }

                fileList.add(response, { animate: true });
                OCA.Onlyoffice.OpenEditor(response.id, dir, response.name, 0, winEditor);

                OCA.Onlyoffice.context = { fileList: fileList };
                OCA.Onlyoffice.context.fileName = response.name;

                OCP.Toast.success(t(OCA.Onlyoffice.AppName, "File created"));
            }
        );
    };

    OCA.Onlyoffice.OpenEditor = function (fileId, fileDir, fileName, version, winEditor, readonly) {
        var filePath = "";
        if (fileName) {
            filePath = fileDir.replace(new RegExp("\/$"), "") + "/" + fileName;
        }
        var url = OC.generateUrl("/apps/" + OCA.Onlyoffice.AppName + "/{fileId}?filePath={filePath}",
            {
                fileId: fileId,
                filePath: filePath
            });

        if ($("#isPublic").val()) {
            url = OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/s/{shareToken}?fileId={fileId}",
                {
                    shareToken: encodeURIComponent($("#sharingToken").val()),
                    fileId: fileId
                });
        }
		if (readonly)
			url += '&readonly=1';
        if (version > 0) {
            url += "&version=" + version;
        }

		if (readonly)
			url += '&readonly=1';
        if (winEditor && winEditor.location) {
            winEditor.location.href = url;
        } else if (!OCA.Onlyoffice.setting.sameTab || OCA.Onlyoffice.Desktop) {
            winEditor = window.open(url, "_blank");
        } else if ($("#isPublic").val() === "1" && !$("#filestable").length) {
            location.href = url;
        } else {
            OCA.Onlyoffice.frameSelector = "#onlyofficeFrame";
            var $iframe = $("<iframe id=\"onlyofficeFrame\" nonce=\"" + btoa(OC.requestToken) + "\" scrolling=\"no\" allowfullscreen src=\"" + url + "&inframe=true\" />");
            $("#app-content").append($iframe);

            $("body").addClass("onlyoffice-inline");

            if (OCA.Files.Sidebar) {
                OCA.Files.Sidebar.close();
            } else {
                //todo: remove. only for v17
                OC.Apps.hideAppSidebar();
            }

            var scrollTop = $(window).scrollTop();
            $(OCA.Onlyoffice.frameSelector).css("top", scrollTop);

            OCA.Onlyoffice.folderUrl = location.href;
            window.history.pushState(null, null, url);
        }
    };

    OCA.Onlyoffice.ShowHeaderButton = function () {
        if ($("#onlyofficeHeader").length) {
            return;
        }

        var wrapper = $("<div id='onlyofficeHeader' />")

        var btnClose = $("<a class='icon icon-close-white'></a>");
        btnClose.on("click", function() {
            OCA.Onlyoffice.onRequestClose();
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
        $("#onlyofficeHeader").remove();

        OCA.Onlyoffice.context = null;

        var url = OCA.Onlyoffice.folderUrl;
        if (!!url) {
            window.history.pushState(null, null, url);
            OCA.Onlyoffice.folderUrl = null;
        }

        if (OCA.Versions) {
            OCA.Onlyoffice.bindVersionClick();
        }
    };

    OCA.Onlyoffice.OpenShareDialog = function () {
        if (OCA.Onlyoffice.context) {
            if (!$("#app-sidebar").is(":visible")) {
                OCA.Onlyoffice.context.fileList.showDetailsView(OCA.Onlyoffice.context.fileName, "sharing");
                OC.Apps.showAppSidebar();
            } else {
                if (OCA.Files.Sidebar) {
                    OCA.Files.Sidebar.close();
                } else {
                    //todo: remove. only for v17
                    OC.Apps.hideAppSidebar();
                }
            }
        }
    };

    OCA.Onlyoffice.FileClick = function (fileName, context) {
        var fileInfoModel = context.fileInfoModel || context.fileList.getModelForFile(fileName);
        OCA.Onlyoffice.OpenEditor(fileInfoModel.id, context.dir, fileName, null, OCA.Onlyoffice.setting.readOnly);

        OCA.Onlyoffice.context = context;
        OCA.Onlyoffice.context.fileName = fileName;
    };

    OCA.Onlyoffice.FileEditClick = function (fileName, context) {
        var fileInfoModel = context.fileInfoModel || context.fileList.getModelForFile(fileName);
        OCA.Onlyoffice.OpenEditor(fileInfoModel.id, context.dir, fileName);

        OCA.Onlyoffice.context = context;
        OCA.Onlyoffice.context.fileName = fileName;
    };

    OCA.Onlyoffice.FileConvertClick = function (fileName, context) {
        var fileInfoModel = context.fileInfoModel || context.fileList.getModelForFile(fileName);
        var fileList = context.fileList;

        var convertData = {
            fileId: fileInfoModel.id
        };

        if ($("#isPublic").val()) {
            convertData.shareToken = encodeURIComponent($("#sharingToken").val());
        }

        $.post(OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/convert"),
            convertData,
            function onSuccess(response) {
                if (response.error) {
                    OCP.Toast.error(response.error);
                    return;
                }

                if (response.parentId == fileList.dirInfo.id) {
                    fileList.add(response, { animate: true });
                }

                OCP.Toast.success(t(OCA.Onlyoffice.AppName, "File created"));
            });
    };

    OCA.Onlyoffice.GetSettings = function (callbackSettings) {
        if (OCA.Onlyoffice.setting.formats) {

            callbackSettings();

        } else {

            $.get(OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/settings"),
                function onSuccess(settings) {
                    OCA.Onlyoffice.setting = settings;

                    callbackSettings();
                }
            );

        }
    };

    OCA.Onlyoffice.FileList = {
        attach: function (fileList) {
            if (fileList.id == "trashbin") {
                return;
            }

            var register = function () {
                var formats = OCA.Onlyoffice.setting.formats;

                $.each(formats, function (ext, config) {
                    if (!config.mime) {
                        return true;
                    }
                    fileList.fileActions.registerAction({
                        name: "onlyofficeOpen",
                        displayName: t(OCA.Onlyoffice.AppName, "Open in ONLYOFFICE"),
                        mime: config.mime,
                        permissions: OC.PERMISSION_READ,
                        iconClass: "icon-onlyoffice-open",
                        actionHandler: OCA.Onlyoffice.FileClick
                    });

                    if (config.def) {
                        fileList.fileActions.setDefault(config.mime, "onlyofficeOpen");
                        if (OCA.Onlyoffice.setting.readOnly)
							OCA.Files.fileActions.registerAction({
								name: 'edit_onlyoffice',
								displayName: t('edit_onlyoffice', 'Open for editing'),
								mime: config.mime,
								actionHandler: OCA.Onlyoffice.FileEditClick,
								permissions: OC.PERMISSION_UPDATE,
								iconClass: 'icon-edit'
							});

                    }

                    if (config.conv) {
                        fileList.fileActions.registerAction({
                            name: "onlyofficeConvert",
                            displayName: t(OCA.Onlyoffice.AppName, "Convert with ONLYOFFICE"),
                            mime: config.mime,
                            permissions: ($("#isPublic").val() ? OC.PERMISSION_UPDATE : OC.PERMISSION_READ),
                            iconClass: "icon-onlyoffice-convert",
                            actionHandler: OCA.Onlyoffice.FileConvertClick
                        });
                        if (OCA.Onlyoffice.setting.readOnly)
							fileList.fileActions.registerAction({
								name: "onlyofficeOpen",
								displayName: t(OCA.Onlyoffice.AppName, "Open for editing in ONLYOFFICE"),
								mime: config.mime,
								permissions: OC.PERMISSION_UPDATE,
								iconClass: "icon-onlyoffice-open",
								actionHandler: OCA.Onlyoffice.FileEditClick
							});
                    }
                });
            }

            OCA.Onlyoffice.GetSettings(register);
        }
    };

    OCA.Onlyoffice.NewFileMenu = {
        attach: function (menu) {
            var fileList = menu.fileList;

            if (fileList.id !== "files" && fileList.id !== "files.public") {
                return;
            }

            menu.addMenuEntry({
                id: "onlyofficeDocx",
                displayName: t(OCA.Onlyoffice.AppName, "Document"),
                templateName: t(OCA.Onlyoffice.AppName, "Document"),
                iconClass: "icon-onlyoffice-new-docx",
                fileType: "docx",
                actionHandler: function (name) {
                    OCA.Onlyoffice.CreateFile(name + ".docx", fileList);
                }
            });

            menu.addMenuEntry({
                id: "onlyofficeXlsx",
                displayName: t(OCA.Onlyoffice.AppName, "Spreadsheet"),
                templateName: t(OCA.Onlyoffice.AppName, "Spreadsheet"),
                iconClass: "icon-onlyoffice-new-xlsx",
                fileType: "xlsx",
                actionHandler: function (name) {
                    OCA.Onlyoffice.CreateFile(name + ".xlsx", fileList);
                }
            });

            menu.addMenuEntry({
                id: "onlyofficePpts",
                displayName: t(OCA.Onlyoffice.AppName, "Presentation"),
                templateName: t(OCA.Onlyoffice.AppName, "Presentation"),
                iconClass: "icon-onlyoffice-new-pptx",
                fileType: "pptx",
                actionHandler: function (name) {
                    OCA.Onlyoffice.CreateFile(name + ".pptx", fileList);
                }
            });
        }
    };

    var getFileExtension = function (fileName) {
        var extension = fileName.substr(fileName.lastIndexOf(".") + 1).toLowerCase();
        return extension;
    }

    OCA.Onlyoffice.openVersion = function (fileId, version) {
        if (OCA.Onlyoffice.frameSelector) {
            $(OCA.Onlyoffice.frameSelector)[0].contentWindow.OCA.Onlyoffice.onRequestHistory(version);
            return;
        }

        OCA.Onlyoffice.OpenEditor(fileId, "", "", version)
    };

    OCA.Onlyoffice.bindVersionClick = function () {
        OCA.Onlyoffice.unbindVersionClick();
        $(document).on("click.onlyoffice-version", "#versionsTabView .downloadVersion", function() {
            var versionNodes = $("#versionsTabView ul.versions>li");
            var versionNode = $(this).closest("#versionsTabView ul.versions>li")[0];

            var href = $(this).attr("href");
            var search = new RegExp("\/versions\/(\\d+)\/\\d+$");
            var result = search.exec(href);
            if (result && result.length > 1) {
                var fileId = result[1];
            }
            if (!fileId) {
                return true;
            }

            var versionNum = versionNodes.length - $.inArray(versionNode, versionNodes);

            OCA.Onlyoffice.openVersion(fileId, versionNum);

            return false;
        });
    };

    OCA.Onlyoffice.unbindVersionClick = function() {
        $(document).off("click.onlyoffice-version", "#versionsTabView .downloadVersion");
    }

    var initPage = function () {
        if ($("#isPublic").val() === "1" && !$("#filestable").length) {
            var fileName = $("#filename").val();
            var extension = getFileExtension(fileName);

            var initSharedButton = function () {
                var formats = OCA.Onlyoffice.setting.formats;

                var config = formats[extension];
                if (!config) {
                    return;
                }

                var button = document.createElement("a");
                button.href = OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/s/" + encodeURIComponent($("#sharingToken").val()));
                button.className = "button";
                button.innerText = t(OCA.Onlyoffice.AppName, "Open in ONLYOFFICE")

                if (!OCA.Onlyoffice.setting.sameTab) {
                    button.target = "_blank";
                }

                $("#preview").append(button);
            };

            OCA.Onlyoffice.GetSettings(initSharedButton);
        } else {
            OC.Plugins.register("OCA.Files.FileList", OCA.Onlyoffice.FileList);
            OC.Plugins.register("OCA.Files.NewFileMenu", OCA.Onlyoffice.NewFileMenu);

            if (OCA.Versions) {
                OCA.Onlyoffice.bindVersionClick();
            }
        }
    };

    $(document).ready(initPage);

})(OCA);

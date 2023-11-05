/**
 *
 * (c) Copyright Ascensio System SIA 2023
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

    OCA.Onlyoffice.setting = OCP.InitialState.loadState(OCA.Onlyoffice.AppName, "settings");
    OCA.Onlyoffice.mobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|BB|PlayBook|IEMobile|Windows Phone|Kindle|Silk|Opera Mini|Macintosh/i.test(navigator.userAgent)
                            && navigator.maxTouchPoints && navigator.maxTouchPoints > 1;

    OCA.Onlyoffice.CreateFile = function (name, fileList, templateId, targetId, open = true) {
        var dir = fileList.getCurrentDirectory();

        if ((!OCA.Onlyoffice.setting.sameTab || OCA.Onlyoffice.mobile || OCA.Onlyoffice.Desktop) && open) {
            $loaderUrl = OCA.Onlyoffice.Desktop ? "" : OC.filePath(OCA.Onlyoffice.AppName, "templates", "loader.html");
            var winEditor = window.open($loaderUrl);
        }

        var createData = {
            name: name,
            dir: dir
        };

        if (templateId) {
            createData.templateId = templateId;
        }

        if (targetId) {
            createData.targetId = targetId;
        }

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
                if (open) {
                    OCA.Onlyoffice.OpenEditor(response.id, dir, response.name, 0, winEditor);

                    OCA.Onlyoffice.context = { fileList: fileList };
                    OCA.Onlyoffice.context.fileName = response.name;
                    OCA.Onlyoffice.context.dir = dir;
                }

                OCP.Toast.success(t(OCA.Onlyoffice.AppName, "File created"));
            }
        );
    };

    OCA.Onlyoffice.OpenEditor = function (fileId, fileDir, fileName, version, winEditor) {
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

        if (version > 0) {
            url += "&version=" + version;
        }

        if (winEditor && winEditor.location) {
            winEditor.location.href = url;
        } else if (!OCA.Onlyoffice.setting.sameTab || OCA.Onlyoffice.mobile || OCA.Onlyoffice.Desktop) {
            winEditor = window.open(url, "_blank");
        } else if ($("#isPublic").val() === "1" && $("#mimetype").val() !== "httpd/unix-directory") {
            location.href = url;
        } else {
            OCA.Onlyoffice.frameSelector = "#onlyofficeFrame";
            var $iframe = $("<iframe id=\"onlyofficeFrame\" nonce=\"" + btoa(OC.requestToken) + "\" scrolling=\"no\" allowfullscreen src=\"" + url + "&inframe=true\" />");
            $("#app-content").append($iframe);

            $("body").addClass("onlyoffice-inline");

            if (OCA.Files.Sidebar) {
                OCA.Files.Sidebar.close();
            }

            var scrollTop = $("#app-content").scrollTop();
            $(OCA.Onlyoffice.frameSelector).css("top", scrollTop);

            OCA.Onlyoffice.folderUrl = location.href;
            window.history.pushState(null, null, url);
        }
    };

    OCA.Onlyoffice.CloseEditor = function () {
        OCA.Onlyoffice.frameSelector = null;

        $("body").removeClass("onlyoffice-inline");

        OCA.Onlyoffice.context = null;

        var url = OCA.Onlyoffice.folderUrl;
        if (!!url) {
            window.history.pushState(null, null, url);
            OCA.Onlyoffice.folderUrl = null;
        }

        OCA.Onlyoffice.bindVersionClick();
    };

    OCA.Onlyoffice.OpenShareDialog = function () {
        if (OCA.Onlyoffice.context) {
            if (!$("#app-sidebar-vue").is(":visible")) {
                OCA.Files.Sidebar.open(OCA.Onlyoffice.context.dir + "/" + OCA.Onlyoffice.context.fileName);
                OCA.Files.Sidebar.setActiveTab("sharing");
            } else {
                OCA.Files.Sidebar.close();
            }
        }
    };

    OCA.Onlyoffice.RefreshVersionsDialog = function () {
        if (OCA.Onlyoffice.context) {
            if ($("#app-sidebar-vue").is(":visible")) {
                OCA.Files.Sidebar.close();
                OCA.Files.Sidebar.open(OCA.Onlyoffice.context.dir + "/" + OCA.Onlyoffice.context.fileName);
                OCA.Files.Sidebar.setActiveTab("versionsTabView");
            }
        }
    };

    OCA.Onlyoffice.FileClick = function (fileName, context) {
        var fileInfoModel = context.fileInfoModel || context.fileList.getModelForFile(fileName);
        var fileId = context.fileId || context.$file[0].dataset.id || fileInfoModel.id;
        var winEditor = !fileInfoModel && !OCA.Onlyoffice.setting.sameTab ? document : null;

        OCA.Onlyoffice.OpenEditor(fileId, context.dir, fileName, 0, winEditor);

        OCA.Onlyoffice.context = context;
        OCA.Onlyoffice.context.fileName = fileName;
    };

    OCA.Onlyoffice.FileConvertClick = function (fileName, context) {
        var fileInfoModel = context.fileInfoModel || context.fileList.getModelForFile(fileName);
        var fileList = context.fileList;

        var convertData = {
            fileId: context.$file[0].dataset.id || fileInfoModel.id
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

                OCP.Toast.success(t(OCA.Onlyoffice.AppName, "File has been converted. Its content might look different."));
            });
    };

    OCA.Onlyoffice.DownloadClick = function (fileName, context) {
        $.get(OC.filePath(OCA.Onlyoffice.AppName, "templates", "downloadPicker.html"), 
            function (tmpl) {
                var dialog = $(tmpl).octemplate({
                    dialog_name: "download-picker",
                    dialog_title: t("onlyoffice", "Download as")
                });

                $(dialog[0].querySelectorAll("p")).text(t(OCA.Onlyoffice.AppName, "Choose a format to convert {fileName}", {fileName: fileName}));

                var extension = OCA.Onlyoffice.getFileExtension(fileName);
                var selectNode = dialog[0].querySelectorAll("select")[0];
                var optionNodeOrigin = selectNode.querySelectorAll("option")[0];

                $(optionNodeOrigin).attr("data-value", extension);
                $(optionNodeOrigin).text(t(OCA.Onlyoffice.AppName, "Origin format"));

                dialog[0].dataset.format = extension;
                selectNode.onchange = function() {
                    dialog[0].dataset.format = $("#onlyoffice-download-select option:selected").attr("data-value");
                }

                OCA.Onlyoffice.setting.formats[extension].saveas.forEach(ext => {
                    var optionNode = optionNodeOrigin.cloneNode(true);

                    $(optionNode).attr("data-value", ext);
                    $(optionNode).text(ext);

                    selectNode.append(optionNode);
                })

                $("body").append(dialog)

                $("#download-picker").ocdialog({
                    closeOnEscape: true,
                    modal: true,
                    buttons: [{
                        text: t("core", "Cancel"),
                        classes: "cancel",
                        click: function() {
                            $(this).ocdialog("close")
                        }
                    }, {
                        text: t("onlyoffice", "Download"),
                        classes: "primary",
                        click: function() {
                            var format = this.dataset.format;
                            var fileId = context.fileInfoModel.id;
                            var downloadLink = OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/downloadas?fileId={fileId}&toExtension={toExtension}",{
                                fileId: fileId,
                                toExtension: format
                            });

                            location.href = downloadLink;
                            $(this).ocdialog("close")
                        }
                    }]
                });
            });
    };

    OCA.Onlyoffice.OpenFormPicker = function (name, filelist) {
        var filterMimes = [
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
        ];

        var buttons = [
            {
                text: t(OCA.Onlyoffice.AppName, "Blank"),
                type: "blank"
            },
            {
                text: t(OCA.Onlyoffice.AppName, "From text document"),
                type: "target",
                defaultButton: true
            }
        ];

        OC.dialogs.filepicker(t(OCA.Onlyoffice.AppName, "Create new Form template"),
            function (filePath, type) {
                var dialogFileList = OC.dialogs.filelist;
                var targetId = 0;

                if (type === "target") {
                    var targetFileName = filePath.split("/").pop();
                    dialogFileList.forEach(item => {
                        if (item.name === targetFileName) {
                            targetId = item.id;
                        }
                    })
                }

                OCA.Onlyoffice.CreateFile(name, filelist, 0, targetId);
            },
            false,
            filterMimes,
            true,
            OC.dialogs.FILEPICKER_TYPE_CUSTOM,
            filelist.getCurrentDirectory(),
            {
                buttons: buttons
            });
    };

    OCA.Onlyoffice.CreateFormClick = function (fileName, context) {
        var fileList = context.fileList;
        var name = fileName.replace(/\.[^.]+$/, ".oform");
        var targetId = context.fileInfoModel.id;

        OCA.Onlyoffice.CreateFile(name, fileList, 0, targetId, false);
    };

    OCA.Onlyoffice.registerAction = function() {
        var formats = OCA.Onlyoffice.setting.formats;

        $.each(formats, function (ext, config) {
            if (!config.mime) {
                return true;
            }
            OCA.Files.fileActions.registerAction({
                name: "onlyofficeOpen",
                displayName: t(OCA.Onlyoffice.AppName, "Open in ONLYOFFICE"),
                mime: config.mime,
                permissions: OC.PERMISSION_READ,
                iconClass: "icon-onlyoffice-open",
                actionHandler: OCA.Onlyoffice.FileClick
            });

            if (config.def) {
                OCA.Files.fileActions.setDefault(config.mime, "onlyofficeOpen");
            }

            if (config.conv) {
                OCA.Files.fileActions.registerAction({
                    name: "onlyofficeConvert",
                    displayName: t(OCA.Onlyoffice.AppName, "Convert with ONLYOFFICE"),
                    mime: config.mime,
                    permissions: ($("#isPublic").val() ? OC.PERMISSION_UPDATE : OC.PERMISSION_READ),
                    iconClass: "icon-onlyoffice-convert",
                    actionHandler: OCA.Onlyoffice.FileConvertClick
                });
            }

            if (config.fillForms) {
                OCA.Files.fileActions.registerAction({
                    name: "onlyofficeFill",
                    displayName: t(OCA.Onlyoffice.AppName, "Fill in form in ONLYOFFICE"),
                    mime: config.mime,
                    permissions: OC.PERMISSION_UPDATE,
                    iconClass: "icon-onlyoffice-fill",
                    actionHandler: OCA.Onlyoffice.FileClick
                });
            }

            if (config.createForm) {
                OCA.Files.fileActions.registerAction({
                    name: "onlyofficeCreateForm",
                    displayName: t(OCA.Onlyoffice.AppName, "Create form"),
                    mime: config.mime,
                    permissions: ($("#isPublic").val() ? OC.PERMISSION_UPDATE : OC.PERMISSION_READ),
                    iconClass: "icon-onlyoffice-create",
                    actionHandler: OCA.Onlyoffice.CreateFormClick
                });
            }

            if (config.saveas && !$("#isPublic").val()) {
                OCA.Files.fileActions.registerAction({
                    name: "onlyofficeDownload",
                    displayName: t(OCA.Onlyoffice.AppName, "Download as"),
                    mime: config.mime,
                    permissions: OC.PERMISSION_READ,
                    iconClass: "icon-onlyoffice-download",
                    actionHandler: OCA.Onlyoffice.DownloadClick
                });
            }
        });
    };

    OCA.Onlyoffice.NewFileMenu = {
        attach: function (menu) {
            var fileList = menu.fileList;

            if (fileList.id !== "files" && fileList.id !== "files.public") {
                return;
            }

            if ($("#isPublic").val() === "1" && $("#mimetype").val() === "httpd/unix-directory") {
                menu.addMenuEntry({
                    id: "onlyofficeDocx",
                    displayName: t(OCA.Onlyoffice.AppName, "New document"),
                    templateName: t(OCA.Onlyoffice.AppName, "New document"),
                    iconClass: "icon-onlyoffice-new-docx",
                    fileType: "docx",
                    actionHandler: function (name) {
                        if (!$("#isPublic").val() && OCA.Onlyoffice.TemplateExist("document")) {
                            OCA.Onlyoffice.OpenTemplatePicker(name, ".docx", "document");
                        } else {
                            OCA.Onlyoffice.CreateFile(name + ".docx", fileList);
                        }
                    }
                });

                menu.addMenuEntry({
                    id: "onlyofficeXlsx",
                    displayName: t(OCA.Onlyoffice.AppName, "New spreadsheet"),
                    templateName: t(OCA.Onlyoffice.AppName, "New spreadsheet"),
                    iconClass: "icon-onlyoffice-new-xlsx",
                    fileType: "xlsx",
                    actionHandler: function (name) {
                        if (!$("#isPublic").val() && OCA.Onlyoffice.TemplateExist("spreadsheet")) {
                            OCA.Onlyoffice.OpenTemplatePicker(name, ".xlsx", "spreadsheet");
                        } else {
                            OCA.Onlyoffice.CreateFile(name + ".xlsx", fileList);
                        }
                    }
                });

                menu.addMenuEntry({
                    id: "onlyofficePpts",
                    displayName: t(OCA.Onlyoffice.AppName, "New presentation"),
                    templateName: t(OCA.Onlyoffice.AppName, "New presentation"),
                    iconClass: "icon-onlyoffice-new-pptx",
                    fileType: "pptx",
                    actionHandler: function (name) {
                        if (!$("#isPublic").val() && OCA.Onlyoffice.TemplateExist("presentation")) {
                            OCA.Onlyoffice.OpenTemplatePicker(name, ".pptx", "presentation");
                        } else {
                            OCA.Onlyoffice.CreateFile(name + ".pptx", fileList);
                        }
                    }
                });

                if (OCA.Onlyoffice.GetTemplates) {
                    OCA.Onlyoffice.GetTemplates();
                }
            }

            menu.addMenuEntry({
                id: "onlyofficeDocxf",
                displayName: t(OCA.Onlyoffice.AppName, "New form template"),
                templateName: t(OCA.Onlyoffice.AppName, "New form template"),
                iconClass: "icon-onlyoffice-new-docxf",
                fileType: "docxf",
                actionHandler: function (name) {
                    OCA.Onlyoffice.OpenFormPicker(name + ".docxf", fileList);
                }
            });
        }
    };

    OCA.Onlyoffice.TabView = {
        attach(fileList) {
            if (OCA.Onlyoffice.SharingTabView) {
                fileList.registerTabView(new OCA.Onlyoffice.SharingTabView())
            }
        }
    }

    OCA.Onlyoffice.getFileExtension = function (fileName) {
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
            var ext = $(this).attr("download").split(".").pop();
            if (!OCA.Onlyoffice.setting.formats[ext]
                || !OCA.Onlyoffice.setting.formats[ext].def) {
                return true;
            }

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
        if ($("#isPublic").val() === "1" && $("#mimetype").val() !== "httpd/unix-directory") {
            //file by shared link
            var fileName = $("#filename").val();
            var extension = OCA.Onlyoffice.getFileExtension(fileName);

            var formats = OCA.Onlyoffice.setting.formats;

            var config = formats[extension];
            if (!config) {
                return;
            }

            var editorUrl = OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/s/" + encodeURIComponent($("#sharingToken").val()));

            if (_oc_appswebroots.richdocuments
                || _oc_appswebroots.files_pdfviewer && extension === "pdf"
                || _oc_appswebroots.text && extension === "txt") {

                var button = document.createElement("a");
                button.href = editorUrl;
                button.className = "onlyoffice-public-open button";
                button.innerText = t(OCA.Onlyoffice.AppName, "Open in ONLYOFFICE")

                if (!OCA.Onlyoffice.setting.sameTab) {
                    button.target = "_blank";
                }

                $("#preview").prepend(button);
            } else {
                OCA.Onlyoffice.frameSelector = "#onlyofficeFrame";
                var $iframe = $("<iframe id=\"onlyofficeFrame\" nonce=\"" + btoa(OC.requestToken) + "\" scrolling=\"no\" allowfullscreen src=\"" + editorUrl + "?inframe=true\" />");
                $("#app-content").append($iframe);
                $("body").addClass("onlyoffice-inline");
            }
        } else {
            OC.Plugins.register("OCA.Files.NewFileMenu", OCA.Onlyoffice.NewFileMenu);

            OC.Plugins.register("OCA.Files.FileList", OCA.Onlyoffice.TabView);

            OCA.Onlyoffice.registerAction();

            OCA.Onlyoffice.bindVersionClick();
        }
    };

    initPage();

})(OCA);

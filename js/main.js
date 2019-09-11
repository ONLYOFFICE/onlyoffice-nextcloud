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

(function (OCA) {

    OCA.Onlyoffice = _.extend({
            AppName: "onlyoffice"
        }, OCA.Onlyoffice);

    OCA.Onlyoffice.setting = {};

    OCA.Onlyoffice.CreateFile = function (name, fileList) {
        var dir = fileList.getCurrentDirectory();

        if (!OCA.Onlyoffice.setting.sameTab || OCA.Onlyoffice.Desktop) {
            var winEditor = window.open("");
            if (winEditor) {
                winEditor.document.write(t(OCA.Onlyoffice.AppName, "Loading, please wait."));
                winEditor.document.close();
            }
        }

        var createData = {
            name: name,
            dir: dir
        };

        if ($("#isPublic").val()) {
            createData.token = encodeURIComponent($("#sharingToken").val());
        }

        $.post(OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/new"),
            createData,
            function onSuccess(response) {
                if (response.error) {
                    if (winEditor) {
                        winEditor.close();
                    }
                    OC.Notification.show(response.error, {
                        type: "error",
                        timeout: 3
                    });
                    return;
                }

                fileList.add(response, { animate: true });
                OCA.Onlyoffice.OpenEditor(response.id, dir, response.name, winEditor);

                OC.Notification.show(t(OCA.Onlyoffice.AppName, "File created"), {
                    timeout: 3
                });
            }
        );
    };

    OCA.Onlyoffice.OpenEditor = function (fileId, fileDir, fileName, winEditor) {
        var filePath = fileDir.replace(new RegExp("\/$"), "") + "/" + fileName;
        var url = OC.generateUrl("/apps/" + OCA.Onlyoffice.AppName + "/{fileId}?filePath={filePath}",
            {
                fileId: fileId,
                filePath: filePath
            });

        if ($("#isPublic").val()) {
            url = OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/s/{token}?fileId={fileId}",
                {
                    token: encodeURIComponent($("#sharingToken").val()),
                    fileId: fileId
                });
        }

        if (winEditor && winEditor.location) {
            winEditor.location.href = url;
        } else if (!OCA.Onlyoffice.setting.sameTab || OCA.Onlyoffice.Desktop) {
            winEditor = window.open(url, "_blank");
        } else {
            location.href = url;
        }
    };

    OCA.Onlyoffice.FileClick = function (fileName, context) {
        var fileInfoModel = context.fileInfoModel || context.fileList.getModelForFile(fileName);
        OCA.Onlyoffice.OpenEditor(fileInfoModel.id, context.dir, fileName);
    };

    OCA.Onlyoffice.FileConvertClick = function (fileName, context) {
        var fileInfoModel = context.fileInfoModel || context.fileList.getModelForFile(fileName);
        var fileList = context.fileList;

        var convertData = {
            fileId: fileInfoModel.id
        };

        if ($("#isPublic").val()) {
            convertData.token = encodeURIComponent($("#sharingToken").val());
        }

        $.post(OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/convert"),
            convertData,
            function onSuccess(response) {
                if (response.error) {
                    OC.Notification.show(response.error, {
                        type: "error",
                        timeout: 3
                    });
                    return;
                }

                if (response.parentId == fileList.dirInfo.id) {
                    fileList.add(response, { animate: true });
                }

                OC.Notification.show(t(OCA.Onlyoffice.AppName, "File created"), {
                    timeout: 3
                });
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

            var register = function() {
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

    var initPage = function () {
        if ($("#isPublic").val() === "1" && !$("#filestable").length) {
            var fileName = $("#filename").val();
            var extension = getFileExtension(fileName);

            var initSharedButton = function() {
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
        }
    };

    $(document).ready(initPage)

})(OCA);

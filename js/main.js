/**
 *
 * (c) Copyright Ascensio System Limited 2010-2018
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
 * You can contact Ascensio System SIA at 17-2 Elijas street, Riga, Latvia, EU, LV-1021.
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

        $.post(OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/new"),
            {
                name: name,
                dir: dir
            },
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
                OCA.Onlyoffice.OpenEditor(response.id, winEditor);

                OC.Notification.show(t(OCA.Onlyoffice.AppName, "File created"), {
                    timeout: 3
                });
            }
        );
    };

    OCA.Onlyoffice.OpenEditor = function (fileId, winEditor) {
        var url = OC.generateUrl("/apps/" + OCA.Onlyoffice.AppName + "/{fileId}",
            {
                fileId: fileId
            });

        if ($("#isPublic").val()) {
            url = OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/s/" + encodeURIComponent($("#sharingToken").val())) + "?fileId=" + fileId;
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
        OCA.Onlyoffice.OpenEditor(fileInfoModel.id);
    };

    OCA.Onlyoffice.FileConvertClick = function (fileName, context) {
        var fileInfoModel = context.fileInfoModel || context.fileList.getModelForFile(fileName);
        var fileList = context.fileList;

        $.post(OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/convert"),
            {
                fileId: fileInfoModel.id
            },
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

                $.each(formats, function (ext, attr) {
                    if (!attr.mime) {
                        return true;
                    }
                    fileList.fileActions.registerAction({
                        name: "onlyofficeOpen",
                        displayName: t(OCA.Onlyoffice.AppName, "Open in ONLYOFFICE"),
                        mime: attr.mime,
                        permissions: OC.PERMISSION_READ,
                        iconClass: "icon-onlyoffice-open",
                        actionHandler: OCA.Onlyoffice.FileClick
                    });

                    if (attr.def) {
                        fileList.fileActions.setDefault(attr.mime, "onlyofficeOpen");
                    }

                    if (!$("#isPublic").val() && attr.conv) {
                        fileList.fileActions.registerAction({
                            name: "onlyofficeConvert",
                            displayName: t(OCA.Onlyoffice.AppName, "Convert with ONLYOFFICE"),
                            mime: attr.mime,
                            permissions: OC.PERMISSION_READ,
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

            if (fileList.id !== "files") {
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

    var initPage = function(){
        if ($("#isPublic").val() && !$("#dir").val().length) {
            var fileName = $("#filename").val();
            var extension = fileName.substr(fileName.lastIndexOf(".") + 1).toLowerCase();

            var initSharedButton = function() {
                var formats = OCA.Onlyoffice.setting.formats;

                var conf = formats[extension];
                if (conf) {
                    var button = document.createElement("a");
                    button.href = OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/s/" + encodeURIComponent($("#sharingToken").val()));
                    button.className = "button";
                    button.innerText = t(OCA.Onlyoffice.AppName, "Open in ONLYOFFICE")

                    if (!OCA.Onlyoffice.setting.sameTab) {
                        button.target = "_blank";
                    }

                    $("#preview").append(button);
                }
            };

            OCA.Onlyoffice.GetSettings(initSharedButton);
        } else {
            OC.Plugins.register("OCA.Files.FileList", OCA.Onlyoffice.FileList);
            OC.Plugins.register("OCA.Files.NewFileMenu", OCA.Onlyoffice.NewFileMenu);
        }
    };

    $(document).ready(initPage)

})(OCA);

/**
 *
 * (c) Copyright Ascensio System Limited 2010-2017
 *
 * This program is freeware. You can redistribute it and/or modify it under the terms of the GNU 
 * General Public License (GPL) version 3 as published by the Free Software Foundation (https://www.gnu.org/copyleft/gpl.html). 
 * In accordance with Section 7(a) of the GNU GPL its Section 15 shall be amended to the effect that 
 * Ascensio System SIA expressly excludes the warranty of non-infringement of any third-party rights.
 *
 * THIS PROGRAM IS DISTRIBUTED WITHOUT ANY WARRANTY; WITHOUT EVEN THE IMPLIED WARRANTY OF MERCHANTABILITY OR
 * FITNESS FOR A PARTICULAR PURPOSE. For more details, see GNU GPL at https://www.gnu.org/copyleft/gpl.html
 *
 * You can contact Ascensio System SIA by email at sales@onlyoffice.com
 *
 * The interactive user interfaces in modified source and object code versions of ONLYOFFICE must display 
 * Appropriate Legal Notices, as required under Section 5 of the GNU GPL version 3.
 *
 * Pursuant to Section 7 § 3(b) of the GNU GPL you must retain the original ONLYOFFICE logo which contains 
 * relevant author attributions when distributing the software. If the display of the logo in its graphic 
 * form is not reasonably feasible for technical reasons, you must include the words "Powered by ONLYOFFICE" 
 * in every copy of the program you distribute. 
 * Pursuant to Section 7 § 3(e) we decline to grant you any rights under trademark law for use of our trademarks.
 *
*/

(function (OCA) {

    OCA.Onlyoffice = _.extend({}, OCA.Onlyoffice);
    if (!OCA.Onlyoffice.AppName) {
        OCA.Onlyoffice = {
            AppName: "onlyoffice"
        };
    }

    OCA.Onlyoffice.CreateFile = function (name, fileList) {
        var dir = fileList.getCurrentDirectory();

        var winEditor = window.open("");
        if (winEditor) {
            winEditor.document.write(t(OCA.Onlyoffice.AppName, "Loading, please wait."));
            winEditor.document.close();
        }

        $.post(OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/new"),
            {
                name: name,
                dir: dir
            },
            function onSuccess(response) {
                if (response.error) {
                    winEditor.close();
                    var row = OC.Notification.show(response.error);
                    setTimeout(function () {
                        OC.Notification.hide(row);
                    }, 3000);
                    return;
                }

                fileList.add(response, { animate: true });
                OCA.Onlyoffice.OpenEditor(response.id, winEditor);

                var row = OC.Notification.show(t(OCA.Onlyoffice.AppName, "File created"));
                setTimeout(function () {
                    OC.Notification.hide(row);
                }, 3000);
            }
        );
    };

    OCA.Onlyoffice.OpenEditor = function (fileId, winEditor) {
        var url = OC.generateUrl("/apps/" + OCA.Onlyoffice.AppName + "/{fileId}",
            {
                fileId: fileId
            });

        if (winEditor && winEditor.location) {
            winEditor.location.href = url;
        } else {
            winEditor = window.open(url, "_blank");
        }
    };

    OCA.Onlyoffice.FileClick = function (context, attr) {
        var fileInfoModel = context.fileInfoModel;
        var fileList = context.fileList;
        if (!attr.conv) {
            OCA.Onlyoffice.OpenEditor(fileInfoModel.id);
            return;
        }

        OC.dialogs.confirm(t(OCA.Onlyoffice.AppName, "The document file you open will be converted to the Office Open XML format for faster viewing and editing."),
            t(OCA.Onlyoffice.AppName, "Convert and open document"),
            function (convert) {
                if (!convert) {
                    return;
                }

                $.post(OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/convert"),
                    {
                        fileId: fileInfoModel.id
                    },
                    function onSuccess(response) {
                        if (response.error) {
                            var row = OC.Notification.show(response.error);
                            setTimeout(function () {
                                OC.Notification.hide(row);
                            }, 3000);
                            return;
                        }

                        if (response.parentId == fileList.dirInfo.id) {
                            fileList.add(response, { animate: true });
                        }

                        var row = OC.Notification.show(t(OCA.Onlyoffice.AppName, "File created"));
                        setTimeout(function () {
                            OC.Notification.hide(row);
                        }, 3000);
                    });
            });
    };

    OCA.Onlyoffice.FileList = {
        attach: function (fileList) {
            if (fileList.id == "trashbin") {
                return;
            }

            $.get(OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/settings"),
                function onSuccess(mimes) {

                    OCA.Onlyoffice.mimes = mimes;
                    $.each(mimes, function (ext, attr) {
                        fileList.fileActions.registerAction({
                            name: "onlyofficeOpen",
                            displayName: t(OCA.Onlyoffice.AppName, "Open in ONLYOFFICE"),
                            mime: attr.mime,
                            permissions: OC.PERMISSION_READ,
                            icon: function () {
                                return OC.imagePath(OCA.Onlyoffice.AppName, "btn-edit");
                            },
                            actionHandler: function (fileName, context) {
                                OCA.Onlyoffice.FileClick(context, attr);
                            }
                        });
                    });
                }
            );
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
                iconClass: "onlyoffice-new-docx",
                fileType: "docx",
                actionHandler: function (name) {
                    OCA.Onlyoffice.CreateFile(name + ".docx", fileList);
                }
            });

            menu.addMenuEntry({
                id: "onlyofficeXlsx",
                displayName: t(OCA.Onlyoffice.AppName, "Spreadsheet"),
                iconClass: "onlyoffice-new-xlsx",
                fileType: "xlsx",
                actionHandler: function (name) {
                    OCA.Onlyoffice.CreateFile(name + ".xlsx", fileList);
                }
            });

            menu.addMenuEntry({
                id: "onlyofficePpts",
                displayName: t(OCA.Onlyoffice.AppName, "Presentation"),
                iconClass: "onlyoffice-new-pptx",
                fileType: "pptx",
                actionHandler: function (name) {
                    OCA.Onlyoffice.CreateFile(name + ".pptx", fileList);
                }
            });
        }
    };
})(OCA);

OC.Plugins.register("OCA.Files.FileList", OCA.Onlyoffice.FileList);
OC.Plugins.register("OCA.Files.NewFileMenu", OCA.Onlyoffice.NewFileMenu);

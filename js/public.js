/**
 *
 * (c) Copyright Ascensio System Limited 2010-2018
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
 * Pursuant to Section 7  3(b) of the GNU GPL you must retain the original ONLYOFFICE logo which contains
 * relevant author attributions when distributing the software. If the display of the logo in its graphic
 * form is not reasonably feasible for technical reasons, you must include the words "Powered by ONLYOFFICE"
 * in every copy of the program you distribute.
 * Pursuant to Section 7  3(e) we decline to grant you any rights under trademark law for use of our trademarks.
 *
 */

(function ($, OCA) {

    OCA.Onlyoffice = _.extend({}, OCA.Onlyoffice);
    if (!OCA.Onlyoffice.AppName) {
        OCA.Onlyoffice = {
            AppName: "onlyoffice"
        };
    }

    OCA.Onlyoffice.setting = {};

    OCA.Onlyoffice.InitPublic = function () {

        if (!!$("#dir").val().length) {
            return;
        }

        var fileName = $("#filename").val();
        var extension = fileName.substr(fileName.lastIndexOf('.') + 1);

        $.get(OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/settings"),
            function onSuccess(settings) {
                OCA.Onlyoffice.setting = settings;
                var mimes = OCA.Onlyoffice.setting.formats;

                OCA.Onlyoffice.mimes = mimes;
                var conf = OCA.Onlyoffice.mimes[extension];
                if (conf && conf.edit) {

                    var button = document.createElement("a");
                    button.href = OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/s/" + $('#sharingToken').val());
                    button.className = "button";
                    button.innerText = t(OCA.Onlyoffice.AppName, "Open in ONLYOFFICE")

                    if (!OCA.Onlyoffice.setting.sameTab) {
                        button.target = "_blank";
                    }

                    $("#preview").append(button);
                }
            }
        );
    };

    $(document).ready(OCA.Onlyoffice.InitPublic);

})(jQuery, OCA);
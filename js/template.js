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

 (function ($, OC) {

    OCA.Onlyoffice = _.extend({
        AppName: "onlyoffice"
    }, OCA.Onlyoffice);

    OCA.Onlyoffice.AddTemplate = function (file, callback) {
        var data = new FormData();
        data.append("file", file);

        $.ajax({
            method: "POST",
            url: OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/template"),
            data: data,
            processData: false,
            contentType: false,
            success: function onSuccess(response) {
                if (response.error) {
                    callback(null, response.error)
                    return;
                }

                callback(response, null);
            }
        });
    };

    OCA.Onlyoffice.DeleteTemplate = function (templateId, callback) {
        $.ajax({
            method: "DELETE",
            url: OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/template?templateId={templateId}",
            {
                templateId: templateId
            }),
            success: function onSuccess(response) {
                if (response) {
                    callback(response);
                }
            }
        });
    };

    OCA.Onlyoffice.AttachItemTemplate = function (template) {
        $.get(OC.filePath(OCA.Onlyoffice.AppName, "templates", "templateItem.html"),
        function (item) {
            var item = $(item)

            item.attr("data-id", template.id);
            item.children("img").attr("src", "/core/img/filetypes/x-office-" + template.type + ".svg");
            item.children("p").text(template.name);

            $(".onlyoffice-template-container").append(item);
        });
    };

})(jQuery, OC);
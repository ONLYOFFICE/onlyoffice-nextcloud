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
    if (OCA.Onlyoffice) {
        return;
    }

    OCA.Onlyoffice = {
            AppName: "onlyoffice",
            frameSelector: null,
            setting: {},
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

    var OnlyofficeViewerVue = {
        name: "OnlyofficeViewerVue",
        render: function (createElement) {
            var self = this;

            return createElement("iframe", {
                attrs: {
                    id: "onlyofficeViewerFrame",
                    scrolling: "no",
                    src: self.url + "&inframe=true",
                },
                on: {
                    load: function () {
                        self.doneLoading();
                    },
                },
            })
        },
        props: {
            filename: {
                type: String,
                default: null
            },
            fileid: {
                type: Number,
                default: null
            }
        },
        data: function () {
            return {
                url: OC.generateUrl("/apps/" + OCA.Onlyoffice.AppName + "/{fileId}?filePath={filePath}",
                    {
                        fileId: this.fileid,
                        filePath: this.filename
                    })
            }
        }
    };

    var initPage = function () {
        if (OCA.Viewer) {
            OCA.Onlyoffice.frameSelector = "#onlyofficeViewerFrame";

            OCA.Onlyoffice.GetSettings(function () {

                var mimes = $.map(OCA.Onlyoffice.setting.formats, function (format) {
                    if (format.def) {
                        return format.mime;
                    }
                });

                OCA.Viewer.registerHandler({
                    id: OCA.Onlyoffice.AppName,
                    group: null,
                    mimes: mimes,
                    component: OnlyofficeViewerVue
                })
            });
        }
    };

    $(document).ready(function() {
        initPage();
    });

})(OCA);

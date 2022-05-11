/**
 *
 * (c) Copyright Ascensio System SIA 2022
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
        AppName: "onlyoffice",
    }, OCA.Onlyoffice);

    OCA.Onlyoffice.SharingTabView = OCA.Files.DetailTabView.extend({
        id: "onlyofficeSharingTabView",
        className: "tab onlyofficeSharingTabView",

        template: null,

        initialize() {
            OCA.Files.DetailTabView.prototype.initialize.apply(this, arguments);
        },

        getLabel() {
            return t(OCA.Onlyoffice.AppName, "Onlyoffice sharing")
        },

        getIcon() {
            return "icon-onlyoffice-sharing"
        },

        render() {
            var that = this;

            $("<div>").html(t(OCA.Onlyoffice.AppName, "Share files with ONLYOFFICE")).prependTo(that.$el);
            $.get(OC.filePath(OCA.Onlyoffice.AppName, "templates", "share.html"), 
                function (tmpl) {
                    that.template = $(tmpl);

                    that.$el.append(that.template);
                });
        },

        setFileInfo(fileInfo) {
            if(fileInfo) {
                this.render();
            }
        },

        canDisplay: function(fileInfo) {
            if (fileInfo.isDirectory()) {
                return false;
            };

            return true;
        },
    });

})(jQuery, OC);
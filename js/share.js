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

    OCA.Onlyoffice.Permissions = {
        Review: 1,
        Comment: 2,
        FillForms: 4,
        ModifyFilter: 8
    };

    OCA.Onlyoffice.SharingTabView = OCA.Files.DetailTabView.extend({
        id: "onlyofficeSharingTabView",
        className: "tab onlyofficeSharingTabView",

        templateItem: null,
        permissionsMenu: null,
        collection: null,
        format: null,

        events: {
            'click #onlyoffice-share-action': '_onClickPermissionMenu',
        },

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

            this._getTemplate(() => {
                var container = this.$el.find(".onlyoffice-share-container");

                container.children().remove();
                this.collection.forEach(extra => {
                    var itemNode = that.templateItem.clone();
                    var descNode = itemNode.find("span");
                    var avatar = itemNode.find("img");

                    avatar[0].src = "/index.php/avatar/" + extra["shareWith"] + "/32?v=0";
                    itemNode[0].id = extra.share_id;
                    descNode[0].innerText = extra.shareWithName;
                    
                    container.append(itemNode);
                });
            });
        },

        setFileInfo(fileInfo) {
            if(fileInfo) {
                OCA.Onlyoffice.GetShares(fileInfo.id, (shares) => {
                    this.collection = shares;

                    var ext = fileInfo.attributes.name.split(".").pop();
                    this.format = OCA.Onlyoffice.setting.formats[ext];

                    this.render();
                });
            }
        },

        canDisplay: function(fileInfo) {
            if (fileInfo.isDirectory()) {
                return false;
            };

            return true;
        },

        _getTemplate: function(callback) {
            if (this.templateItem) {
                callback();
                return;
            }

            var that = this;
            $.get(OC.filePath(OCA.Onlyoffice.AppName, "templates", "share.html"), 
                function (tmpl) {
                    that.templateItem = $(tmpl);

                    $("<ul>", {class: "onlyoffice-share-container"}).appendTo(that.$el)
                    $("<div>").html(t(OCA.Onlyoffice.AppName, "Share files with ONLYOFFICE")).prependTo(that.$el);

                    that.$el.append(that.template);

                    callback();
                });
        },

        _onClickPermissionMenu: function(e) {
            if (!this.permissionsMenu) {
                this.permissionsMenu = this._permissionMenu();
            }

            var shareNode = $(e.target).closest(".onlyoffice-share-item")[0];
            var shareId = shareNode.id;

            
            if (this.permissionsMenu.isOpen()) {
                var previousId = this.permissionsMenu.getTargetId();
                this.permissionsMenu.close();

                if (previousId == shareId) return;
            }

            var extra = this.collection.find(item => item.share_id == shareId);

            var review = false;
            var comment = false;
            var fillForms = false;
            var modifyFilter = false;
            if ("review" in this.format) {
                review = (OCA.Onlyoffice.Permissions.Review & extra["permissions"]) === OCA.Onlyoffice.Permissions.Review;
                this.permissionsMenu.appendItem(review, OCA.Onlyoffice.Permissions.Review, t(OCA.Onlyoffice.AppName, "Review"));
            }
            if ("comment" in this.format && !review) {
                comment = (OCA.Onlyoffice.Permissions.Comment & extra["permissions"]) === OCA.Onlyoffice.Permissions.Comment;
                this.permissionsMenu.appendItem(comment, OCA.Onlyoffice.Permissions.Comment, t(OCA.Onlyoffice.AppName, "Comment"));
            }
            if ("fillForms" in this.format && !review) {
                fillForms = (OCA.Onlyoffice.Permissions.FillForms & extra["permissions"]) === OCA.Onlyoffice.Permissions.FillForms;
                this.permissionsMenu.appendItem(fillForms, OCA.Onlyoffice.Permissions.FillForms, t(OCA.Onlyoffice.AppName, "FillForms"));
            }
            if ("modifyFilter" in this.format) {
                modifyFilter = (OCA.Onlyoffice.Permissions.ModifyFilter & extra["permissions"]) === OCA.Onlyoffice.Permissions.ModifyFilter;
                this.permissionsMenu.appendItem(modifyFilter, OCA.Onlyoffice.Permissions.ModifyFilter, t(OCA.Onlyoffice.AppName, "ModifyFilter"));
            }

            this.permissionsMenu.open(extra.share_id, $(e.target).position());
        },

        _permissionMenu: function() {
            var popup = $("<div>", {
                class: "popovermenu onlyoffice-share-popup"
            }).append($("<ul>"), {
                id: -1
            });

            this.$el.append(popup);

            return {
                isOpen: function() {
                    return popup.is(":visible");
                },

                open: function(id, position) {
                    if (position) {
                        popup.css({top: position.top});
                    }

                    this.setTargetId(id);
                    popup.show();
                },

                close: function() {
                    var items = popup.find("li");
                    if (items) {
                        items.remove();
                    }

                    this.setTargetId(-1);
                    popup.hide();
                },

                appendItem: function(checked, checkboxId, name) {
                    var item = $("<li>").append($("<span>", {
                        class: "onlyoffice-share-action"
                    }).append($("<input>", {
                        id: checkboxId,
                        type: "checkbox",
                        class: "checkbox action-checkbox__checkbox focusable",
                        checked: checked
                    })).append($("<label>", {
                        for: checkboxId,
                        text: name,
                        class: "onlyoffice-share-label"
                    })));

                    popup.find("ul").append(item);
                },

                setTargetId: function(id) {
                    popup.find("ul").attr("id", id);
                },

                getTargetId: function() {
                    return Number(popup.find("ul").attr("id"));
                },
            }
        }
    });

    OCA.Onlyoffice.GetShares = function(fileId, callback) {
        $.ajax({
            url: OC.linkToOCS("apps/" + OCA.Onlyoffice.AppName + "/api/v1/shares", 2) + fileId + "?format=json",
            success: function onSuccess(response) {
                callback(response.ocs.data);
            }
        })
    }

})(jQuery, OC);
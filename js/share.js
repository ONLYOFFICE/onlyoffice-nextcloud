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
            "click #onlyoffice-share-action": "_onClickPermissionMenu",
            "change .onlyoffice-share-action input": "_onClickSetPermissions"
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

        _onClickSetPermissions: function(e) {
            var permissionValues = this.permissionsMenu.getValues();
            var shareId = this.permissionsMenu.getTargetId();
            var extra = this.collection.find(item => item.share_id == shareId);

            var permissions = 0;
            permissionValues.forEach(permission => {
                if (permission.value) {
                    permissions |= permission.id;
                }
            });

            OCA.Onlyoffice.SetShares(extra.id, shareId, permissions, (extra) => {
                this.collection.forEach(item => {
                    if (item.share_id == extra.share_id) {
                        item.id = extra.id;
                        item.permissions = extra.permissions;
                    }
                });

                var attributes = this._getPermissionAttributes(extra["permissions"]);

                this.permissionsMenu.refresh(attributes);
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

            var attributes = this._getPermissionAttributes(extra["permissions"]);

            this.permissionsMenu.open(extra.share_id, attributes, $(e.target).position());
        },

        _getPermissionAttributes: function(permissions) {
            var attributes = [];

            var review = false;
            var comment = false;
            var fillForms = false;
            var modifyFilter = false;
            if ("review" in this.format) {
                review = (OCA.Onlyoffice.Permissions.Review & permissions) === OCA.Onlyoffice.Permissions.Review;
                attributes.push({
                    checked: review,
                    inputAttribute: OCA.Onlyoffice.Permissions.Review,
                    label: t(OCA.Onlyoffice.AppName, "Review")
                });
            }
            if ("comment" in this.format && !review) {
                comment = (OCA.Onlyoffice.Permissions.Comment & permissions) === OCA.Onlyoffice.Permissions.Comment;
                attributes.push({
                    checked: comment,
                    inputAttribute: OCA.Onlyoffice.Permissions.Comment,
                    label: t(OCA.Onlyoffice.AppName, "Comment")
                });
            }
            if ("fillForms" in this.format && !review) {
                fillForms = (OCA.Onlyoffice.Permissions.FillForms & permissions) === OCA.Onlyoffice.Permissions.FillForms;
                attributes.push({
                    checked: fillForms,
                    inputAttribute: OCA.Onlyoffice.Permissions.FillForms,
                    label: t(OCA.Onlyoffice.AppName, "FillForms")
                });
            }
            if ("modifyFilter" in this.format) {
                modifyFilter = (OCA.Onlyoffice.Permissions.ModifyFilter & permissions) === OCA.Onlyoffice.Permissions.ModifyFilter;
                attributes.push({
                    checked: modifyFilter,
                    inputAttribute: OCA.Onlyoffice.Permissions.ModifyFilter,
                    label: t(OCA.Onlyoffice.AppName, "ModifyFilter")
                });
            }

            return attributes;
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

                open: function(id, attributes, position) {
                    if (position) {
                        popup.css({top: position.top});
                    }

                    attributes.forEach(attr => {
                        this.appendItem(attr.checked, attr.inputAttribute, attr.label);
                    });

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

                refresh: function(attributes) {
                    var items = popup.find("li");
                    if (items) {
                        items.remove();
                    }

                    attributes.forEach(attr => {
                        this.appendItem(attr.checked, attr.inputAttribute, attr.label);
                    });
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

                getValues: function() {
                    var values = [];

                    var items = popup.find("input");
                    for (var i = 0; i < items.length; i++) {
                        values.push({
                            id: items[i].id,
                            value: items[i].checked
                        });
                    }

                    return values;
                },

                setTargetId: function(id) {
                    popup.find("ul").attr("id", id);
                },

                getTargetId: function() {
                    return popup.find("ul").attr("id");
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

    OCA.Onlyoffice.SetShares = function(id, shareId, permissions, callback) {
        var data = {
            extraId: id,
            shareId: shareId,
            permissions: permissions
        }

        $.ajax({
            method: "POST",
            url: OC.linkToOCS("apps/" + OCA.Onlyoffice.AppName + "/api/v1", 2) + "shares?format=json",
            data: data,
            success: function onSuccess(response) {
                callback(response.ocs.data);
            }
        })
    }

})(jQuery, OC);
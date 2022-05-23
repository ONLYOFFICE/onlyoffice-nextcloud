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

    var tabcontext = null;

    OCA.Onlyoffice.SharingTabView = OCA.Files.DetailTabView.extend({
        id: "onlyofficeSharingTabView",
        className: "tab onlyofficeSharingTabView",

        fileInfo: null,
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
            tabcontext = this;
        },

        getLabel() {
            return t(OCA.Onlyoffice.AppName, "Onlyoffice sharing")
        },

        getIcon() {
            return "icon-onlyoffice-sharing"
        },

        render() {
            var self = this;

            this._getTemplate(() => {
                this.collection.forEach(extra => {
                    var itemNode = self.templateItem.clone();
                    var descNode = itemNode.find("span");
                    var avatar = itemNode.find("img");

                    avatar[0].src = "/index.php/avatar/" + extra["shareWith"] + "/32?v=0";
                    itemNode[0].id = extra.share_id;
                    descNode[0].innerText = extra.shareWithName;

                    self._getContainer().append(itemNode);
                });
            });
        },

        setFileInfo(fileInfo) {
            if(fileInfo) {
                this.fileInfo = fileInfo;

                this._getContainer().children().remove();

                OCA.Onlyoffice.GetShares(this.fileInfo.id, (shares) => {
                    this.collection = shares;

                    this.render();
                });
            }
        },

        canDisplay: function(fileInfo) {
            var canDisplay = false;

            if (!fileInfo.isDirectory()) {
                var ext = fileInfo.name.split(".").pop();
                var format = OCA.Onlyoffice.setting.formats[ext];
                if (format && (format["review"]
                    || format["comment"]
                    || format["fillForms"]
                    || format["modifyFilter"])) {
                    canDisplay = true;
                    tabcontext.format = format;

                    if ($("#sharing").hasClass("active")
                        && tabcontext.fileInfo
                        && tabcontext.fileInfo.id == fileInfo.id) {
                        this.update(fileInfo);
                    }
                }
            };

            return canDisplay;
        },

        _getContainer: function() {
            return this.$el.find(".onlyoffice-share-container");
        },

        _getTemplate: function(callback) {
            if (this.templateItem) {
                callback();
                return;
            }

            var self = this;
            $.get(OC.filePath(OCA.Onlyoffice.AppName, "templates", "share.html"), 
                function (tmpl) {
                    self.templateItem = $(tmpl);

                    $("<ul>", {class: "onlyoffice-share-container"}).appendTo(self.$el)
                    $("<div>").html(t(OCA.Onlyoffice.AppName, "Share files with ONLYOFFICE")).prependTo(self.$el);

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

            this.permissionsMenu.block(true);
            OCA.Onlyoffice.SetShares(extra.id, shareId, permissions, (extra) => {
                this.collection.forEach(item => {
                    if (item.share_id == extra.share_id) {
                        item.id = extra.id;
                        item.permissions = extra.permissions;
                    }
                });

                var attributes = this._getPermissionAttributes(extra);

                this.permissionsMenu.refresh(attributes);
                this.permissionsMenu.block(false);
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

            var attributes = this._getPermissionAttributes(extra);

            this.permissionsMenu.open(extra.share_id, attributes, $(e.target).position());
        },

        _getPermissionAttributes: function(extra) {
            var attributes = [];

            var review = false;
            var comment = false;
            var fillForms = false;
            var modifyFilter = false;

            var read = (OC.PERMISSION_READ & extra["basePermissions"]) === OC.PERMISSION_READ;
            var update = (OC.PERMISSION_UPDATE & extra["basePermissions"]) === OC.PERMISSION_UPDATE;

            if (!update && read) {
                if (this.format["review"]) {
                    review = (OCA.Onlyoffice.Permissions.Review & extra["permissions"]) === OCA.Onlyoffice.Permissions.Review;
                    attributes.push({
                        checked: review,
                        inputAttribute: OCA.Onlyoffice.Permissions.Review,
                        label: t(OCA.Onlyoffice.AppName, "Review")
                    });
                }
                if (this.format["comment"] && !review) {
                    comment = (OCA.Onlyoffice.Permissions.Comment & extra["permissions"]) === OCA.Onlyoffice.Permissions.Comment;
                    attributes.push({
                        checked: comment,
                        inputAttribute: OCA.Onlyoffice.Permissions.Comment,
                        label: t(OCA.Onlyoffice.AppName, "Comment")
                    });
                }
                if (this.format["fillForms"] && !review) {
                    fillForms = (OCA.Onlyoffice.Permissions.FillForms & extra["permissions"]) === OCA.Onlyoffice.Permissions.FillForms;
                    attributes.push({
                        checked: fillForms,
                        inputAttribute: OCA.Onlyoffice.Permissions.FillForms,
                        label: t(OCA.Onlyoffice.AppName, "FillForms")
                    });
                }
            }

            if (update) {
                if (this.format["modifyFilter"]) {
                    modifyFilter = (OCA.Onlyoffice.Permissions.ModifyFilter & extra["permissions"]) === OCA.Onlyoffice.Permissions.ModifyFilter;
                    attributes.push({
                        checked: modifyFilter,
                        inputAttribute: OCA.Onlyoffice.Permissions.ModifyFilter,
                        label: t(OCA.Onlyoffice.AppName, "ModifyFilter")
                    });
                }
            }

            return attributes;
        },

        _permissionMenu: function() {
            var popup = $("<div>", {
                class: "popovermenu onlyoffice-share-popup"
            }).append($("<ul>"), {
                id: -1
            });

            var appendItem = function(checked, inputAttribute, name) {
                var item = $("<li>").append($("<span>", {
                    class: "onlyoffice-share-action"
                }).append($("<input>", {
                    id: inputAttribute,
                    type: "checkbox",
                    class: "checkbox action-checkbox__checkbox focusable",
                    checked: checked
                })).append($("<label>", {
                    for: inputAttribute,
                    text: name,
                    class: "onlyoffice-share-label"
                })));

                popup.find("ul").append(item);
            };

            var setTargetId = function(id) {
                popup.find("ul").attr("id", id);
            };

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
                        appendItem(attr.checked, attr.inputAttribute, attr.label);
                    });

                    setTargetId(id);
                    popup.show();
                },

                close: function() {
                    var items = popup.find("li");
                    if (items) {
                        items.remove();
                    }

                    setTargetId(-1);
                    popup.hide();
                },

                refresh: function(attributes) {
                    var items = popup.find("li");
                    if (items) {
                        items.remove();
                    }

                    attributes.forEach(attr => {
                        appendItem(attr.checked, attr.inputAttribute, attr.label);
                    });
                },

                block: function(value) {
                    popup.find("input").prop("disabled", value);
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
            method: "PUT",
            url: OC.linkToOCS("apps/" + OCA.Onlyoffice.AppName + "/api/v1", 2) + "shares?format=json",
            data: data,
            success: function onSuccess(response) {
                callback(response.ocs.data);
            }
        })
    }

})(jQuery, OC);
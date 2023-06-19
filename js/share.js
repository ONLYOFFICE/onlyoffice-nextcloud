/**
 *
 * (c) Copyright Ascensio System SIA 2023
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
        None: 0,
        Review: 1,
        Comment: 2,
        FillForms: 4,
        ModifyFilter: 8
    };

    var tabcontext = null;

    OCA.Onlyoffice.SharingTabView = OCA.Files.DetailTabView.extend({
        id: "onlyofficeSharingTabView",
        className: "tab onlyofficeSharingTabView",

        customEvents: null,
        fileInfo: null,
        templateItem: null,
        permissionsMenu: null,
        colectionLoading: null,
        collection: null,
        format: null,

        events: {
            "click #onlyoffice-share-action": "_onClickPermissionMenu",
            "change .onlyoffice-share-action input": "_onClickSetPermissions"
        },

        initialize() {
            OCA.Files.DetailTabView.prototype.initialize.apply(this, arguments);
            tabcontext = this;

            this.colectionLoading = false;
        },

        getLabel() {
            return t(OCA.Onlyoffice.AppName, "Advanced")
        },

        getIcon() {
            return "icon-onlyoffice-sharing"
        },

        render() {
            var self = this;

            if (this.customEvents === null) {
                this.customEvents = this._customEvents();
                this.customEvents.on();
            }

            this._getTemplate(() => {
                this.collection.forEach(extra => {
                    var itemNode = self.templateItem.clone();
                    var descNode = itemNode.find("span");
                    var avatar = itemNode.find("img");

                    var avatarSrc = "/index.php/avatar/" + extra.shareWith + "/32?v=0";
                    var label = extra.shareWithName;
                    if (extra.type == OC.Share.SHARE_TYPE_GROUP) {
                        avatarSrc = "/index.php/avatar/guest/" + extra.shareWith + "/32?v=0";
                        label = extra.shareWith + " (" + t(OCA.Onlyoffice.AppName, "group") + ")";
                    }

                    avatar[0].src = avatarSrc;
                    descNode[0].innerText = label;

                    itemNode[0].id = extra.share_id;

                    self._getContainer().append(itemNode);
                });
            });
        },

        setFileInfo(fileInfo) {
            if(fileInfo) {
                this.fileInfo = fileInfo;

                if (this.colectionLoading) {
                    return;
                }

                this._getContainer().children().remove();

                this.colectionLoading = true;
                OCA.Onlyoffice.GetShares(this.fileInfo.id, (shares) => {
                    this.collection = shares;

                    this.colectionLoading = false;
                    this.render();
                });
            }
        },

        canDisplay: function (fileInfo) {
            var canDisplay = false;

            if (!fileInfo.isDirectory()) {
                var ext = OCA.Onlyoffice.getFileExtension(fileInfo.name);
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

        _getContainer: function () {
            return this.$el.find(".onlyoffice-share-container");
        },

        _getTemplate: function (callback) {
            if (this.templateItem) {
                callback();
                return;
            }

            var self = this;
            $.get(OC.filePath(OCA.Onlyoffice.AppName, "templates", "share.html"), 
                function (tmpl) {
                    self.templateItem = $(tmpl);

                    $("<ul>", {class: "onlyoffice-share-container"}).appendTo(self.$el)
                    $("<div>").html(t(OCA.Onlyoffice.AppName, "Provide advanced document permissions using ONLYOFFICE Docs")).prependTo(self.$el);

                    callback();
                });
        },

        _onClickSetPermissions: function (e) {
            var permissionValues = this.permissionsMenu.getValues();
            var shareId = this.permissionsMenu.getTargetId();
            var fileId = this.fileInfo.id;
            var extra = this.collection.find(item => item.share_id == shareId);

            var permissions = OCA.Onlyoffice.Permissions.None;
            if (permissionValues[OCA.Onlyoffice.Permissions.Review]) {
                permissions |= OCA.Onlyoffice.Permissions.Review;
            }
            if (permissionValues[OCA.Onlyoffice.Permissions.Comment]
                && (permissions & OCA.Onlyoffice.Permissions.Review) != OCA.Onlyoffice.Permissions.Review
                && (permissions & OCA.Onlyoffice.Permissions.ModifyFilter) != OCA.Onlyoffice.Permissions.ModifyFilter) {
                permissions |= OCA.Onlyoffice.Permissions.Comment;
            }
            if (permissionValues[OCA.Onlyoffice.Permissions.FillForms]
                && (permissions & OCA.Onlyoffice.Permissions.Review) != OCA.Onlyoffice.Permissions.Review) {
                permissions |= OCA.Onlyoffice.Permissions.FillForms;
            }
            if (permissionValues[OCA.Onlyoffice.Permissions.ModifyFilter]
                && (permissions & OCA.Onlyoffice.Permissions.Comment) != OCA.Onlyoffice.Permissions.Comment) {
                permissions |= OCA.Onlyoffice.Permissions.ModifyFilter;
            }

            this.permissionsMenu.block(true);
            OCA.Onlyoffice.SetShares(extra.id, shareId, fileId, permissions, (extra) => {
                this.collection.forEach(item => {
                    if (item.share_id == extra.share_id) {
                        item.id = extra.id;
                        item.permissions = extra.permissions;
                        item.available = extra.available
                    }
                });

                var attributes = this._getPermissionAttributes(extra);

                this.permissionsMenu.refresh(attributes);
                this.permissionsMenu.block(false);
            });
        },

        _onClickPermissionMenu: function (e) {
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

        _getPermissionAttributes: function (extra) {
            var attributes = [];

            if (tabcontext.format["review"]
                && (OCA.Onlyoffice.Permissions.Review & extra["available"]) === OCA.Onlyoffice.Permissions.Review) {
                var review = (OCA.Onlyoffice.Permissions.Review & extra["permissions"]) === OCA.Onlyoffice.Permissions.Review;
                attributes.push({
                    checked: review,
                    extra: OCA.Onlyoffice.Permissions.Review,
                    label: t(OCA.Onlyoffice.AppName, "Review only")
                });
            }
            if (tabcontext.format["comment"]
                && (OCA.Onlyoffice.Permissions.Comment & extra["available"]) === OCA.Onlyoffice.Permissions.Comment) {
                var comment = (OCA.Onlyoffice.Permissions.Comment & extra["permissions"]) === OCA.Onlyoffice.Permissions.Comment;
                attributes.push({
                    checked: comment,
                    extra: OCA.Onlyoffice.Permissions.Comment,
                    label: t(OCA.Onlyoffice.AppName, "Comment only")
                });
            }
            if (tabcontext.format["fillForms"]
                && (OCA.Onlyoffice.Permissions.FillForms & extra["available"]) === OCA.Onlyoffice.Permissions.FillForms) {
                var fillForms = (OCA.Onlyoffice.Permissions.FillForms & extra["permissions"]) === OCA.Onlyoffice.Permissions.FillForms;
                attributes.push({
                    checked: fillForms,
                    extra: OCA.Onlyoffice.Permissions.FillForms,
                    label: t(OCA.Onlyoffice.AppName, "Form filling")
                });
            }

            if (tabcontext.format["modifyFilter"]
                && (OCA.Onlyoffice.Permissions.ModifyFilter & extra["available"]) === OCA.Onlyoffice.Permissions.ModifyFilter) {
                var modifyFilter = (OCA.Onlyoffice.Permissions.ModifyFilter & extra["permissions"]) === OCA.Onlyoffice.Permissions.ModifyFilter;
                attributes.push({
                    checked: modifyFilter,
                    extra: OCA.Onlyoffice.Permissions.ModifyFilter,
                    label: t(OCA.Onlyoffice.AppName, "Custom filter")
                });
            }

            return attributes;
        },

        _customEvents: function () {
            var init = false;
            var self = this;

            return {
                on: function () {
                    if (!init) {
                        $("#content").on("click", function (e) {
                            var target = $(e.target)[0];
                            if (!self.permissionsMenu
                                || !self.permissionsMenu.isOpen()
                                || target.id == "onlyoffice-share-action"
                                || target.className == "onlyoffice-share-label"
                                || target.closest(".onlyoffice-share-action")) {
                                return;
                            }

                            self.permissionsMenu.close();
                        });

                        init = true;
                    }
                }
            }
        },

        _permissionMenu: function () {
            var popup = $("<div>", {
                class: "popovermenu onlyoffice-share-popup"
            }).append($("<ul>"), {
                id: -1
            });

            var appendItem = function (checked, extra, name) {
                var item = $("<li>").append($("<span>", {
                    class: "onlyoffice-share-action"
                }).append($("<input>", {
                    id: "extra-" + extra,
                    type: "checkbox",
                    class: "checkbox action-checkbox__checkbox focusable",
                    checked: checked
                })).append($("<label>", {
                    for: "extra-" + extra,
                    text: name,
                    class: "onlyoffice-share-label"
                })));

                popup.find("ul").append(item);
            };

            var removeItems = function () {
                var items = popup.find("li");
                if (items) {
                    items.remove();
                }
            }

            var setTargetId = function (id) {
                popup.find("ul").attr("id", id);
            };

            this.$el.append(popup);

            return {
                isOpen: function () {
                    return popup.is(":visible");
                },

                open: function (id, attributes, position) {
                    removeItems();

                    if (position) {
                        popup.css({top: position.top});
                    }

                    attributes.forEach(attr => {
                        appendItem(attr.checked, attr.extra, attr.label);
                    });

                    setTargetId(id);
                    popup.show();
                },

                close: function () {
                    removeItems();

                    setTargetId(-1);
                    popup.hide();
                },

                refresh: function (attributes) {
                    removeItems();

                    attributes.forEach(attr => {
                        appendItem(attr.checked, attr.extra, attr.label);
                    });
                },

                block: function (value) {
                    popup.find("input").prop("disabled", value);
                },

                getValues: function () {
                    var values = [];

                    var items = popup.find("input");
                    for (var i = 0; i < items.length; i++) {
                        var extra = items[i].id.split("extra-")[1];
                        values[extra] = items[i].checked;
                    }

                    return values;
                },

                getTargetId: function () {
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

    OCA.Onlyoffice.SetShares = function(id, shareId, fileId, permissions, callback) {
        var data = {
            extraId: id,
            shareId: shareId,
            fileId: fileId,
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
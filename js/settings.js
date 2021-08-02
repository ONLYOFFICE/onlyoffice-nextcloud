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

(function ($, OC) {

    $(document).ready(function () {
        OCA.Onlyoffice = _.extend({}, OCA.Onlyoffice);
        if (!OCA.Onlyoffice.AppName) {
            OCA.Onlyoffice = {
                AppName: "onlyoffice"
            };
        }

        var advToogle = function () {
            $("#onlyofficeSecretPanel").toggleClass("onlyoffice-hide");
            $("#onlyofficeAdv .icon").toggleClass("icon-triangle-s icon-triangle-n");
        };

        if ($("#onlyofficeInternalUrl").val().length
            || $("#onlyofficeStorageUrl").val().length) {
            advToogle();
        }

        $("#onlyofficeAdv").click(advToogle);

        $("#onlyofficeGroups").prop("checked", $("#onlyofficeLimitGroups").val() != "");

        var groupListToggle = function () {
            if ($("#onlyofficeGroups").prop("checked")) {
                OC.Settings.setupGroupsSelect($("#onlyofficeLimitGroups"));
            } else {
                $("#onlyofficeLimitGroups").select2("destroy");
            }
        };

        $("#onlyofficeGroups").click(groupListToggle);
        groupListToggle();

        var demoToggle = function () {
            $("#onlyofficeAddrSettings input:not(#onlyofficeStorageUrl)").prop("disabled", $("#onlyofficeDemo").prop("checked"));
        };

        $("#onlyofficeDemo").click(demoToggle);
        demoToggle();

        var watermarkToggle = function () {
            $("#onlyofficeWatermarkSettings").toggleClass("onlyoffice-hide", !$("#onlyofficeWatermark_enabled").prop("checked"));
        };

        $("#onlyofficeWatermark_enabled").click(watermarkToggle)

        $("#onlyofficeWatermark_shareAll").click(function () {
            $("#onlyofficeWatermark_shareRead").parent().toggleClass("onlyoffice-hide");
        });

        $("#onlyofficeWatermark_linkAll").click(function () {
            $("#onlyofficeWatermark_link_sensitive").toggleClass("onlyoffice-hide");
        });

        var watermarkLists = [
            "allGroups",
            "allTags",
            "linkTags",
        ];
        $.each(watermarkLists, function (i, watermarkList) {
            var watermarkListToggle = function () {
                if ($("#onlyofficeWatermark_" + watermarkList).prop("checked")) {
                    if (watermarkList.indexOf("Group") >= 0) {
                        OC.Settings.setupGroupsSelect($("#onlyofficeWatermark_" + watermarkList + "List"));
                    } else {
                        OC.SystemTags.collection.fetch({
                            success: function () {
                                $("#onlyofficeWatermark_" + watermarkList + "List").select2({
                                    allowClear: true,
                                    closeOnSelect: false,
                                    multiple: true,
                                    separator: "|",
                                    toggleSelect: true,
                                    placeholder: t(OCA.Onlyoffice.AppName, "Select tag"),
                                    query: _.debounce(function (query) {
                                        query.callback({
                                            results: OC.SystemTags.collection.filterByName(query.term)
                                        });
                                    }, 100, true),
                                    initSelection: function (element, callback) {
                                        var selection = ($(element).val() || []).split("|").map(function (tagId) {
                                            return OC.SystemTags.collection.get(tagId);
                                        });
                                        callback(selection);
                                    },
                                    formatResult: function (tag) {
                                        return OC.SystemTags.getDescriptiveTag(tag);
                                    },
                                    formatSelection: function (tag) {
                                        return tag.get("name");
                                    },
                                    sortResults: function (results) {
                                        results.sort(function (a, b) {
                                            return OC.Util.naturalSortCompare(a.get("name"), b.get("name"));
                                        });
                                        return results;
                                    }
                                });
                            }
                        });
                    }
                } else {
                    $("#onlyofficeWatermark_" + watermarkList + "List").select2("destroy");
                }
            };

            $("#onlyofficeWatermark_" + watermarkList).click(watermarkListToggle);
            watermarkListToggle();
        });


        $("#onlyofficeAddrSave").click(function () {
            $(".section-onlyoffice").addClass("icon-loading");
            var onlyofficeUrl = $("#onlyofficeUrl").val().trim();

            if (!onlyofficeUrl.length) {
                $("#onlyofficeInternalUrl, #onlyofficeStorageUrl, #onlyofficeSecret").val("");
            }

            var onlyofficeInternalUrl = ($("#onlyofficeInternalUrl:visible").val() || "").trim();
            var onlyofficeStorageUrl = ($("#onlyofficeStorageUrl:visible").val() || "").trim();
            var onlyofficeVerifyPeerOff = $("#onlyofficeVerifyPeerOff").prop("checked");
            var onlyofficeSecret = ($("#onlyofficeSecret:visible").val() || "").trim();
            var demo = $("#onlyofficeDemo").prop("checked");

            $.ajax({
                method: "PUT",
                url: OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/settings/address"),
                data: {
                    documentserver: onlyofficeUrl,
                    documentserverInternal: onlyofficeInternalUrl,
                    storageUrl: onlyofficeStorageUrl,
                    verifyPeerOff: onlyofficeVerifyPeerOff,
                    secret: onlyofficeSecret,
                    demo: demo
                },
                success: function onSuccess(response) {
                    $(".section-onlyoffice").removeClass("icon-loading");
                    if (response && (response.documentserver != null || demo)) {
                        $("#onlyofficeUrl").val(response.documentserver);
                        $("#onlyofficeInternalUrl").val(response.documentserverInternal);
                        $("#onlyofficeStorageUrl").val(response.storageUrl);
                        $("#onlyofficeSecret").val(response.secret);

                        $(".section-onlyoffice-common, .section-onlyoffice-templates, .section-onlyoffice-watermark").toggleClass("onlyoffice-hide", (!response.documentserver.length && !demo) || !!response.error.length);

                        var versionMessage = response.version ? (" (" + t(OCA.Onlyoffice.AppName, "version") + " " + response.version + ")") : "";

                        if (response.error) {
                            OCP.Toast.error(t(OCA.Onlyoffice.AppName, "Error when trying to connect") + " (" + response.error + ")" + versionMessage);
                        } else {
                            OCP.Toast.success(t(OCA.Onlyoffice.AppName, "Settings have been successfully updated") + versionMessage);
                        }
                    }
                }
            });
        });

        $("#onlyofficeSave").click(function () {
            $(".section-onlyoffice").addClass("icon-loading");

            var defFormats = {};
            $("input[id^=\"onlyofficeDefFormat\"]").each(function () {
                defFormats[this.name] = this.checked;
            });

            var editFormats = {};
            $("input[id^=\"onlyofficeEditFormat\"]").each(function () {
                editFormats[this.name] = this.checked;
            });

            var sameTab = $("#onlyofficeSameTab").is(":checked");
            var preview = $("#onlyofficePreview").is(":checked");
            var versionHistory = $("#onlyofficeVersionHistory").is(":checked");

            var limitGroupsString = $("#onlyofficeGroups").prop("checked") ? $("#onlyofficeLimitGroups").val() : "";
            var limitGroups = limitGroupsString ? limitGroupsString.split("|") : [];

            var chat = $("#onlyofficeChat").is(":checked");
            var compactHeader = $("#onlyofficeCompactHeader").is(":checked");
            var feedback = $("#onlyofficeFeedback").is(":checked");
            var forcesave = $("#onlyofficeForcesave").is(":checked");
            var help = $("#onlyofficeHelp").is(":checked");
            var toolbarNoTabs = $("#onlyofficeToolbarNoTabs").is(":checked");
            var reviewDisplay = $("input[type='radio'][name='reviewDisplay']:checked").attr("id").replace("onlyofficeReviewDisplay_", "");

            $.ajax({
                method: "PUT",
                url: OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/settings/common"),
                data: {
                    defFormats: defFormats,
                    editFormats: editFormats,
                    sameTab: sameTab,
                    preview: preview,
                    versionHistory: versionHistory,
                    limitGroups: limitGroups,
                    chat: chat,
                    compactHeader: compactHeader,
                    feedback: feedback,
                    forcesave: forcesave,
                    help: help,
                    toolbarNoTabs: toolbarNoTabs,
                    reviewDisplay: reviewDisplay
                },
                success: function onSuccess(response) {
                    $(".section-onlyoffice").removeClass("icon-loading");
                    if (response) {
                        OCP.Toast.success(t(OCA.Onlyoffice.AppName, "Settings have been successfully updated"));
                    }
                }
            });
        });

        $("#onlyofficeWatermarkSave").click(function () {
            $(".section-onlyoffice").addClass("icon-loading");

            var watermarkSettings = {
                enabled: $("#onlyofficeWatermark_enabled").is(":checked")
            };
            if (watermarkSettings.enabled) {
                watermarkSettings.text = ($("#onlyofficeWatermark_text").val() || "").trim();

                var watermarkLabels = [
                    "allGroups",
                    "allTags",
                    "linkAll",
                    "linkRead",
                    "linkSecure",
                    "linkTags",
                    "shareAll",
                    "shareRead"
                ];
                $.each(watermarkLabels, function (i, watermarkLabel) {
                    watermarkSettings[watermarkLabel] = $("#onlyofficeWatermark_" + watermarkLabel).is(":checked");
                });

                $.each(watermarkLists, function (i, watermarkList) {
                    var list = $("#onlyofficeWatermark_" + watermarkList).is(":checked") ? $("#onlyofficeWatermark_" + watermarkList + "List").val() : "";
                    watermarkSettings[watermarkList + "List"] = list ? list.split("|") : [];
                });

            }

            $.ajax({
                method: "PUT",
                url: OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/settings/watermark"),
                data: {
                    settings: watermarkSettings
                },
                success: function onSuccess(response) {
                    $(".section-onlyoffice").removeClass("icon-loading");
                    if (response) {
                        OCP.Toast.success(t(OCA.Onlyoffice.AppName, "Settings have been successfully updated"));
                    }
                }
            });
        });

        $(".section-onlyoffice-addr input").keypress(function (e) {
            var code = e.keyCode || e.which;
            if (code === 13) {
                $("#onlyofficeAddrSave").click();
            }
        });

        $("#onlyofficeSecret-show").click(function () {
            if ($("#onlyofficeSecret").attr("type") == "password") {
                $("#onlyofficeSecret").attr("type", "text");
            } else {
                $("#onlyofficeSecret").attr("type", "password");
            }
        });

        $("#onlyofficeClearVersionHistory").click(function () {
            $(".section-onlyoffice").addClass("icon-loading");

            $.ajax({
                method: "DELETE",
                url: OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/settings/history"),
                success: function onSuccess(response) {
                    $(".section-onlyoffice").removeClass("icon-loading");
                    if (response) {
                        OCP.Toast.success(t(OCA.Onlyoffice.AppName, "All history successfully deleted"));
                    }
                }
            });
        });

        $("#onlyofficeAddTemplate").change(function () {
            var file = this.files[0];
            var data = new FormData();

            data.append("file", file);

            $(".section-onlyoffice").addClass("icon-loading");
            OCA.Onlyoffice.AddTemplate(file, (template, error) => {

                $(".section-onlyoffice").removeClass("icon-loading");
                var message = error ? t(OCA.Onlyoffice.AppName, "Error") + ": " + error
                                    : t(OCA.Onlyoffice.AppName, "Template successfully added");

                if (error) {
                    OCP.Toast.error(message);
                    return;
                }

                if (template) {
                    OCA.Onlyoffice.AttachItemTemplate(template);
                }
                OCP.Toast.success(message);
            });
        });

        $(document).on("click", ".onlyoffice-template-delete", function (event) {
            var item = $(event.target).parents(".onlyoffice-template-item");
            var templateId = $(item).attr("data-id");

            $(".section-onlyoffice").addClass("icon-loading");
            OCA.Onlyoffice.DeleteTemplate(templateId, (response) => {
                $(".section-onlyoffice").removeClass("icon-loading");

                var message = response.error ? t(OCA.Onlyoffice.AppName, "Error") + ": " + response.error
                                             : t(OCA.Onlyoffice.AppName, "Template successfully deleted");
                if (response.error) {
                    OCP.Toast.error(message);
                    return;
                }

                $(item).detach();
                OCP.Toast.success(message);
            });
        });

        $(document).on("click", ".onlyoffice-template-item p", function (event) {
            var item = $(event.target).parents(".onlyoffice-template-item");
            var templateId = $(item).attr("data-id");

            var url = OC.generateUrl("/apps/" + OCA.Onlyoffice.AppName + "/{fileId}?template={template}",
            {
                fileId: templateId,
                template: "true"
            });

            window.open(url);
        });

        $(document).on("click", ".onlyoffice-template-download", function (event) {
            var item = $(event.target).parents(".onlyoffice-template-item");
            var templateId = $(item).attr("data-id");

            var downloadLink = OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/downloadas?fileId={fileId}&template={template}",{
                fileId: templateId,
                template: "true"
            });

            location.href = downloadLink;
        });
    });

})(jQuery, OC);

/**
 *
 * (c) Copyright Ascensio System SIA 2020
 *
 * This program is a free software product.
 * You can redistribute it and/or modify it under the terms of the GNU Affero General Public License
 * (AGPL) version 3 as published by the Free Software Foundation.
 * In accordance with Section 7(a) of the GNU AGPL its Section 15 shall be amended to the effect
 * that Ascensio System SIA expressly excludes the warranty of non-infringement of any third-party rights.
 *
 * This program is distributed WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * For details, see the GNU AGPL at: http://www.gnu.org/licenses/agpl-3.0.html
 *
 * You can contact Ascensio System SIA at 20A-12 Ernesta Birznieka-Upisha street, Riga, Latvia, EU, LV-1050.
 *
 * The interactive user interfaces in modified source and object code versions of the Program
 * must display Appropriate Legal Notices, as required under Section 5 of the GNU AGPL version 3.
 *
 * Pursuant to Section 7(b) of the License you must retain the original Product logo when distributing the program.
 * Pursuant to Section 7(e) we decline to grant you any rights under trademark law for use of our trademarks.
 *
 * All the Product's GUI elements, including illustrations and icon sets, as well as technical
 * writing content are licensed under the terms of the Creative Commons Attribution-ShareAlike 4.0 International.
 * See the License terms at http://creativecommons.org/licenses/by-sa/4.0/legalcode
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

        var demoToggle = function() {
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
        $.each(watermarkLists, function(i, watermarkList) {
            var watermarkListToggle = function() {
                if ($("#onlyofficeWatermark_" + watermarkList).prop("checked")) {
                    if (watermarkList.indexOf("Group") >= 0) {
                        OC.Settings.setupGroupsSelect($("#onlyofficeWatermark_" + watermarkList + "List"));
                    } else {
                        OC.SystemTags.collection.fetch({
                            success: function() {
                                $("#onlyofficeWatermark_" + watermarkList + "List").select2({
                                    allowClear: true,
                                    closeOnSelect: false,
                                    multiple: true,
                                    separator: "|",
                                    toggleSelect: true,
                                    placeholder: t(OCA.Onlyoffice.AppName, "Select tag"),
                                    query: _.debounce(function(query) {
                                        query.callback({
                                            results: OC.SystemTags.collection.filterByName(query.term)
                                        });
                                    }, 100, true),
                                    initSelection: function(element, callback) {
                                        var selection = ($(element).val() || []).split("|").map(function(tagId){
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
                                    sortResults: function(results) {
                                        results.sort(function(a, b) {
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
            var onlyofficeSecret = ($("#onlyofficeSecret:visible").val() || "").trim();
            var demo = $("#onlyofficeDemo").prop("checked");

            $.ajax({
                method: "PUT",
                url: OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/settings/address"),
                data: {
                    documentserver: onlyofficeUrl,
                    documentserverInternal: onlyofficeInternalUrl,
                    storageUrl: onlyofficeStorageUrl,
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

                        $(".section-onlyoffice-common, .section-onlyoffice-watermark").toggleClass("onlyoffice-hide", (!response.documentserver.length && !demo) || !!response.error.length);

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
            $("input[id^=\"onlyofficeDefFormat\"]").each(function() {
                defFormats[this.name] = this.checked;
            });

            var editFormats = {};
            $("input[id^=\"onlyofficeEditFormat\"]").each(function() {
                editFormats[this.name] = this.checked;
            });

            var sameTab = $("#onlyofficeSameTab").is(":checked");

            var limitGroupsString = $("#onlyofficeGroups").prop("checked") ? $("#onlyofficeLimitGroups").val() : "";
            var limitGroups = limitGroupsString ? limitGroupsString.split("|") : [];

            var chat = $("#onlyofficeChat").is(":checked");
            var compactHeader = $("#onlyofficeCompactHeader").is(":checked");
            var feedback = $("#onlyofficeFeedback").is(":checked");
            var help = $("#onlyofficeHelp").is(":checked");
            var toolbarNoTabs = !$("#onlyofficeToolbarNoTabs").is(":checked");
            var reviewDisplay = $("input[type='radio'][name='reviewDisplay']:checked").attr("id").replace("onlyofficeReviewDisplay_", "");

            $.ajax({
                method: "PUT",
                url: OC.generateUrl("apps/" + OCA.Onlyoffice.AppName + "/ajax/settings/common"),
                data: {
                    defFormats: defFormats,
                    editFormats: editFormats,
                    sameTab: sameTab,
                    limitGroups: limitGroups,
                    chat: chat,
                    compactHeader: compactHeader,
                    feedback: feedback,
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
    });

})(jQuery, OC);

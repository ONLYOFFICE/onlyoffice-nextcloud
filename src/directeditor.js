/**
 *
 * (c) Copyright Ascensio System SIA 2024
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

(function (OCA) {

    OCA.Onlyoffice = _.extend({}, OCA.Onlyoffice);

    var callMobileMessage = function (messageName, attributes) {
        var message = messageName
        if (typeof attributes !== "undefined") {
            message = {
                MessageName: messageName,
                Values: attributes,
            };
        }
        var attributesString = null
        try {
            attributesString = JSON.stringify(attributes);
        } catch (e) {
            attributesString = null;
        }

        // Forward to mobile handler
        if (window.DirectEditingMobileInterface && typeof window.DirectEditingMobileInterface[messageName] === "function") {
            if (attributesString === null || typeof attributesString === "undefined") {
                window.DirectEditingMobileInterface[messageName]();
            } else {
                window.DirectEditingMobileInterface[messageName](attributesString);
            }
        }

        // iOS webkit fallback
        if (window.webkit
            && window.webkit.messageHandlers
            && window.webkit.messageHandlers.DirectEditingMobileInterface) {
            window.webkit.messageHandlers.DirectEditingMobileInterface.postMessage(message);
        }

        window.postMessage(message);
    }

    OCA.Onlyoffice.directEditor = {
        close: function () {
            callMobileMessage("close");
        },
        loaded: function () {
            callMobileMessage("loaded");
        }
    };


    window.onload = function() {
        let directEditorError = document.getElementById("directEditorError");

        if (directEditorError) {
            OCA.Onlyoffice.directEditor.loaded();
            let directEditorErrorButton = document.getElementById("directEditorErrorButton");
            directEditorErrorButton.addEventListener('click', function() {
                OCA.Onlyoffice.directEditor.close();
            });
        }
    };

})(OCA);

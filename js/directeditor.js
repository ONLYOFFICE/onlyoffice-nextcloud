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

})(OCA);

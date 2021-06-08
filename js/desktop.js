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

    if (!window["AscDesktopEditor"]) {
        return;
    }

    OCA.Onlyoffice.Desktop = true;

    if (location.href.indexOf(_oc_appswebroots.dashboard) !== -1) {
        location.href = location.href.split(_oc_appswebroots.dashboard)[0] + _oc_appswebroots.files;
        return;
    }

    $("html").addClass("AscDesktopEditor");

    var domain = new RegExp("^http(s)?:\/\/[^\/]+").exec(location)[0];
    domain += OC.getRootPath();

    var data = {
        displayName: oc_current_user,
        domain: domain,
        provider: "Nextcloud",
    };

    window.AscDesktopEditor.execCommand("portal:login", JSON.stringify(data));

})(OCA);

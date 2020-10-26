/**
 *
 * (c) Copyright Ascensio System SIA 2020
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

    OCA.Onlyoffice.getTags = function (callback) {
        $.ajax({
            method: 'PROPFIND',
            url: OC.linkToRemote('dav') + '/systemtags',
            data: `<?xml version="1.0"?>
                        <d:propfind  xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
                        <d:prop>
                            <oc:id />
                            <oc:display-name />
                            <oc:user-visible />
                            <oc:user-assignable />
                            <oc:can-assign />
                        </d:prop>
                        </d:propfind>`,
            success: function (response) {
                callback(xmlToTagList(response));
            }
        });
    }

    OCA.Onlyoffice.getDescriptiveTag = function(tag) {
        var $span = $('<span>')
        $span.append(tag.displayName)

        var scope
        if (!tag.userAssignable) {
            scope = t('core', 'restricted')
        }
        if (!tag.userVisible) {
            scope = t('core', 'invisible')
        }
        if (scope) {
            $span.append($('<em>').text(' (' + scope + ')'))
        }
        return $span
    }

    xmlToTagList = function(xml) {
        var json = xmlToJson(xml);
        var listTags = json['d:multistatus']['d:response'];
        var result = [];
        for (let index in listTags) {
            let tag = listTags[index]['d:propstat']

            if (tag['d:status']['#text'] !== 'HTTP/1.1 200 OK') {
                continue
            }
            result.push({
                id: tag['d:prop']['oc:id']['#text'],
                displayName: tag['d:prop']['oc:display-name']['#text'],
                canAssign: tag['d:prop']['oc:can-assign']['#text'] === 'true',
                userAssignable: tag['d:prop']['oc:user-assignable']['#text'] === 'true',
                userVisible: tag['d:prop']['oc:user-visible']['#text'] === 'true'
            })
        }
        return result;
    }

    xmlToJson = function(xml) {
        var obj = {};
        if (xml.nodeType == 1) {                
            if (xml.attributes.length > 0) {
                obj["@attributes"] = {};
                for (var j = 0; j < xml.attributes.length; j++) {
                    var attribute = xml.attributes.item(j);
                    obj["@attributes"][attribute.nodeName] = attribute.nodeValue;
                }
            }
        } else if (xml.nodeType == 3) { 
            obj = xml.nodeValue;
        }            
        if (xml.hasChildNodes()) {
            for (var i = 0; i < xml.childNodes.length; i++) {
                var item = xml.childNodes.item(i);
                var nodeName = item.nodeName;
                if (typeof (obj[nodeName]) == "undefined") {
                    obj[nodeName] = xmlToJson(item);
                } else {
                    if (typeof (obj[nodeName].push) == "undefined") {
                        var old = obj[nodeName];
                        obj[nodeName] = [];
                        obj[nodeName].push(old);
                    }
                    obj[nodeName].push(xmlToJson(item));
                }
            }
        }
        return obj;
    }
})(OCA);
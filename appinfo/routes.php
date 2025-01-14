<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2025
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

return [
    "routes" => [
       ["name" => "callback#download", "url" => "/download", "verb" => "GET"],
       ["name" => "callback#emptyfile", "url" => "/empty", "verb" => "GET"],
       ["name" => "callback#track", "url" => "/track", "verb" => "POST"],
       ["name" => "template#preview", "url" => "/preview", "verb" => "GET"],
       ["name" => "editor#create_new", "url" => "/new", "verb" => "GET"],
       ["name" => "editor#download", "url" => "/downloadas", "verb" => "GET"],
       ["name" => "editor#index", "url" => "/{fileId}", "verb" => "GET"],
       ["name" => "editor#public_page", "url" => "/s/{shareToken}", "verb" => "GET"],
       ["name" => "editor#users", "url" => "/ajax/users", "verb" => "GET"],
       ["name" => "editor#user_info", "url" => "/ajax/userInfo", "verb" => "GET"],
       ["name" => "editor#mention", "url" => "/ajax/mention", "verb" => "POST"],
       ["name" => "editor#reference", "url" => "/ajax/reference", "verb" => "POST"],
       ["name" => "editor#create", "url" => "/ajax/new", "verb" => "POST"],
       ["name" => "editor#convert", "url" => "/ajax/convert", "verb" => "POST"],
       ["name" => "editor#save", "url" => "/ajax/save", "verb" => "POST"],
       ["name" => "editor#url", "url" => "/ajax/url", "verb" => "GET"],
       ["name" => "editor#history", "url" => "/ajax/history", "verb" => "GET"],
       ["name" => "editor#version", "url" => "/ajax/version", "verb" => "GET"],
       ["name" => "editor#restore", "url" => "/ajax/restore", "verb" => "PUT"],
       ["name" => "settings#save_address", "url" => "/ajax/settings/address", "verb" => "PUT"],
       ["name" => "settings#save_common", "url" => "/ajax/settings/common", "verb" => "PUT"],
       ["name" => "settings#save_security", "url" => "/ajax/settings/security", "verb" => "PUT"],
       ["name" => "settings#clear_history", "url" => "/ajax/settings/history", "verb" => "DELETE"],
       ["name" => "template#add_template", "url" => "/ajax/template", "verb" => "POST"],
       ["name" => "template#delete_template", "url" => "/ajax/template", "verb" => "DELETE"],
       ["name" => "template#get_templates", "url" => "/ajax/template", "verb" => "GET"],
    ],
    "ocs" => [
        ["name" => "federation#key", "url" => "/api/v1/key", "verb" => "POST"],
        ["name" => "federation#keylock", "url" => "/api/v1/keylock", "verb" => "POST"],
        ["name" => "federation#healthcheck", "url" => "/api/v1/healthcheck", "verb" => "GET"],
        ["name" => "editorApi#config", "url" => "/api/v1/config/{fileId}", "verb" => "GET"],
        ["name" => "sharingApi#get_shares", "url" => "/api/v1/shares/{fileId}", "verb" => "GET"],
        ["name" => "sharingApi#set_shares", "url" => "/api/v1/shares", "verb" => "PUT"]
    ]
];

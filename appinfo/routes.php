<?php
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
       ["name" => "editor#mention", "url" => "/ajax/mention", "verb" => "POST"],
       ["name" => "editor#create", "url" => "/ajax/new", "verb" => "POST"],
       ["name" => "editor#convert", "url" => "/ajax/convert", "verb" => "POST"],
       ["name" => "editor#save", "url" => "/ajax/save", "verb" => "POST"],
       ["name" => "editor#url", "url" => "/ajax/url", "verb" => "GET"],
       ["name" => "editor#history", "url" => "/ajax/history", "verb" => "GET"],
       ["name" => "editor#version", "url" => "/ajax/version", "verb" => "GET"],
       ["name" => "editor#restore", "url" => "/ajax/restore", "verb" => "PUT"],
       ["name" => "settings#save_address", "url" => "/ajax/settings/address", "verb" => "PUT"],
       ["name" => "settings#save_common", "url" => "/ajax/settings/common", "verb" => "PUT"],
       ["name" => "settings#save_watermark", "url" => "/ajax/settings/watermark", "verb" => "PUT"],
       ["name" => "settings#get_settings", "url" => "/ajax/settings", "verb" => "GET"],
       ["name" => "settings#clear_history", "url" => "/ajax/settings/history", "verb" => "DELETE"],
       ["name" => "template#add_template", "url" => "/ajax/template", "verb" => "POST"],
       ["name" => "template#delete_template", "url" => "/ajax/template", "verb" => "DELETE"],
       ["name" => "template#get_templates", "url" => "/ajax/template", "verb" => "GET"],
    ],
    "ocs" => [
        ["name" => "federation#key", "url" => "/api/v1/key", "verb" => "POST"],
        ["name" => "federation#keylock", "url" => "/api/v1/keylock", "verb" => "POST"],
        ["name" => "editorapi#config", "url" => "/api/v1/config/{fileId}", "verb" => "GET"]
    ]
];
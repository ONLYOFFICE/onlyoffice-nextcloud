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

    style("onlyoffice", "editor");
    script("onlyoffice", "desktop");
    script("onlyoffice", "editor");
    if (!empty($_["directToken"])) {
        script("onlyoffice", "directeditor");
    }
?>

<div id="app">

    <div id="iframeEditor"
        data-id="<?php p($_["fileId"]) ?>"
        data-path="<?php p($_["filePath"]) ?>"
        data-sharetoken="<?php p($_["shareToken"]) ?>"
        data-directtoken="<?php p($_["directToken"]) ?>"
        data-version="<?php p($_["version"]) ?>"
        data-template="<?php p($_["isTemplate"]) ?>"
        data-anchor="<?php p($_["anchor"]) ?>"
        data-inframe="<?php p($_["inframe"]) ?>"></div>

    <?php if (!empty($_["documentServerUrl"])) { ?>
        <script nonce="<?php p(base64_encode($_["requesttoken"])) ?>"
            src="<?php p($_["documentServerUrl"]) ?>web-apps/apps/api/documents/api.js" type="text/javascript"></script>
    <?php } ?>

</div>

<?php if (!empty($_["directToken"])) { ?>
<script>
    document.querySelector("meta[name='viewport']")
        .setAttribute("content", "width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no");
</script>
<?php } ?>

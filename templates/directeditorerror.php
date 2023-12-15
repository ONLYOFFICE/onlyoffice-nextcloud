<?php
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

script("onlyoffice", "directeditor");
?>
<div class="guest-box" id="directEditorError">
	<h2><?php p($l->t('Error')); ?></h2>
    <p><?php p($_["error"]); ?></p>
</div>
<p>
    <button class="primary button" id="directEditorErrorButton" style="margin-top: 18px;">Go back</button>
</p>

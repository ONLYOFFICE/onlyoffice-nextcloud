<!--
  (c) Copyright Ascensio System SIA 2026

  This program is a free software product.
  You can redistribute it and/or modify it under the terms of the GNU Affero General Public License
  (AGPL) version 3 as published by the Free Software Foundation.
  In accordance with Section 7(a) of the GNU AGPL its Section 15 shall be amended to the effect
  that Ascensio System SIA expressly excludes the warranty of non-infringement of any third-party rights.

  This program is distributed WITHOUT ANY WARRANTY;
  without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
  For details, see the GNU AGPL at: http://www.gnu.org/licenses/agpl-3.0.html

  You can contact Ascensio System SIA at 20A-12 Ernesta Birznieka-Upisha street, Riga, Latvia, EU, LV-1050.

  The interactive user interfaces in modified source and object code versions of the Program
  must display Appropriate Legal Notices, as required under Section 5 of the GNU AGPL version 3.

  Pursuant to Section 7(b) of the License you must retain the original Product logo when distributing the program.
  Pursuant to Section 7(e) we decline to grant you any rights under trademark law for use of our trademarks.

  All the Product's GUI elements, including illustrations and icon sets, as well as technical
  writing content are licensed under the terms of the Creative Commons Attribution-ShareAlike 4.0 International.
  See the License terms at http://creativecommons.org/licenses/by-sa/4.0/legalcode
-->
<script setup lang="ts">
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { onMounted, ref } from 'vue'
import TemplateList from '../TemplateList.vue'
import CommonSection from './CommonSection.vue'
import SecuritySection from './SecuritySection.vue'
import ServerSection from './ServerSection.vue'

const state = loadState<Record<string, unknown>>('onlyoffice', 'admin-settings')
const showSections = ref(!!(state.successful && (state.documentserver || (state.demo as { enabled: boolean }).enabled)))

onMounted(() => {
	if (state.settingsError) {
		showError(t('onlyoffice', 'Error when trying to connect') + ' (' + state.settingsError + ')')
	}
})

/**
 * Handles the address-saved event from ServerSection.
 * @param {boolean} showSections whether non-server sections should be visible
 */
function onAddressSaved({ showSections: show }: { showSections: boolean }) {
	showSections.value = show
}
</script>

<template>
	<div>
		<ServerSection
			:documentserver="state.documentserver as string"
			:documentserverInternal="state.documentserverInternal as string"
			:storageUrl="state.storageUrl as string"
			:verifyPeerOff="state.verifyPeerOff as boolean"
			:secret="state.secret as string"
			:jwtHeader="state.jwtHeader as string"
			:demo="state.demo as { enabled: boolean, available: boolean }"
			@addressSaved="onAddressSaved" />
		<template v-if="showSections">
			<CommonSection
				:formats="state.formats as Record<string, Record<string, unknown>>"
				:restrictExternalStorage="state.restrictExternalStorage as boolean"
				:sameTab="state.sameTab as boolean"
				:enableSharing="state.enableSharing as boolean"
				:preview="state.preview as boolean"
				:advanced="state.advanced as boolean"
				:cronChecker="state.cronChecker as boolean"
				:emailNotifications="state.emailNotifications as boolean"
				:versionHistory="state.versionHistory as boolean"
				:limitGroups="state.limitGroups as string[]"
				:chat="state.chat as boolean"
				:compactHeader="state.compactHeader as boolean"
				:feedback="state.feedback as boolean"
				:forcesave="state.forcesave as boolean"
				:liveViewOnShare="state.liveViewOnShare as boolean"
				:help="state.help as boolean"
				:reviewDisplay="state.reviewDisplay as string"
				:theme="state.theme as string"
				:unknownAuthor="state.unknownAuthor as string" />
			<div class="section section-onlyoffice section-onlyoffice-templates">
				<h2>
					{{ t('onlyoffice', 'Common templates') }}
					<input id="onlyofficeAddTemplate" type="file" class="hidden-visually">
					<label for="onlyofficeAddTemplate" class="icon-add" :title="t('onlyoffice', 'Add a new template')" />
				</h2>
				<TemplateList />
			</div>
			<SecuritySection
				:plugins="state.plugins as boolean"
				:macros="state.macros as boolean"
				:protection="state.protection as string"
				:watermark="state.watermark as Record<string, unknown>"
				:tagsEnabled="state.tagsEnabled as boolean" />
		</template>
	</div>
</template>

<style>
.section-onlyoffice input:not(.v-select *) {
    display: block;
    width: 250px;
}

.section-onlyoffice .v-select.select {
    min-width: 250px;
    max-width: 250px;
}

.section-onlyoffice .block-inline {
    margin-left: 1.5em;
}

.section-onlyoffice .onlyoffice-tables {
    margin-top: 4px;
    line-height: initial;
    column-width: 140px;
    -moz-column-width: 140px;
    -webkit-column-width: 140px;
    margin-bottom: 1em;
}
</style>

<style scoped>
.section-onlyoffice-templates .icon-add {
    opacity: 0.5;
    padding-left: 44px;
}

.section-onlyoffice-templates .icon-add:hover {
    opacity: 0.7;
}

.section-onlyoffice-templates input {
    display: none;
}
</style>

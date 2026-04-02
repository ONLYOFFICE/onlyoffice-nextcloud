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
import { ref, onMounted } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'

import EmptyJwtInfoDialog from '../EmptyJwtInfoDialog.vue'
import TemplateList from '../TemplateList.vue'
import ServerSection from './ServerSection.vue'
import CommonSection from './CommonSection.vue'
import SecuritySection from './SecuritySection.vue'

const state = loadState<Record<string, unknown>>('onlyoffice', 'admin-settings')
const successful = ref(state.successful as boolean)

onMounted(() => {
	if (state.settingsError) {
		showError(t('onlyoffice', 'Error when trying to connect') + ' (' + state.settingsError + ')')
	}
})

/**
 * Handles the address-saved event from ServerSection.
 * Shows the empty JWT warning dialog when the server was saved successfully but no JWT secret is set.
 * @param result address-saved event payload
 * @param {boolean} result.successful whether the save request succeeded
 * @param {boolean} result.hasSecret whether a JWT secret is currently configured
 */
function onAddressSaved({ successful: ok, hasSecret }: { successful: boolean, hasSecret: boolean }) {
	successful.value = ok
	if (ok && !hasSecret) {
		spawnDialog(EmptyJwtInfoDialog)
	}
}
</script>

<template>
	<div>
		<ServerSection :documentserver="state.documentserver as string"
			:documentserver-internal="state.documentserverInternal as string"
			:storage-url="state.storageUrl as string"
			:verify-peer-off="state.verifyPeerOff as boolean"
			:secret="state.secret as string"
			:jwt-header="state.jwtHeader as string"
			:demo="state.demo as { enabled: boolean, available: boolean }"
			@address-saved="onAddressSaved" />
		<template v-if="successful">
			<CommonSection :formats="state.formats as Record<string, Record<string, unknown>>"
				:same-tab="state.sameTab as boolean"
				:enable-sharing="state.enableSharing as boolean"
				:preview="state.preview as boolean"
				:advanced="state.advanced as boolean"
				:cron-checker="state.cronChecker as boolean"
				:email-notifications="state.emailNotifications as boolean"
				:version-history="state.versionHistory as boolean"
				:limit-groups="state.limitGroups as string[]"
				:chat="state.chat as boolean"
				:compact-header="state.compactHeader as boolean"
				:feedback="state.feedback as boolean"
				:forcesave="state.forcesave as boolean"
				:live-view-on-share="state.liveViewOnShare as boolean"
				:help="state.help as boolean"
				:review-display="state.reviewDisplay as string"
				:theme="state.theme as string"
				:unknown-author="state.unknownAuthor as string" />
			<div class="section section-onlyoffice section-onlyoffice-templates">
				<h2>
					{{ t('onlyoffice', 'Common templates') }}
					<input id="onlyofficeAddTemplate" type="file" class="hidden-visually">
					<label for="onlyofficeAddTemplate" class="icon-add" :title="t('onlyoffice', 'Add a new template')" />
				</h2>
				<TemplateList />
			</div>
			<SecuritySection :plugins="state.plugins as boolean"
				:macros="state.macros as boolean"
				:protection="state.protection as string"
				:watermark="state.watermark as Record<string, unknown>"
				:tags-enabled="state.tagsEnabled as boolean" />
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

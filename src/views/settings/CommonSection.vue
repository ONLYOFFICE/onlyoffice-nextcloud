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
import { ref, computed, watch } from 'vue'
import { showConfirmation, showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSettingsSelectGroup from '@nextcloud/vue/components/NcSettingsSelectGroup'
import { clearHistory, saveCommonSettings } from '../../services/SettingsService'

const props = defineProps<{
	formats: Record<string, Record<string, unknown>>
	sameTab: boolean
	enableSharing: boolean
	preview: boolean
	advanced: boolean
	cronChecker: boolean
	emailNotifications: boolean
	versionHistory: boolean
	limitGroups: string[]
	chat: boolean
	compactHeader: boolean
	feedback: boolean
	forcesave: boolean
	liveViewOnShare: boolean
	help: boolean
	reviewDisplay: string
	theme: string
	unknownAuthor: string
}>()

// Build defFormats and editFormats from the formats object
const defFormats = ref<Record<string, boolean>>(
	Object.fromEntries(
		Object.entries(props.formats)
			.filter(([, fmt]) => fmt.mime != null)
			.map(([name, fmt]) => [name, !!(fmt.def)]),
	),
)
const editFormats = ref<Record<string, boolean>>(
	Object.fromEntries(
		Object.entries(props.formats)
			.filter(([, fmt]) => fmt.editable)
			.map(([name, fmt]) => [name, !!(fmt.edit)]),
	),
)

// Computed filtered views of formats
const previewFormats = computed(() =>
	Object.entries(props.formats).filter(([, fmt]) => fmt.mime != null),
)
const editableFormats = computed(() =>
	Object.entries(props.formats).filter(([, fmt]) => fmt.editable),
)

const sameTab = ref(props.sameTab)
const enableSharing = ref(props.enableSharing)
const preview = ref(props.preview)
const advanced = ref(props.advanced)
const cronChecker = ref(props.cronChecker)
const emailNotifications = ref(props.emailNotifications)
const versionHistory = ref(props.versionHistory)
const limitGroups = ref<string[]>([...props.limitGroups])
const useGroups = ref(props.limitGroups.length > 0)
const chat = ref(props.chat)
const compactHeader = ref(props.compactHeader)
const feedback = ref(props.feedback)
const forcesave = ref(props.forcesave)
const liveViewOnShare = ref(props.liveViewOnShare)
const help = ref(props.help)
const reviewDisplay = ref(props.reviewDisplay)
const theme = ref(props.theme)
const unknownAuthor = ref(props.unknownAuthor ?? '')
const saving = ref(false)

watch(sameTab, (val) => {
	if (val) enableSharing.value = false
})

watch(useGroups, (val) => {
	if (!val) limitGroups.value = []
})

async function onClearHistory() {
	const confirmed = await showConfirmation({
		name: t('onlyoffice', 'Confirm metadata removal'),
		text: t('onlyoffice', 'Are you sure you want to clear metadata?'),
		labelReject: t('core', 'Cancel'),
		severity: 'info',
	})
	if (!confirmed) return

	saving.value = true
	try {
		await clearHistory()
		showSuccess(t('onlyoffice', 'All history successfully deleted'))
	} catch {
		showError(t('onlyoffice', 'Error'))
	} finally {
		saving.value = false
	}
}

async function save() {
	saving.value = true
	try {
		await saveCommonSettings({
			defFormats: defFormats.value,
			editFormats: editFormats.value,
			sameTab: sameTab.value,
			enableSharing: enableSharing.value,
			preview: preview.value,
			advanced: advanced.value,
			cronChecker: cronChecker.value,
			emailNotifications: emailNotifications.value,
			versionHistory: versionHistory.value,
			limitGroups: useGroups.value ? limitGroups.value : [],
			chat: chat.value,
			compactHeader: compactHeader.value,
			feedback: feedback.value,
			forcesave: forcesave.value,
			liveViewOnShare: liveViewOnShare.value,
			help: help.value,
			reviewDisplay: reviewDisplay.value,
			theme: theme.value,
			unknownAuthor: unknownAuthor.value.trim(),
		})
		showSuccess(t('onlyoffice', 'Common settings have been successfully updated'))
	} catch {
		showError(t('onlyoffice', 'Error'))
	} finally {
		saving.value = false
	}
}
</script>

<template>
	<div class="section section-onlyoffice section-onlyoffice-common">
		<h2>{{ t('onlyoffice', 'Common settings') }}</h2>

		<!-- Group access restriction -->
		<p>
			<input type="checkbox" class="checkbox" id="onlyoffice-groups" v-model="useGroups" />
			<label for="onlyoffice-groups">{{ t('onlyoffice', 'Allow the following groups to access the editors') }}</label>
		</p>
		<p class="block-inline">
			<NcSettingsSelectGroup
				v-if="useGroups"
				v-model="limitGroups"
				:label="t('core', 'Groups')"
			/>
		</p>

		<!-- Behaviour -->
		<p>
			<input type="checkbox" class="checkbox" id="onlyoffice-preview" v-model="preview" />
			<label for="onlyoffice-preview">{{ t('onlyoffice', 'Use ONLYOFFICE to generate a document preview (it will take up disk space)') }}</label>
		</p>

		<p>
			<input type="checkbox" class="checkbox" id="onlyoffice-same-tab" v-model="sameTab" />
			<label for="onlyoffice-same-tab">{{ t('onlyoffice', 'Open file in the same tab') }}</label>
		</p>

		<div v-show="!sameTab" id="onlyoffice-enable-sharing-block">
			<p>
				<input type="checkbox" class="checkbox" id="onlyoffice-enable-sharing" v-model="enableSharing" />
				<label for="onlyoffice-enable-sharing">{{ t('onlyoffice', 'Enable sharing (might increase editors loading time)') }}</label>
			</p>
		</div>

		<p>
			<input type="checkbox" class="checkbox" id="onlyoffice-advanced" v-model="advanced" />
			<label for="onlyoffice-advanced">{{ t('onlyoffice', 'Provide advanced document permissions using ONLYOFFICE Docs') }}</label>
		</p>

		<p class="onlyoffice-version-history">
			<div>
				<input type="checkbox" class="checkbox" id="onlyoffice-version-history" v-model="versionHistory" />
				<label for="onlyoffice-version-history">{{ t('onlyoffice', 'Keep metadata for each version once the document is edited (it will take up disk space)') }}</label>
			</div>
			<NcButton :disabled="saving" @click="onClearHistory">
				{{ t('onlyoffice', 'Clear') }}
			</NcButton>
		</p>

		<p>
			<input type="checkbox" class="checkbox" id="onlyoffice-cron-checker" v-model="cronChecker" />
			<label for="onlyoffice-cron-checker">{{ t('onlyoffice', 'Enable background connection check to the editors') }}</label>
		</p>

		<p>
			<input type="checkbox" class="checkbox" id="onlyoffice-email-notifications" v-model="emailNotifications" />
			<label for="onlyoffice-email-notifications">{{ t('onlyoffice', 'Enable e-mail notifications') }}</label>
		</p>

		<p>{{ t('onlyoffice', 'Unknown author display name') }}</p>
		<p><input id="onlyoffice-unknown-author" v-model="unknownAuthor" type="text" placeholder="" /></p>

		<!-- Default formats -->
		<p>{{ t('onlyoffice', 'The default application for opening the format') }}</p>
		<div class="onlyoffice-exts">
			<div v-for="[name] in previewFormats" :key="'def-' + name">
				<input type="checkbox" class="checkbox" :id="'onlyoffice-def-format' + name" v-model="defFormats[name]" />
				<label :for="'onlyoffice-def-format' + name">{{ name }}</label>
			</div>
		</div>

		<!-- Editable formats -->
		<p>{{ t('onlyoffice', 'Open the file for editing (due to format restrictions, the data might be lost when saving to the formats from the list below)') }}</p>
		<div class="onlyoffice-exts">
			<div v-for="[name] in editableFormats" :key="'edit-' + name">
				<input type="checkbox" class="checkbox" :id="'onlyoffice-edit-format' + name" v-model="editFormats[name]" />
				<label :for="'onlyoffice-edit-format' + name">{{ name }}</label>
			</div>
		</div>

		<br>

		<!-- Editor customization -->
		<h2>{{ t('onlyoffice', 'Editor customization settings') }}</h2>

		<p>
			<input type="checkbox" class="checkbox" id="onlyoffice-forcesave" v-model="forcesave" />
			<label for="onlyoffice-forcesave">{{ t('onlyoffice', 'Keep intermediate versions when editing (forcesave)') }}</label>
		</p>

		<p>
			<input type="checkbox" class="checkbox" id="onlyoffice-live-view-on-share" v-model="liveViewOnShare" />
			<label for="onlyoffice-live-view-on-share">{{ t('onlyoffice', 'Enable live-viewing mode when accessing file by public link') }}</label>
		</p>

		<p>
			{{ t('onlyoffice', 'The customization section allows personalizing the editor interface') }}
		</p>

		<p>
			<input type="checkbox" class="checkbox" id="onlyoffice-chat" v-model="chat" />
			<label for="onlyoffice-chat">{{ t('onlyoffice', 'Display Chat menu button') }}</label>
		</p>

		<p>
			<input type="checkbox" class="checkbox" id="onlyoffice-compact-header" v-model="compactHeader" />
			<label for="onlyoffice-compact-header">{{ t('onlyoffice', 'Display the header more compact') }}</label>
		</p>

		<p>
			<input type="checkbox" class="checkbox" id="onlyoffice-feedback" v-model="feedback" />
			<label for="onlyoffice-feedback">{{ t('onlyoffice', 'Display Feedback & Support menu button') }}</label>
		</p>

		<p>
			<input type="checkbox" class="checkbox" id="onlyoffice-help" v-model="help" />
			<label for="onlyoffice-help">{{ t('onlyoffice', 'Display Help menu button') }}</label>
		</p>

		<!-- Review display mode -->
		<p>
			{{ t('onlyoffice', 'REVIEW mode for viewing') }}
		</p>
		<div class="onlyoffice-tables">
			<div>
				<input type="radio" class="radio" id="onlyoffice-review-display-markup" v-model="reviewDisplay" value="markup" name="reviewDisplay" />
				<label for="onlyoffice-review-display-markup">{{ t('onlyoffice', 'Markup') }}</label>
			</div>
			<div>
				<input type="radio" class="radio" id="onlyoffice-review-display-final" v-model="reviewDisplay" value="final" name="reviewDisplay" />
				<label for="onlyoffice-review-display-final">{{ t('onlyoffice', 'Final') }}</label>
			</div>
			<div>
				<input type="radio" class="radio" id="onlyoffice-review-display-original" v-model="reviewDisplay" value="original" name="reviewDisplay" />
				<label for="onlyoffice-review-display-original">{{ t('onlyoffice', 'Original') }}</label>
			</div>
		</div>

		<!-- Theme -->
		<p>
			{{ t('onlyoffice', 'Default editor theme') }}
		</p>
		<div class="onlyoffice-tables">
			<div>
				<input type="radio" class="radio" id="onlyoffice-theme-theme-system" v-model="theme" value="theme-system" name="theme" />
				<label for="onlyoffice-theme-theme-system">{{ t('onlyoffice', 'Same as system') }}</label>
			</div>
			<div>
				<input type="radio" class="radio" id="onlyoffice-theme-default-light" v-model="theme" value="default-light" name="theme" />
				<label for="onlyoffice-theme-default-light">{{ t('onlyoffice', 'Light') }}</label>
			</div>
			<div>
				<input type="radio" class="radio" id="onlyoffice-theme-default-dark" v-model="theme" value="default-dark" name="theme" />
				<label for="onlyoffice-theme-default-dark">{{ t('onlyoffice', 'Dark') }}</label>
			</div>
		</div>

		<br>

		<p>
			<NcButton id="onlyoffice-common-save" :disabled="saving" @click="save" variant="primary">
				{{ t('onlyoffice', 'Save') }}
			</NcButton>
		</p>
	</div>
</template>

<style scoped>
#onlyoffice-enable-sharing-block {
    margin-left: 1.5em;
}

.onlyoffice-version-history {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.onlyoffice-exts {
    column-width: 100px;
    -moz-column-width: 100px;
    -webkit-column-width: 100px;
    margin-bottom: 1em;
}

</style>

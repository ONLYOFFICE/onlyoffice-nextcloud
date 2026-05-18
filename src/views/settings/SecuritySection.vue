<!--
  Copyright (C) Ascensio System SIA, 2009-2026

  This program is a free software product. You can redistribute it and/or
  modify it under the terms of the GNU Affero General Public License (AGPL)
  version 3 as published by the Free Software Foundation, together with the
  additional terms provided in the LICENSE file.

  This program is distributed WITHOUT ANY WARRANTY; without even the implied
  warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. For
  details, see the GNU AGPL at: https://www.gnu.org/licenses/agpl-3.0.html

  You can contact Ascensio System SIA by email at info@onlyoffice.com
  or by postal mail at 20A-6 Ernesta Birznieka-Upisha Street, Riga,
  LV-1050, Latvia, European Union.

  The interactive user interfaces in modified versions of the Program
  are required to display Appropriate Legal Notices in accordance with
  Section 5 of the GNU AGPL version 3.

  No trademark rights are granted under this License.

  All non-code elements of the Product, including illustrations,
  icon sets, and technical writing content, are licensed under the
  Creative Commons Attribution-ShareAlike 4.0 International License:
  https://creativecommons.org/licenses/by-sa/4.0/legalcode

  This license applies only to such non-code elements and does not
  modify or replace the licensing terms applicable to the Program's
  source code, which remains licensed under the GNU Affero General
  Public License v3.

  SPDX-License-Identifier: AGPL-3.0-only
-->
<script setup lang="ts">
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSelectTags from '@nextcloud/vue/components/NcSelectTags'
import NcSettingsSelectGroup from '@nextcloud/vue/components/NcSettingsSelectGroup'
import { saveSecuritySettings } from '../../services/SettingsService.ts'

interface WatermarkSettings {
	enabled: boolean
	text: string
	allGroups: boolean
	allGroupsList: string[]
	allTags: boolean
	allTagsList: number[]
	shareAll: boolean
	shareRead: boolean
	linkAll: boolean
	linkSecure: boolean
	linkRead: boolean
	linkTags: boolean
	linkTagsList: number[]
}

const props = defineProps<{
	plugins: boolean
	macros: boolean
	protection: string
	watermark: Record<string, unknown>
	tagsEnabled: boolean
}>()

const plugins = ref(props.plugins)
const macros = ref(props.macros)
const protection = ref(props.protection)
const saving = ref(false)

// Copy watermark, converting tag ID arrays from string[] to number[]
const watermark = ref<WatermarkSettings>({
	enabled: !!(props.watermark.enabled),
	text: (props.watermark.text as string) ?? '',
	allGroups: !!(props.watermark.allGroups),
	allGroupsList: (props.watermark.allGroupsList as string[] | undefined) ?? [],
	allTags: !!(props.watermark.allTags),
	allTagsList: ((props.watermark.allTagsList as string[] | undefined) ?? []).map(Number),
	shareAll: !!(props.watermark.shareAll),
	shareRead: !!(props.watermark.shareRead),
	linkAll: !!(props.watermark.linkAll),
	linkSecure: !!(props.watermark.linkSecure),
	linkRead: !!(props.watermark.linkRead),
	linkTags: !!(props.watermark.linkTags),
	linkTagsList: ((props.watermark.linkTagsList as string[] | undefined) ?? []).map(Number),
})

/**
 * Persists all security settings (watermark, plugins, macros, protection) to the backend.
 */
async function save() {
	saving.value = true
	try {
		await saveSecuritySettings({
			watermarks: {
				...watermark.value,
				// Convert tag ID arrays back to string[] for PHP
				allTagsList: watermark.value.allTagsList.map(String),
				linkTagsList: watermark.value.linkTagsList.map(String),
			},
			plugins: plugins.value,
			macros: macros.value,
			protection: protection.value,
		})
		showSuccess(t('onlyoffice', 'Security settings have been successfully updated'))
	} catch {
		showError(t('onlyoffice', 'Error'))
	} finally {
		saving.value = false
	}
}
</script>

<template>
	<div class="section section-onlyoffice section-onlyoffice-watermark">
		<h2>{{ t('onlyoffice', 'Security') }}</h2>

		<p>
			<input
				id="onlyoffice-plugins"
				v-model="plugins"
				type="checkbox"
				class="checkbox">
			<label for="onlyoffice-plugins">{{ t('onlyoffice', 'Enable plugins') }}</label>
		</p>

		<p>
			<input
				id="onlyoffice-macros"
				v-model="macros"
				type="checkbox"
				class="checkbox">
			<label for="onlyoffice-macros">{{ t('onlyoffice', 'Run document macros') }}</label>
		</p>

		<!-- Document protection -->
		<p class="onlyoffice-header">
			{{ t('onlyoffice', 'Enable document protection for') }}
		</p>
		<div class="onlyoffice-tables">
			<div>
				<input
					id="onlyoffice-protection-all"
					v-model="protection"
					type="radio"
					class="radio"
					value="all"
					name="protection">
				<label for="onlyoffice-protection-all">{{ t('onlyoffice', 'All users') }}</label>
			</div>
			<div>
				<input
					id="onlyoffice-protection-owner"
					v-model="protection"
					type="radio"
					class="radio"
					value="owner"
					name="protection">
				<label for="onlyoffice-protection-owner">{{ t('onlyoffice', 'Owner only') }}</label>
			</div>
		</div>

		<br>

		<!-- Watermark -->
		<p>
			{{ t('onlyoffice', 'Secure view enables you to secure documents by embedding a watermark') }}
		</p>
		<p>
			<input
				id="onlyoffice-watermark-enabled"
				v-model="watermark.enabled"
				type="checkbox"
				class="checkbox">
			<label for="onlyoffice-watermark-enabled">{{ t('onlyoffice', 'Enable watermarking') }}</label>
		</p>

		<div v-show="watermark.enabled">
			<br>

			<p>{{ t('onlyoffice', 'Watermark text') }}</p>

			<br>

			<p>
				{{ t('onlyoffice', 'Supported placeholders') }}: {userId}, {userDisplayName}, {email}, {date}, {themingName}
			</p>
			<p>
				<input
					id="onlyoffice-watermark-text"
					v-model="watermark.text"
					type="text"
					:placeholder="t('onlyoffice', 'DO NOT SHARE THIS') + ' {userId} {date}'">
			</p>

			<br>

			<!-- Tags -->
			<template v-if="tagsEnabled">
				<p>
					<input
						id="onlyoffice-watermark-all-tags"
						v-model="watermark.allTags"
						type="checkbox"
						class="checkbox">
					<label for="onlyoffice-watermark-all-tags">{{ t('onlyoffice', 'Show watermark on tagged files') }}</label>
				</p>
				<p class="block-inline">
					<NcSelectTags
						v-if="watermark.allTags"
						v-model="watermark.allTagsList"
						:multiple="true" />
				</p>
			</template>

			<!-- Groups -->
			<p>
				<input
					id="onlyoffice-watermark-all-groups"
					v-model="watermark.allGroups"
					type="checkbox"
					class="checkbox">
				<label for="onlyoffice-watermark-all-groups">{{ t('onlyoffice', 'Show watermark for users of groups') }}</label>
			</p>
			<p class="block-inline">
				<NcSettingsSelectGroup
					v-if="watermark.allGroups"
					v-model="watermark.allGroupsList"
					:label="t('core', 'Groups')" />
			</p>

			<!-- Share-based watermarks -->
			<p>
				<input
					id="onlyoffice-watermark-share-all"
					v-model="watermark.shareAll"
					type="checkbox"
					class="checkbox">
				<label for="onlyoffice-watermark-share-all">{{ t('onlyoffice', 'Show watermark for all shares') }}</label>
			</p>
			<!-- shareRead is hidden when shareAll is on (already implied) -->
			<p v-if="!watermark.shareAll">
				<input
					id="onlyoffice-watermark-share-read"
					v-model="watermark.shareRead"
					type="checkbox"
					class="checkbox">
				<label for="onlyoffice-watermark-share-read">{{ t('onlyoffice', 'Show watermark for read only shares') }}</label>
			</p>

			<br>

			<!-- Link-based watermarks -->
			<p>{{ t('onlyoffice', 'Link shares') }}</p>
			<p>
				<input
					id="onlyoffice-watermark-link-all"
					v-model="watermark.linkAll"
					type="checkbox"
					class="checkbox">
				<label for="onlyoffice-watermark-link-all">{{ t('onlyoffice', 'Show watermark for all link shares') }}</label>
			</p>
			<!-- link-specific options hidden when linkAll is on (already implies all) -->
			<template v-if="!watermark.linkAll">
				<p>
					<input
						id="onlyoffice-watermark-link-secure"
						v-model="watermark.linkSecure"
						type="checkbox"
						class="checkbox">
					<label for="onlyoffice-watermark-link-secure">{{ t('onlyoffice', 'Show watermark for download hidden shares') }}</label>
				</p>
				<p>
					<input
						id="onlyoffice-watermark-link-read"
						v-model="watermark.linkRead"
						type="checkbox"
						class="checkbox">
					<label for="onlyoffice-watermark-link-read">{{ t('onlyoffice', 'Show watermark for read only link shares') }}</label>
				</p>
				<template v-if="tagsEnabled">
					<p>
						<input
							id="onlyoffice-watermark-link-tags"
							v-model="watermark.linkTags"
							type="checkbox"
							class="checkbox">
						<label for="onlyoffice-watermark-link-tags">{{ t('onlyoffice', 'Show watermark on link shares with specific system tags') }}</label>
					</p>
					<p class="block-inline">
						<NcSelectTags
							v-if="watermark.linkTags"
							v-model="watermark.linkTagsList"
							:multiple="true" />
					</p>
				</template>
			</template>
		</div>

		<br>

		<p>
			<NcButton
				id="onlyoffice-security-save"
				:disabled="saving"
				variant="primary"
				@click="save">
				{{ t('onlyoffice', 'Save') }}
			</NcButton>
		</p>
	</div>
</template>

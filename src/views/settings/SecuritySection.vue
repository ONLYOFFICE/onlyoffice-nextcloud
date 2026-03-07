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
import { ref } from 'vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSettingsSelectGroup from '@nextcloud/vue/components/NcSettingsSelectGroup'
import NcSelectTags from '@nextcloud/vue/components/NcSelectTags'
import { saveSecuritySettings } from '../../services/SettingsService'

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
			<input type="checkbox" class="checkbox" id="onlyoffice-plugins" v-model="plugins" />
			<label for="onlyoffice-plugins">{{ t('onlyoffice', 'Enable plugins') }}</label>
		</p>

		<p>
			<input type="checkbox" class="checkbox" id="onlyoffice-macros" v-model="macros" />
			<label for="onlyoffice-macros">{{ t('onlyoffice', 'Run document macros') }}</label>
		</p>

		<!-- Document protection -->
		<p class="onlyoffice-header">
			{{ t('onlyoffice', 'Enable document protection for') }}
		</p>
		<div class="onlyoffice-tables">
			<div>
				<input type="radio" class="radio" id="onlyoffice-protection-all" v-model="protection" value="all" name="protection" />
				<label for="onlyoffice-protection-all">{{ t('onlyoffice', 'All users') }}</label>
			</div>
			<div>
				<input type="radio" class="radio" id="onlyoffice-protection-owner" v-model="protection" value="owner" name="protection" />
				<label for="onlyoffice-protection-owner">{{ t('onlyoffice', 'Owner only') }}</label>
			</div>
		</div>

		<br>

		<!-- Watermark -->
		<p>
			{{ t('onlyoffice', 'Secure view enables you to secure documents by embedding a watermark') }}
		</p>
		<p>
			<input type="checkbox" class="checkbox" id="onlyoffice-watermark-enabled" v-model="watermark.enabled" />
			<label for="onlyoffice-watermark-enabled">{{ t('onlyoffice', 'Enable watermarking') }}</label>
		</p>

		<div v-show="watermark.enabled">
			<br>

			<p>{{ t('onlyoffice', 'Watermark text') }}</p>

			<br>

			<p>
				{{ t('onlyoffice', 'Supported placeholders') }}: {userId}, {userDisplayName}, {email}, {date}, {themingName}
			</p>
			<p><input id="onlyoffice-watermark-text" v-model="watermark.text" type="text" :placeholder="t('onlyoffice', 'DO NOT SHARE THIS') + ' {userId} {date}'" /></p>

			<br>

			<!-- Tags -->
			<template v-if="tagsEnabled">
				<p>
					<input type="checkbox" class="checkbox" id="onlyoffice-watermark-all-tags" v-model="watermark.allTags" />
					<label for="onlyoffice-watermark-all-tags">{{ t('onlyoffice', 'Show watermark on tagged files') }}</label>
				</p>
				<p class="block-inline">
					<NcSelectTags
						v-if="watermark.allTags"
						v-model="watermark.allTagsList"
						:multiple="true"
					/>
				</p>
			</template>

			<!-- Groups -->
			<p>
				<input type="checkbox" class="checkbox" id="onlyoffice-watermark-all-groups" v-model="watermark.allGroups" />
				<label for="onlyoffice-watermark-all-groups">{{ t('onlyoffice', 'Show watermark for users of groups') }}</label>
			</p>
			<p class="block-inline">
				<NcSettingsSelectGroup
					v-if="watermark.allGroups"
					v-model="watermark.allGroupsList"
					:label="t('core', 'Groups')"
				/>
			</p>

			<!-- Share-based watermarks -->
			<p>
				<input type="checkbox" class="checkbox" id="onlyoffice-watermark-share-all" v-model="watermark.shareAll" />
				<label for="onlyoffice-watermark-share-all">{{ t('onlyoffice', 'Show watermark for all shares') }}</label>
			</p>
			<!-- shareRead is hidden when shareAll is on (already implied) -->
			<p v-if="!watermark.shareAll">
				<input type="checkbox" class="checkbox" id="onlyoffice-watermark-share-read" v-model="watermark.shareRead" />
				<label for="onlyoffice-watermark-share-read">{{ t('onlyoffice', 'Show watermark for read only shares') }}</label>
			</p>

			<br>

			<!-- Link-based watermarks -->
			<p>{{ t('onlyoffice', 'Link shares') }}</p>
			<p>
				<input type="checkbox" class="checkbox" id="onlyoffice-watermark-link-all" v-model="watermark.linkAll" />
				<label for="onlyoffice-watermark-link-all">{{ t('onlyoffice', 'Show watermark for all link shares') }}</label>
			</p>
			<!-- link-specific options hidden when linkAll is on (already implies all) -->
			<template v-if="!watermark.linkAll">
				<p>
					<input type="checkbox" class="checkbox" id="onlyoffice-watermark-link-secure" v-model="watermark.linkSecure" />
					<label for="onlyoffice-watermark-link-secure">{{ t('onlyoffice', 'Show watermark for download hidden shares') }}</label>
				</p>
				<p>
					<input type="checkbox" class="checkbox" id="onlyoffice-watermark-link-read" v-model="watermark.linkRead" />
					<label for="onlyoffice-watermark-link-read">{{ t('onlyoffice', 'Show watermark for read only link shares') }}</label>
				</p>
				<template v-if="tagsEnabled">
					<p>
						<input type="checkbox" class="checkbox" id="onlyoffice-watermark-link-tags" v-model="watermark.linkTags" />
						<label for="onlyoffice-watermark-link-tags">{{ t('onlyoffice', 'Show watermark on link shares with specific system tags') }}</label>
					</p>
					<p class="block-inline">
						<NcSelectTags
							v-if="watermark.linkTags"
							v-model="watermark.linkTagsList"
							:multiple="true"
						/>
					</p>
				</template>
			</template>
		</div>

		<br>

		<p>
			<NcButton :disabled="saving" @click="save" variant="primary">
				{{ t('onlyoffice', 'Save') }}
			</NcButton>
		</p>
	</div>
</template>

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
import { ref } from 'vue'
import { t } from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'
import type { ShareExtra } from '../../services/ShareService'
import { getShares, setShares } from '../../services/ShareService'
import ShareItem from './ShareItem.vue'
import { Permissions } from '../../utils/permissions'
import { getFileExtension } from '../../utils/files'
import HintPopover from '../../components/HintPopover.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'

/**
 * Switches the sidebar to the native Sharing tab.
 */
function openSharingTab() {
	const OCA = (window as unknown as { OCA?: { Files?: { Sidebar?: { setActiveTab?: (id: string) => void } } } }).OCA
	OCA?.Files?.Sidebar?.setActiveTab?.('sharing')
}

const formats = loadState<{ formats: Record<string, Record<string, boolean>> }>('onlyoffice', 'settings', { formats: {} }).formats ?? {}

const fileId = ref<number | null>(null)
const collection = ref<ShareExtra[]>([])
const format = ref<Record<string, boolean>>({})
const saving = ref(false)
const loading = ref(false)

/**
 * Loads sharing permissions for the given file into the tab.
 * @param {{ id: number, name: string }} fileInfo the file to display sharing settings for
 * @param {number} fileInfo.id file ID used to fetch existing shares
 * @param {string} fileInfo.name file name used to resolve the format configuration
 */
async function update(fileInfo: { id: number; name: string }) {
	fileId.value = fileInfo.id
	format.value = formats[getFileExtension(fileInfo.name)] ?? {}
	loading.value = true
	collection.value = await getShares(fileInfo.id)
	loading.value = false
}

defineExpose({ update })

/**
 * Computes a combined permissions bitmask from a map of permission flags,
 * enforcing mutual-exclusion rules (e.g. Comment requires no Review or ModifyFilter).
 * @param {Record<number, boolean>} values map of permission constant to enabled state
 * @return {number} combined permissions bitmask
 */
function computePermissions(values: Record<number, boolean>): number {
	let p = Permissions.None
	if (values[Permissions.Review]) p |= Permissions.Review
	if (values[Permissions.Comment]
		&& !(p & Permissions.Review)
		&& !(p & Permissions.ModifyFilter)) {
		p |= Permissions.Comment
	}
	if (values[Permissions.FillForms] && !(p & Permissions.Review)) p |= Permissions.FillForms
	if (values[Permissions.ModifyFilter] && !(p & Permissions.Comment)) p |= Permissions.ModifyFilter
	return p
}

/**
 * Handles a permission toggle for a share, recomputes the bitmask, and persists the update.
 * @param {ShareExtra} extra the share entry whose permissions are being changed
 * @param {number} changedKey the permission constant that was toggled
 * @param {boolean} changedValue the new state of the toggled permission
 */
async function onPermissionChange(extra: ShareExtra, changedKey: number, changedValue: boolean) {
	const values: Record<number, boolean> = {
		[Permissions.Review]: (Permissions.Review & extra.permissions) === Permissions.Review,
		[Permissions.Comment]: (Permissions.Comment & extra.permissions) === Permissions.Comment,
		[Permissions.FillForms]: (Permissions.FillForms & extra.permissions) === Permissions.FillForms,
		[Permissions.ModifyFilter]: (Permissions.ModifyFilter & extra.permissions) === Permissions.ModifyFilter,
	}
	values[changedKey] = changedValue

	const permissions = computePermissions(values)
	saving.value = true
	try {
		const updated = await setShares({
			extraId: extra.id,
			shareId: extra.share_id,
			fileId: fileId.value as number,
			permissions,
		})
		const item = collection.value.find(i => i.share_id === updated.share_id)
		if (item) {
			item.id = updated.id
			item.permissions = updated.permissions
			item.available = updated.available
		}
	} finally {
		saving.value = false
	}
}

</script>

<template>
	<div class="onlyoffice-share-tab">
		<div v-if="loading" class="onlyoffice-share-loading icon-loading" />
		<template v-else>
			<NcEmptyContent v-if="collection.length === 0"
				class="onlyoffice-share-empty"
				:name="t('onlyoffice', 'No users here')"
				:description="t('onlyoffice', 'Enable Custom permissions for users on the Sharing tab, ensuring Edit is turned on and Share is turned off')">
				<template #action>
					<NcButton variant="primary" @click="openSharingTab">
						{{ t('onlyoffice', 'Share this file') }}
					</NcButton>
				</template>
			</NcEmptyContent>
			<template v-else>
				<div class="onlyoffice-share-header">
					<span>{{ t('onlyoffice', 'Provide advanced document permissions using ONLYOFFICE Docs') }}</span>
					<HintPopover :label="t('onlyoffice', 'Advanced permissions explanation')">
						<p>{{ t('onlyoffice', 'Limit access permissions for files shared with Custom permissions (Edit enabled, Share disabled) or via a public link with edit permission:') }}</p>
						<ul>
							<li><strong>{{ t('onlyoffice', 'Document') }}</strong> — {{ t('onlyoffice', 'Review/Comment') }}</li>
							<li><strong>{{ t('onlyoffice', 'Spreadsheet') }}</strong> — {{ t('onlyoffice', 'Comment/Global filter') }}</li>
							<li><strong>{{ t('onlyoffice', 'Presentation') }}</strong> — {{ t('onlyoffice', 'Comment') }}</li>
							<li><strong>{{ t('onlyoffice', 'PDF') }}</strong> — {{ t('onlyoffice', 'Comment/Form filling') }}</li>
						</ul>
					</HintPopover>
				</div>
				<ul>
					<ShareItem v-for="extra in collection"
						:key="extra.share_id"
						:extra="extra"
						:format="format"
						:disabled="saving"
						@change="(key: number, val: boolean) => onPermissionChange(extra, key, val)" />
				</ul>
			</template>
		</template>
	</div>
</template>

<style scoped>
.onlyoffice-share-loading,
.onlyoffice-share-empty {
	position: absolute;
	inset: 0;
	min-height: 50vh;
}

.onlyoffice-share-header {
	display: flex;
	align-items: center;
}
</style>

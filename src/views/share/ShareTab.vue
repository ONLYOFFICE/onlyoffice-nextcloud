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
import type { INode } from '@nextcloud/files'
import type { ShareExtra } from '../../services/ShareService.ts'

import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { ref, watch } from 'vue'
import ShareItem from './ShareItem.vue'
import { getShares, setShares } from '../../services/ShareService.ts'
import { getFileExtension } from '../../utils/files.ts'
import { Permissions } from '../../utils/permissions.ts'

const props = defineProps<{
	node: INode
	active: boolean
}>()

const formats = loadState<{ formats: Record<string, Record<string, boolean>> }>('onlyoffice', 'settings', { formats: {} }).formats ?? {}

const fileId = ref<string | null>(null)
const collection = ref<ShareExtra[]>([])
const format = ref<Record<string, boolean>>({})
const saving = ref(false)
const loading = ref(false)

watch(() => [props.node, props.active], async () => {
	if (!props.active || !props.node) {
		return
	}
	const id = props.node.id
	if (id === undefined || id === null) {
		return
	}
	fileId.value = id
	format.value = formats[getFileExtension(props.node.basename)] ?? {}
	loading.value = true
	collection.value = await getShares(id)
	loading.value = false
}, { immediate: true })

/**
 * Computes a combined permissions bitmask from a map of permission flags,
 * enforcing mutual-exclusion rules (e.g. Comment requires no Review or ModifyFilter).
 *
 * @param values map of permission constant to enabled state
 * @return combined permissions bitmask
 */
function computePermissions(values: Record<number, boolean>): number {
	let p = Permissions.None
	if (values[Permissions.Review]) {
		p |= Permissions.Review
	}
	if (values[Permissions.Comment]
		&& !(p & Permissions.Review)
		&& !(p & Permissions.ModifyFilter)) {
		p |= Permissions.Comment
	}
	if (values[Permissions.FillForms] && !(p & Permissions.Review)) {
		p |= Permissions.FillForms
	}
	if (values[Permissions.ModifyFilter] && !(p & Permissions.Comment)) {
		p |= Permissions.ModifyFilter
	}
	return p
}

/**
 * Handles a permission toggle for a share, recomputes the bitmask, and persists the update.
 *
 * @param extra the share entry whose permissions are being changed
 * @param changedKey the permission constant that was toggled
 * @param changedValue the new state of the toggled permission
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
			fileId: fileId.value as string,
			permissions,
		})
		const item = collection.value.find((i) => i.share_id === updated.share_id)
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
	<div :class="{ 'icon-loading': loading }">
		<template v-if="!loading">
			<div>{{ t('onlyoffice', 'Provide advanced document permissions using ONLYOFFICE Docs') }}</div>
			<ul>
				<ShareItem
					v-for="extra in collection"
					:key="extra.share_id"
					:extra="extra"
					:format="format"
					:disabled="saving"
					@change="(key: number, val: boolean) => onPermissionChange(extra, key, val)" />
			</ul>
		</template>
	</div>
</template>

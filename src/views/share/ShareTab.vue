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
import { t } from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'
import type { ShareExtra } from '../../services/ShareService'
import { getShares, setShares } from '../../services/ShareService'
import ShareItem from './ShareItem.vue'
import { Permissions } from '../../utils/permissions'
import { getFileExtension } from '../../utils/files'

const formats = loadState<{ formats: Record<string, Record<string, boolean>> }>('onlyoffice', 'settings', { formats: {} }).formats ?? {}

const fileId = ref<number | null>(null)
const collection = ref<ShareExtra[]>([])
const format = ref<Record<string, boolean>>({})
const saving = ref(false)
const loading = ref(false)

async function update(fileInfo: { id: number; name: string }) {
	fileId.value = fileInfo.id
	format.value = formats[getFileExtension(fileInfo.name)] ?? {}
	loading.value = true
	collection.value = await getShares(fileInfo.id)
	loading.value = false
}

defineExpose({ update })

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
					@change="(key: number, val: boolean) => onPermissionChange(extra, key, val)"
				/>
			</ul>
		</template>
	</div>
</template>

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
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import { ShareType } from '@nextcloud/sharing'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import type { ShareExtra } from '../../services/ShareService'
import { Permissions } from '../../utils/permissions'
import { generateFilePath } from '@nextcloud/router'

const props = defineProps<{
	extra: ShareExtra
	format: Record<string, boolean>
	disabled: boolean
}>()

const emit = defineEmits<{
	(e: 'change', key: number, value: boolean): void
}>()

const name = computed(() => {
	if (props.extra.type === ShareType.Link) return t('onlyoffice', 'Share link')
	if (props.extra.type === ShareType.Group) return `${props.extra.shareWith} (${t('onlyoffice', 'group')})`
	if (props.extra.type === ShareType.Room) return `${props.extra.shareWith} (${t('onlyoffice', 'conversation')})`
	return props.extra.shareWithName
})

const isUserShare = computed(() =>
	props.extra.type !== ShareType.Link
	&& props.extra.type !== ShareType.Group
	&& props.extra.type !== ShareType.Room,
)

const attributes = computed(() => {
	const attrs: Array<{ key: number; label: string; checked: boolean }> = []
	const { available, permissions } = props.extra
	if (props.format.review && (Permissions.Review & available) === Permissions.Review) {
		attrs.push({ key: Permissions.Review, label: t('onlyoffice', 'Review only'), checked: (Permissions.Review & permissions) === Permissions.Review })
	}
	if (props.format.comment && (Permissions.Comment & available) === Permissions.Comment) {
		attrs.push({ key: Permissions.Comment, label: t('onlyoffice', 'Comment only'), checked: (Permissions.Comment & permissions) === Permissions.Comment })
	}
	if (props.format.fillForms && (Permissions.FillForms & available) === Permissions.FillForms) {
		attrs.push({ key: Permissions.FillForms, label: t('onlyoffice', 'Form filling'), checked: (Permissions.FillForms & permissions) === Permissions.FillForms })
	}
	if (props.format.modifyFilter && (Permissions.ModifyFilter & available) === Permissions.ModifyFilter) {
		attrs.push({ key: Permissions.ModifyFilter, label: t('onlyoffice', 'Global filter'), checked: (Permissions.ModifyFilter & permissions) === Permissions.ModifyFilter })
	}
	return attrs
})
</script>

<template>
	<NcListItem :name="name" :force-display-actions="true">
		<template #icon>
			<div v-if="extra.type === ShareType.Link" class="onlyoffice-share-link-avatar">
				<img :src="generateFilePath('onlyoffice', 'img', 'public.svg')">
			</div>
			<NcAvatar v-else
				:user="isUserShare ? extra.shareWith : undefined"
				:is-guest="!isUserShare"
				:display-name="extra.shareWith"
				:size="32"
				:disable-menu="true"
				:disable-tooltip="true"
				:show-user-status="false" />
		</template>
		<template #actions>
			<NcActionCheckbox v-for="attr in attributes"
				:key="attr.key"
				:model-value="attr.checked"
				:disabled="disabled"
				@check="emit('change', attr.key, true)"
				@uncheck="emit('change', attr.key, false)">
				{{ attr.label }}
			</NcActionCheckbox>
		</template>
	</NcListItem>
</template>

<style scoped>
.onlyoffice-share-link-avatar {
    height: 32px;
    width: 32px;
    background: var(--color-background-dark);
    align-items: center;
    display: flex;
    justify-content: center;
	border-radius: 50%;
}

.onlyoffice-share-link-avatar img {
    height: 16px;
    width: 16px;
}
</style>

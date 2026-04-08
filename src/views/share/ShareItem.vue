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
import type { ShareExtra } from '../../services/ShareService.ts'

import { t } from '@nextcloud/l10n'
import { generateFilePath } from '@nextcloud/router'
import { ShareType } from '@nextcloud/sharing'
import { computed } from 'vue'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import { Permissions } from '../../utils/permissions.ts'

const props = defineProps<{
	extra: ShareExtra
	format: Record<string, boolean>
	disabled: boolean
}>()

const emit = defineEmits<{
	(e: 'change', key: number, value: boolean): void
}>()

const name = computed(() => {
	if (props.extra.type === ShareType.Link) {
		return t('onlyoffice', 'Share link')
	}
	if (props.extra.type === ShareType.Group) {
		return `${props.extra.shareWith} (${t('onlyoffice', 'group')})`
	}
	if (props.extra.type === ShareType.Room) {
		return `${props.extra.shareWith} (${t('onlyoffice', 'conversation')})`
	}
	return props.extra.shareWithName
})

const isUserShare = computed(() => props.extra.type !== ShareType.Link
	&& props.extra.type !== ShareType.Group
	&& props.extra.type !== ShareType.Room)

const attributes = computed(() => {
	const attrs: Array<{ key: number, label: string, checked: boolean }> = []
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
	<NcListItem :name="name" :forceDisplayActions="true">
		<template #icon>
			<div v-if="extra.type === ShareType.Link" class="onlyoffice-share-link-avatar">
				<img :src="generateFilePath('onlyoffice', 'img', 'public.svg')">
			</div>
			<NcAvatar
				v-else
				:user="isUserShare ? extra.shareWith : undefined"
				:isGuest="!isUserShare"
				:displayName="extra.shareWith"
				:size="32"
				:disableMenu="true"
				:disableTooltip="true"
				:showUserStatus="false" />
		</template>
		<template #actions>
			<NcActionCheckbox
				v-for="attr in attributes"
				:key="attr.key"
				:modelValue="attr.checked"
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

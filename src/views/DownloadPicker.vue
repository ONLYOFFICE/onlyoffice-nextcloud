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
<template>
	<NcDialog class="onlyoffice-download-picker"
		:name="t('onlyoffice', 'Download as')"
		:buttons="buttons"
		@update:open="$emit('close', null)">
		<div class="onlyoffice-download-container">
			<p>{{ t('onlyoffice', 'Choose a format to convert {fileName}', { fileName }) }}</p>
			<select id="onlyoffice-download-select" v-model="selectedFormat">
				<option :value="extension">
					{{ t('onlyoffice', 'Origin format') }}
				</option>
				<option v-for="ext in saveasFormats" :key="ext" :value="ext">
					{{ ext }}
				</option>
			</select>
		</div>
	</NcDialog>
</template>

<script setup lang="ts">
import NcDialog from '@nextcloud/vue/components/NcDialog'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { computed, ref } from 'vue'

declare const appName: string

const props = defineProps<{
	fileName: string
	fileId: number | string
	extension: string
	saveasFormats: string[]
}>()

const emit = defineEmits<{
	close: [value: boolean | null]
}>()

const selectedFormat = ref(props.extension)

const buttons = computed(() => [
	{
		label: t('core', 'Cancel'),
		variant: 'secondary',
		size: 'large',
		callback: () => emit('close', null),
	},
	{
		label: t('onlyoffice', 'Download'),
		variant: 'primary',
		size: 'large',
		callback: () => {
			location.href = generateUrl('apps/' + appName + '/downloadas?fileId={fileId}&toExtension={toExtension}', {
				fileId: props.fileId,
				toExtension: selectedFormat.value,
			})
			emit('close', true)
		},
	},
])
</script>

<style scoped lang="scss">
.onlyoffice-download-picker :deep(.modal-container) {
	width: auto !important;
	padding: 24px !important;
}

.onlyoffice-download-picker :deep(.dialog__name) {
	margin-top: 24px;
	font-size: 1.8em;
	text-align: left;
}

.onlyoffice-download-picker :deep(.dialog__content) {
	padding-inline-end: 0;
}

.onlyoffice-download-picker :deep(.dialog__actions) {
	justify-content: space-between;
	margin-block: 0 !important;
	padding-inline-end: 0;
	padding: 0;
	padding-top: 10px;
}

.onlyoffice-download-container {
	display: flex;
	align-items: center;
	column-gap: 10px;

	select {
		cursor: pointer;
	}
}
</style>

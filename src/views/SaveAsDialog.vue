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
<template>
	<NcDialog
		class="onlyoffice-save-as"
		:name="t('onlyoffice', 'Save as')"
		:buttons="buttons"
		@update:open="emit('close', null)">
		<div class="onlyoffice-save-as__content">
			<div class="onlyoffice-save-as__name">
				<NcTextField
					v-model="baseName"
					:label="t('onlyoffice', 'File name')"
					:error="!!error"
					:helperText="error"
					@keydown.enter="save" />
				<span v-if="extension" class="onlyoffice-save-as__extension">.{{ extension }}</span>
			</div>
			<p class="onlyoffice-save-as__folder">
				{{ t('onlyoffice', 'Save in {folder}', { folder: dir }) }}
			</p>
		</div>
	</NcDialog>
</template>

<script setup lang="ts">
import type { SaveAsTarget } from '../utils/saveAs.ts'

import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { getFileExtension } from '../utils/files.ts'

const props = defineProps<{
	name: string
	dir: string
}>()

const emit = defineEmits<{
	close: [value: SaveAsTarget | null]
}>()

const extension = getFileExtension(props.name)

const dir = props.dir || '/'
const baseName = ref(splitName(props.name))

const error = computed(() => {
	if (baseName.value.trim() === '') {
		return t('onlyoffice', 'File name cannot be empty')
	}

	if (baseName.value.includes('/')) {
		return t('onlyoffice', 'File name cannot contain "/"')
	}

	return ''
})

const buttons = computed(() => [
	{
		label: t('core', 'Cancel'),
		variant: 'secondary',
		size: 'large',
		callback: () => emit('close', null),
	},
	{
		label: t('onlyoffice', 'Save'),
		variant: 'primary',
		size: 'large',
		disabled: !!error.value,
		callback: () => save(),
	},
])

/**
 * @param name file name to take the extension off
 * @return the file name without its extension
 */
function splitName(name: string): string {
	return extension ? name.slice(0, name.length - extension.length - 1) : name
}

/**
 * Close the dialog with the chosen target
 */
function save() {
	if (error.value) {
		return
	}

	emit('close', {
		dir,
		name: baseName.value.trim() + (extension ? '.' + extension : ''),
	})
}
</script>

<style scoped lang="scss">
.onlyoffice-save-as__content {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding-block-end: 12px;
}

.onlyoffice-save-as__name {
	display: flex;
	align-items: center;
	gap: 6px;
}

.onlyoffice-save-as__extension {
	color: var(--color-text-maxcontrast);
}

.onlyoffice-save-as__folder {
	display: flex;
	align-items: center;
	gap: 12px;
}

.onlyoffice-save-as__path {
	overflow: hidden;
	flex: 1;
	white-space: nowrap;
	text-overflow: ellipsis;
	color: var(--color-text-maxcontrast);
}
</style>

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
	<NcDialog class="new-pdf-form-dialog"
		:name="t('onlyoffice', 'New PDF form')"
		:buttons="buttons"
		is-form
		@submit.prevent="handleCreate"
		@update:open="$emit('close', null)">
		<div class="new-pdf-dialog__form">
			<NcTextField ref="input"
				v-model="filename"
				:label="t('files', 'Filename')"
				:error="validityMessage !== ''"
				:helper-text="validityMessage" />
		</div>
	</NcDialog>
</template>

<script setup lang="ts">
import type { ButtonType, ButtonVariant } from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { InvalidFilenameError, InvalidFilenameErrorReason, getUniqueName, validateFilename } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import { computed, onMounted, ref, watch } from 'vue'

interface DialogButton {
	label: string
	variant: ButtonVariant
	type?: ButtonType
	disabled?: boolean
	callback?: () => unknown
}

const props = withDefaults(defineProps<{
	name: string
	otherNames?: string[]
}>(), {
	otherNames: () => [],
})

const emit = defineEmits<{
	close: [value: { name: string } | { back: true } | null]
}>()

const input = ref<InstanceType<typeof NcTextField>>()
const filename = ref(getUniqueName(props.name, props.otherNames))

const normalizedName = computed(() => {
	const trimmed = filename.value.trim()
	if (/\.pdf$/i.test(trimmed)) {
		return trimmed
	}
	return trimmed.replace(/\.[^./\\]+$/, '') + '.pdf'
})

/**
 * Validity message for the current filename (empty if valid).
 */
const validityMessage = computed(() => {
	const name = normalizedName.value

	if (name === '.pdf' || name.trim() === '') {
		return t('files', 'Filename must not be empty.')
	}

	if (props.otherNames.includes(name)) {
		return t('files', 'This name is already in use.')
	}

	try {
		validateFilename(name)
		return ''
	} catch (error) {
		if (!(error instanceof InvalidFilenameError)) {
			throw error
		}

		switch (error.reason) {
		case InvalidFilenameErrorReason.Character:
			return t('files', '"{char}" is not allowed inside a filename.', { char: error.segment })
		case InvalidFilenameErrorReason.ReservedName:
			return t('files', '"{segment}" is a reserved name and not allowed for filenames.', { segment: error.segment })
		case InvalidFilenameErrorReason.Extension:
			return t('files', 'Filenames must not end with "{extension}".', { extension: error.segment })
		default:
			return t('files', 'Invalid filename.')
		}
	}
})

const isValid = computed(() => validityMessage.value === '')

/**
 * Closes the dialog with the normalized filename, unless it is currently invalid.
 */
function handleCreate() {
	if (!isValid.value) {
		return
	}
	emit('close', { name: normalizedName.value })
}

const buttons = computed<DialogButton[]>(() => [
	{
		label: t('core', 'Back'),
		variant: 'secondary',
		callback: () => emit('close', { back: true }),
	},
	{
		label: t('files', 'Create'),
		variant: 'primary',
		type: 'submit',
		disabled: !isValid.value,
	},
])

// Recompute a unique default name if the surrounding folder content changes
watch(() => [props.name, props.otherNames], () => {
	filename.value = getUniqueName(props.name, props.otherNames)
})

onMounted(() => {
	input.value?.focus()
})
</script>

<style scoped>
.new-pdf-dialog__form {
	/* Reserve space for the validation message so the dialog does not jump */
	min-height: calc(2 * var(--default-clickable-area));
}

@media only screen and (max-width: 512px) {
	.new-pdf-form-dialog :deep(.modal-wrapper .modal-container) {
		width: fit-content;
		height: unset;
		max-height: 90%;
		position: relative;
		top: unset;
		border-radius: var(--border-radius-element);
	}
}
</style>

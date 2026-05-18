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
	<ul class="onlyoffice-template-container">
		<TemplateItem
			v-for="template in templates"
			:key="template.id"
			:template="template"
			@delete="handleDelete" />
	</ul>
</template>

<script setup lang="ts">
import type { Template } from '../types.ts'

import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { onMounted, onUnmounted, ref } from 'vue'
import TemplateItem from '../components/TemplateItem.vue'
import { addTemplate, deleteTemplate, getTemplates } from '../services/TemplateService.ts'

const templates = ref<Template[]>([])

/**
 *
 * @param event
 */
async function handleAdd(event: Event) {
	const input = event.target as HTMLInputElement
	const file = input.files?.[0]
	if (!file) {
		return
	}
	input.value = ''

	const response = await addTemplate(file)
	if ('error' in response) {
		showError(t('onlyoffice', 'Error') + ': ' + response.error)
		return
	}
	templates.value.push(response)
	showSuccess(t('onlyoffice', 'Template successfully added'))
}

/**
 *
 * @param id
 */
async function handleDelete(id: number) {
	const response = await deleteTemplate(id)
	if ('error' in response) {
		showError(t('onlyoffice', 'Error') + ': ' + response.error)
		return
	}
	const idx = templates.value.findIndex((item) => item.id === id)
	if (idx !== -1) {
		templates.value.splice(idx, 1)
	}
	showSuccess(t('onlyoffice', 'Template successfully deleted'))
}

onMounted(async () => {
	templates.value = await getTemplates()
	document.getElementById('onlyofficeAddTemplate')?.addEventListener('change', handleAdd)
})

onUnmounted(() => {
	document.getElementById('onlyofficeAddTemplate')?.removeEventListener('change', handleAdd)
})
</script>

<style scoped>
.onlyoffice-template-container li {
    margin-bottom: 10px;
}
</style>

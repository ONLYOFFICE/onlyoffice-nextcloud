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
	<ul class="onlyoffice-template-container">
		<TemplateItem v-for="template in templates"
			:key="template.id"
			:template="template"
			@delete="handleDelete" />
	</ul>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import TemplateItem from '../components/TemplateItem.vue'
import { getTemplates, addTemplate, deleteTemplate } from '../services/TemplateService'
import type { Template } from '../types'

const templates = ref<Template[]>([])

const handleAdd = async (event: Event) => {
	const input = event.target as HTMLInputElement
	const file = input.files?.[0]
	if (!file) return
	input.value = ''

	const response = await addTemplate(file)
	if ('error' in response) {
		showError(t('onlyoffice', 'Error') + ': ' + response.error)
		return
	}
	templates.value.push(response)
	showSuccess(t('onlyoffice', 'Template successfully added'))
}

const handleDelete = async (id: number) => {
	const response = await deleteTemplate(id)
	if ('error' in response) {
		showError(t('onlyoffice', 'Error') + ': ' + response.error)
		return
	}
	const idx = templates.value.findIndex(item => item.id === id)
	if (idx !== -1) templates.value.splice(idx, 1)
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

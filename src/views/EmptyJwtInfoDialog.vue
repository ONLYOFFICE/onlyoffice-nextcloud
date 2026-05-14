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
	<NcDialog class="empty-jwt-info-dialog"
		:name="dialogName"
		:buttons="buttons"
		@update:open="$emit('close', false)">
		<div class="onlyoffice-popup-info">
			<p>{{ successText }}</p>
			<p v-html="warningHtml" />
		</div>
	</NcDialog>
</template>

<script setup>
import NcDialog from '@nextcloud/vue/components/NcDialog'
import { t } from '@nextcloud/l10n'
import { computed } from 'vue'

const emit = defineEmits(['close'])

const successText = t('onlyoffice', 'Server settings have been successfully updated')
const dialogName = t('onlyoffice', 'Info')
const buttons = [
	{
		label: t('core', 'Ok'),
		variant: 'primary',
		callback: () => emit('close', true),
	},
]

const warningHtml = computed(() => {
	const securityUrl = 'https://api.onlyoffice.com/docs/docs-api/get-started/how-it-works/security/'
	return t(
		'onlyoffice',
		'To ensure the security of important parameters in ONLYOFFICE Docs requests, please set a Secret Key on the Settings page. To learn more, <a href="{url}" target="_blank">click here</a>.',
		{ url: securityUrl },
		{ escape: false, sanitize: false },
	)
})
</script>

<style scoped lang="scss">
.onlyoffice-popup-info {
    display: flex;
    flex-direction: column;
    row-gap: 12px;
    padding: 13px;
}

.onlyoffice-popup-info :deep(a) {
    text-decoration: underline;
}

.empty-jwt-info-dialog :deep(.modal-container) {
	width: 520px;
}
</style>

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

<script>
import NcDialog from '@nextcloud/vue/components/NcDialog'
import { t } from '@nextcloud/l10n'

export default {
	name: 'EmptyJwtInfoDialog',

	components: { NcDialog },

	emits: ['close'],

	data() {
		return {
			successText: t('onlyoffice', 'Server settings have been successfully updated'),
			dialogName: t('onlyoffice', 'Info'),
			buttons: [
				{
					label: t('core', 'Ok'),
					variant: 'primary',
					callback: () => this.$emit('close', true),
				},
			],
		}
	},

	computed: {
		warningHtml() {
			const securityUrl = 'https://api.onlyoffice.com/docs/docs-api/get-started/how-it-works/security/'
			return t(
				'onlyoffice',
				'To ensure the security of important parameters in ONLYOFFICE Docs requests, please set a Secret Key on the Settings page. To learn more, <a href="{url}" target="_blank">click here</a>.',
				{ url: securityUrl },
				{ escape: false, sanitize: false },
			)
		},
	},
}
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

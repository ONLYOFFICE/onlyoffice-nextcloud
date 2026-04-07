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
import { t } from '@nextcloud/l10n'
</script>

<template>
	<div>
		<h2>ONLYOFFICE</h2>

		<div class="onlyoffice-description">
			<h1>{{ t('onlyoffice', 'Welcome to ONLYOFFICE Docs!') }}</h1>
			<p>{{ t('onlyoffice', 'Edit and collaborate on text documents, spreadsheets, presentations, and PDFs within Nextcloud using ONLYOFFICE Docs.') }}</p>
			<div class="useful-links">
				<a href="https://helpcenter.onlyoffice.com/integration/nextcloud.aspx" target="_blank">{{ t('onlyoffice', 'Learn more') }}</a>
				<a href="https://feedback.onlyoffice.com/forums/966080-your-voice-matters?category_id=519288" target="_blank">{{ t('onlyoffice', 'Suggest a feature') }}</a>
			</div>
		</div>
	</div>
</template>

<style scoped>
.onlyoffice-description {
	display: flex;
	flex-direction: column;
	row-gap: 11px;
	margin-bottom: 24px;
}

.onlyoffice-description h1 {
	font-size: 32px;
	font-weight: 600;
}

.onlyoffice-description p {
	font-size: 15px;
	font-weight: 400;
	margin-bottom: 0 !important;
}

.useful-links {
	display: flex;
	column-gap: 24px;
}

.useful-links a {
	display: flex;
	gap: 4px;
}

.useful-links a::after {
	content: url('../../../img/top-right-arrow.svg');
	width: 12px;
	height: 12px;
}
</style>

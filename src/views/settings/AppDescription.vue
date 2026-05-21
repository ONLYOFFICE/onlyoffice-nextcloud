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
				<a href="https://community.onlyoffice.com/c/suggestions/integrations/56" target="_blank">{{ t('onlyoffice', 'Suggest a feature') }}</a>
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

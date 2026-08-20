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
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcPopover from '@nextcloud/vue/components/NcPopover'

defineProps<{
	label: string
}>()

const infoIconPath = 'M11,9H13V7H11M12,20C7.59,20 4,16.41 4,12C4,7.59 7.59,4 12,4C16.41,4 20,7.59 20,12C20,16.41 16.41,20 12,20M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M11,17H13V11H11V17Z'
</script>

<template>
	<NcPopover popup-role="dialog">
		<template #trigger>
			<NcButton class="onlyoffice-hint-icon"
				variant="tertiary-no-background"
				:aria-label="label">
				<template #icon>
					<span class="onlyoffice-hint-icon">
						<NcIconSvgWrapper :path="infoIconPath" :size="20" />
					</span>
				</template>
			</NcButton>
		</template>
		<div class="onlyoffice-hint-body">
			<slot />
		</div>
	</NcPopover>
</template>

<style scoped>
.onlyoffice-hint-icon {
	display: flex;
	color: var(--color-primary-element);
}

.onlyoffice-hint-body {
	max-width: 300px;
	padding: var(--border-radius-element);
}

.onlyoffice-hint-body :deep(p) {
	margin: 0;
}

.onlyoffice-hint-body :deep(ul) {
	padding-left: 16px;
	margin: 4px 0 0;
}

.onlyoffice-hint-body :deep(li + li) {
	margin-top: 4px;
}
</style>

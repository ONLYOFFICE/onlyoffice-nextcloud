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
	<li class="onlyoffice-template-item" :data-id="template.id">
		<img :src="template.icon">
		<p @click="handleOpen">
			{{ template.name }}
		</p>
		<span class="onlyoffice-template-download icon-download" @click="handleDownload" />
		<span class="onlyoffice-template-delete icon-delete" @click="emit('delete', template.id)" />
	</li>
</template>

<script setup lang="ts">
import type { Template } from '../types.ts'

import { generateUrl } from '@nextcloud/router'

const props = defineProps<{ template: Template }>()
const emit = defineEmits<{ delete: [id: number] }>()

/**
 *
 */
function handleOpen() {
	window.open(generateUrl('/apps/onlyoffice/{fileId}?template=true', { fileId: props.template.id }))
}

/**
 *
 */
function handleDownload() {
	location.href = generateUrl('apps/onlyoffice/downloadas?fileId={fileId}&template=true', { fileId: props.template.id })
}
</script>

<style scoped>
.onlyoffice-template-item img,
.onlyoffice-template-delete,
.onlyoffice-template-download,
.onlyoffice-template-item p {
    display: inline-block;
    margin-right: 10px;
    cursor: pointer;
}

.onlyoffice-template-delete,
.onlyoffice-template-download {
    margin-bottom: -4px;
    opacity: .6;
}

.onlyoffice-template-item img {
    float: left;
}
</style>

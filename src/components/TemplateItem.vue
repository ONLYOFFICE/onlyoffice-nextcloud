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
	<li class="onlyoffice-template-item" :data-id="template.id">
		<img :src="template.icon" />
		<p @click="handleOpen">{{ template.name }}</p>
		<span class="onlyoffice-template-download" @click="handleDownload" />
		<span class="onlyoffice-template-delete icon-delete" @click="emit('delete', template.id)" />
	</li>
</template>

<script setup lang="ts">
import { generateUrl } from '@nextcloud/router'
import type { Template } from '../types'

const props = defineProps<{ template: Template }>()
const emit = defineEmits<{ delete: [id: number] }>()

const handleOpen = () => {
	window.open(generateUrl('/apps/onlyoffice/{fileId}?template=true', { fileId: props.template.id }))
}

const handleDownload = () => {
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
}

.onlyoffice-template-delete {
    opacity: .6;
}

.onlyoffice-template-download {
    background-image: url("../../../core/img/actions/download.svg");
    height: 16px;
    width: 16px;
    opacity: .6;
}

.onlyoffice-template-item img {
    float: left;
}
</style>

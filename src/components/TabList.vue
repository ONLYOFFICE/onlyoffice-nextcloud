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
import { provide, reactive, ref, watch } from 'vue'
import { type TabMeta } from './tabs.ts'
import { tabListKey } from './tabs.ts'

const props = withDefaults(defineProps<{ modelValue?: string }>(), { modelValue: '' })
const emit = defineEmits<{ 'update:modelValue': [id: string] }>()

const tabs = reactive<TabMeta[]>([])
const activeId = ref(props.modelValue)

/**
 * Set the active tab and notify the parent v-model.
 *
 * @param id the tab id to activate
 */
function setActive(id: string) {
	if (id === activeId.value) {
		return
	}
	activeId.value = id
	emit('update:modelValue', id)
}

/**
 * Activate a tab.
 *
 * @param id the tab id to activate
 */
function select(id: string) {
	const tab = tabs.find((item) => item.id === id)
	if (tab && !tab.disabled) {
		setActive(id)
	}
}

/**
 * Add a tab to the list.
 *
 * @param tab the tab metadata
 */
function register(tab: TabMeta): TabMeta {
	const meta = reactive({ ...tab })
	tabs.push(meta)
	if (!activeId.value && !meta.disabled) {
		setActive(meta.id)
	}
	return meta
}

/**
 * Remove a tab from the list.
 *
 * @param id the tab id to remove
 */
function unregister(id: string) {
	const index = tabs.findIndex((tab) => tab.id === id)
	if (index !== -1) {
		tabs.splice(index, 1)
	}
}

watch(tabs, () => {
	const current = tabs.find((tab) => tab.id === activeId.value)
	if (!current || current.disabled) {
		const fallback = tabs.find((tab) => !tab.disabled)
		if (fallback) {
			setActive(fallback.id)
		}
	}
})

watch(() => props.modelValue, (id) => {
	if (id) {
		select(id)
	}
})

provide(tabListKey, { activeId, register, unregister, select })
</script>

<template>
	<div class="tab-list">
		<div class="tab-list__tabs" role="tablist">
			<button
				v-for="tab in tabs"
				:key="tab.id"
				type="button"
				role="tab"
				class="tab-list__tab"
				:class="{ 'tab-list__tab--active': tab.id === activeId }"
				:aria-selected="tab.id === activeId"
				:disabled="tab.disabled"
				@click="select(tab.id)">
				{{ tab.label }}
			</button>
		</div>
		<slot />
	</div>
</template>

<style scoped>
.tab-list__tabs {
	display: grid;
	grid-auto-flow: column;
	grid-auto-columns: minmax(0, 1fr);
	border-bottom: 1px solid var(--color-border);
	margin-bottom: 1rem;
}

.tab-list__tabs .tab-list__tab {
	justify-self: center;
	background: transparent;
	border: none;
	border-bottom: 4px solid transparent;
	border-radius: 0;
	margin: 0;
	padding: 12px 20px;
	font-size: 1rem;
	font-weight: normal;
	color: var(--color-main-text);
	cursor: pointer;
}

.tab-list__tabs .tab-list__tab:hover:not(:disabled),
.tab-list__tabs .tab-list__tab:focus:not(:disabled),
.tab-list__tabs .tab-list__tab:active:not(:disabled) {
	background-color: transparent;
	box-shadow: none;
	border-bottom-color: transparent;
	color: var(--color-primary-element);
}

.tab-list__tabs .tab-list__tab--active,
.tab-list__tabs .tab-list__tab--active:hover:not(:disabled),
.tab-list__tabs .tab-list__tab--active:focus:not(:disabled),
.tab-list__tabs .tab-list__tab--active:active:not(:disabled) {
	color: var(--color-primary-element);
	border-bottom-color: var(--color-primary-element);
	font-weight: bold;
}

.tab-list__tabs .tab-list__tab:disabled {
	color: var(--color-text-maxcontrast);
	cursor: not-allowed;
	opacity: 0.5;
}
</style>

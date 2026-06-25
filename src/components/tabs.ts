/**
 * Copyright (C) Ascensio System SIA, 2009-2026
 *
 * This program is a free software product. You can redistribute it and/or
 * modify it under the terms of the GNU Affero General Public License (AGPL)
 * version 3 as published by the Free Software Foundation, together with the
 * additional terms provided in the LICENSE file.
 *
 * This program is distributed WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. For
 * details, see the GNU AGPL at: https://www.gnu.org/licenses/agpl-3.0.html
 *
 * You can contact Ascensio System SIA by email at info@onlyoffice.com
 * or by postal mail at 20A-6 Ernesta Birznieka-Upisha Street, Riga,
 * LV-1050, Latvia, European Union.
 *
 * The interactive user interfaces in modified versions of the Program
 * are required to display Appropriate Legal Notices in accordance with
 * Section 5 of the GNU AGPL version 3.
 *
 * No trademark rights are granted under this License.
 *
 * All non-code elements of the Product, including illustrations,
 * icon sets, and technical writing content, are licensed under the
 * Creative Commons Attribution-ShareAlike 4.0 International License:
 * https://creativecommons.org/licenses/by-sa/4.0/legalcode
 *
 * This license applies only to such non-code elements and does not
 * modify or replace the licensing terms applicable to the Program's
 * source code, which remains licensed under the GNU Affero General
 * Public License v3.
 *
 * SPDX-License-Identifier: AGPL-3.0-only
 */
import { type InjectionKey, type Ref } from 'vue'
import { onMounted, onUnmounted, ref, watch } from 'vue'

export interface TabMeta {
	id: string
	label: string
	disabled: boolean
}

export interface TabListContext {
	activeId: Ref<string>
	register: (tab: TabMeta) => TabMeta
	unregister: (id: string) => void
	select: (id: string) => void
}

export const tabListKey: InjectionKey<TabListContext> = Symbol('onlyoffice:tab-list')

/**
 * Sync the active tab id with the URL hash so it survives a reload.
 *
 * @param valid the allowed tab ids
 * @param fallback the tab id used when the hash is empty or unknown
 */
export function useTabHash(valid: string[], fallback: string = valid[0] ?? ''): Ref<string> {
	// eslint-disable-next-line jsdoc/require-jsdoc
	function read(): string {
		const id = window.location.hash.replace(/^#/, '')
		return valid.includes(id) ? id : fallback
	}

	const value = ref(read())

	watch(value, (id) => {
		if (read() !== id) {
			window.location.hash = id
		}
	})

	// eslint-disable-next-line jsdoc/require-jsdoc
	function onHashChange() {
		value.value = read()
	}

	onMounted(() => window.addEventListener('hashchange', onHashChange))
	onUnmounted(() => window.removeEventListener('hashchange', onHashChange))

	return value
}

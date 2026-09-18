/*
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

import type { Ref } from 'vue'

import { showError, showLoading } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { ref, watch } from 'vue'

interface AutosaveOptions<T> {
	build: () => T
	save: (payload: T) => Promise<unknown>
	errorMessage: string
	delay?: number
}

/**
 * Persists a settings section automatically whenever its reactive state changes.
 *
 * @param options - build/save callbacks, error message and debounce delay
 */
export function useAutosave<T>(options: AutosaveOptions<T>): { saving: Ref<boolean>, flush: () => void } {
	const { build, save, errorMessage, delay = 700 } = options
	const saving = ref(false)
	let lastSaved = JSON.stringify(build())
	let pending = false
	let timer: ReturnType<typeof setTimeout> | undefined

	// eslint-disable-next-line jsdoc/require-jsdoc
	async function run() {
		if (saving.value) {
			pending = true
			return
		}
		saving.value = true
		let toast: ReturnType<typeof showLoading> | undefined
		try {
			do {
				pending = false
				const payload = build()
				if (JSON.stringify(payload) === lastSaved) {
					break
				}
				if (!toast) {
					toast = showLoading(t('onlyoffice', 'Saving …'))
				}
				await save(payload)
				lastSaved = JSON.stringify(build())
			} while (pending)
		} catch (error) {
			showError(errorMessage)
			console.error(error)
		} finally {
			toast?.hideToast()
			saving.value = false
		}
	}

	watch(build, (value) => {
		if (JSON.stringify(value) === lastSaved) {
			return
		}
		clearTimeout(timer)
		timer = setTimeout(run, delay)
	}, { deep: true })

	/**
	 * Saves immediately, bypassing the debounce.
	 */
	function flush() {
		clearTimeout(timer)
		run()
	}

	return { saving, flush }
}

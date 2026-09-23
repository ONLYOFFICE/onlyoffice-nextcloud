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

OCA.Onlyoffice = { ...OCA.Onlyoffice }

/**
 *
 * @param messageName
 * @param attributes
 */
function callMobileMessage(messageName, attributes) {
	let message = messageName
	if (typeof attributes !== 'undefined') {
		message = {
			MessageName: messageName,
			Values: attributes,
		}
	}
	let attributesString
	try {
		attributesString = JSON.stringify(attributes)
	} catch (e) {
		attributesString = null
	}

	// Forward to mobile handler
	if (window.DirectEditingMobileInterface && typeof window.DirectEditingMobileInterface[messageName] === 'function') {
		if (attributesString === null || typeof attributesString === 'undefined') {
			window.DirectEditingMobileInterface[messageName]()
		} else {
			window.DirectEditingMobileInterface[messageName](attributesString)
		}
	}

	// iOS webkit fallback
	if (window.webkit
		&& window.webkit.messageHandlers
		&& window.webkit.messageHandlers.DirectEditingMobileInterface) {
		window.webkit.messageHandlers.DirectEditingMobileInterface.postMessage(message)
	}

	window.postMessage(message)
}

OCA.Onlyoffice.directEditor = {
	close() {
		callMobileMessage('close')
	},
	loaded() {
		callMobileMessage('loaded')
	},
}

window.onload = function() {
	const directEditorError = document.getElementById('directEditorError')

	if (directEditorError) {
		OCA.Onlyoffice.directEditor.loaded()
		const directEditorErrorButton = document.getElementById('directEditorErrorButton')
		directEditorErrorButton.addEventListener('click', function() {
			OCA.Onlyoffice.directEditor.close()
		})
	}
}

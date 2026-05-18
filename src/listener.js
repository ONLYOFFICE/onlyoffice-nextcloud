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

import { getFilePickerBuilder, showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import '@nextcloud/dialogs/style.css'

OCA.Onlyoffice = { AppName: 'onlyoffice', frameSelector: null, titleBase: window.document.title, favIconBase: document.querySelector('link[rel="icon"]')?.getAttribute('href'), ...OCA.Onlyoffice }

OCA.Onlyoffice.onRequestClose = function() {
	document.querySelector(OCA.Onlyoffice.frameSelector)?.remove()

	if (OCA.Viewer && OCA.Viewer.close) {
		OCA.Viewer.close()
	}

	if (OCA.Onlyoffice.CloseEditor) {
		OCA.Onlyoffice.CloseEditor()
	}
}

OCA.Onlyoffice.onRequestSaveAs = function(saveData) {
	getFilePickerBuilder(t(OCA.Onlyoffice.AppName, 'Save as'))
		.setMimeTypeFilter(['httpd/unix-directory'])
		.allowDirectories()
		.startAt(saveData.dir)
		.addButton({
			label: t('core', 'Choose'),
			callback: (nodes) => {
				if (!nodes[0]) {
					return
				}
				saveData.dir = nodes[0].path
				document.querySelector(OCA.Onlyoffice.frameSelector).contentWindow.OCA.Onlyoffice.editorSaveAs(saveData)
			},
			variant: 'primary',
		})
		.build()
		.pickNodes()
		.catch(() => {})
}

OCA.Onlyoffice.onRequestInsertImage = function(imageMimes) {
	getFilePickerBuilder(t(OCA.Onlyoffice.AppName, 'Insert image'))
		.setMimeTypeFilter(imageMimes)
		.addButton({
			label: t('core', 'Choose'),
			callback: (nodes) => {
				if (!nodes[0]) {
					return
				}
				document.querySelector(OCA.Onlyoffice.frameSelector).contentWindow.OCA.Onlyoffice.editorInsertImage(nodes[0].path)
			},
			variant: 'primary',
		})
		.build()
		.pickNodes()
		.catch(() => {})
}

OCA.Onlyoffice.onRequestMailMergeRecipients = function(recipientMimes) {
	getFilePickerBuilder(t(OCA.Onlyoffice.AppName, 'Select recipients'))
		.setMimeTypeFilter(recipientMimes)
		.addButton({
			label: t('core', 'Choose'),
			callback: (nodes) => {
				if (!nodes[0]) {
					return
				}
				document.querySelector(OCA.Onlyoffice.frameSelector).contentWindow.OCA.Onlyoffice.editorSetRecipient(nodes[0].path)
			},
			variant: 'primary',
		})
		.build()
		.pickNodes()
		.catch(() => {})
}

OCA.Onlyoffice.onRequestSelectDocument = function(revisedMimes, documentSelectionType) {
	let title
	switch (documentSelectionType) {
		case 'combine':
			title = t(OCA.Onlyoffice.AppName, 'Select file to combine')
			break
		case 'compare':
			title = t(OCA.Onlyoffice.AppName, 'Select file to compare')
			break
		case 'insert-text':
			title = t(OCA.Onlyoffice.AppName, 'Select file to insert text')
			break
		default:
			title = t(OCA.Onlyoffice.AppName, 'Select file')
	}
	getFilePickerBuilder(title)
		.setMimeTypeFilter(revisedMimes)
		.addButton({
			label: t('core', 'Choose'),
			callback: (nodes) => {
				if (!nodes[0]) {
					return
				}
				document.querySelector(OCA.Onlyoffice.frameSelector).contentWindow.OCA.Onlyoffice.editorSetRequested.call({ documentSelectionType }, nodes[0].path)
			},
			variant: 'primary',
		})
		.build()
		.pickNodes()
		.catch(() => {})
}

OCA.Onlyoffice.onRequestReferenceSource = function(referenceSourceMimes) {
	getFilePickerBuilder(t(OCA.Onlyoffice.AppName, 'Select data source'))
		.setMimeTypeFilter(referenceSourceMimes)
		.addButton({
			label: t('core', 'Choose'),
			callback: (nodes) => {
				if (!nodes[0]) {
					return
				}
				document.querySelector(OCA.Onlyoffice.frameSelector).contentWindow.OCA.Onlyoffice.editorReferenceSource(nodes[0].path)
			},
			variant: 'primary',
		})
		.build()
		.pickNodes()
		.catch(() => {})
}

OCA.Onlyoffice.onDocumentReady = function() {
	console.log('ONLYOFFICE Editor is loaded')
	OCA.Onlyoffice.setViewport()
}

OCA.Onlyoffice.changeFavicon = function(favicon) {
	document.querySelector('link[rel="icon"]')?.setAttribute('href', favicon)
}

OCA.Onlyoffice.setViewport = function() {
	document.querySelector('meta[name="viewport"]').setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0')
}

OCA.Onlyoffice.onShowMessage = function(messageObj) {
	switch (messageObj.type) {
		case 'success':
			showSuccess(messageObj.message, messageObj.props)
			break
		case 'error':
			showError(messageObj.message, messageObj.props)
			break
	}
}

window.addEventListener('message', function(event) {
	const frame = document.querySelector(OCA.Onlyoffice.frameSelector)
	if (!frame
		|| frame.contentWindow !== event.source
		|| !event.data.method) {
		return
	}
	switch (event.data.method) {
		case 'editorRequestClose':
			OCA.Onlyoffice.onRequestClose()
			break
		case 'editorRequestSharingSettings':
			if (OCA.Onlyoffice.OpenShareDialog) {
				OCA.Onlyoffice.OpenShareDialog()
			}
			break
		case 'onRefreshVersionsDialog':
			if (OCA.Onlyoffice.RefreshVersionsDialog) {
				OCA.Onlyoffice.RefreshVersionsDialog()
			}
			break
		case 'editorRequestSaveAs':
			OCA.Onlyoffice.onRequestSaveAs(event.data.param)
			break
		case 'editorRequestInsertImage':
			OCA.Onlyoffice.onRequestInsertImage(event.data.param)
			break
		case 'editorRequestMailMergeRecipients':
			OCA.Onlyoffice.onRequestMailMergeRecipients(event.data.param)
			break
		case 'editorRequestSelectDocument':
			OCA.Onlyoffice.onRequestSelectDocument(event.data.param, event.data.documentSelectionType)
			break
		case 'editorRequestReferenceSource':
			OCA.Onlyoffice.onRequestReferenceSource(event.data.param)
			break
		case 'onDocumentReady':
			OCA.Onlyoffice.onDocumentReady(event.data.param)
			break
		case 'changeFavicon':
			OCA.Onlyoffice.changeFavicon(event.data.param)
			break
		case 'onShowMessage':
			OCA.Onlyoffice.onShowMessage(event.data.param)
			break
	}
}, false)

window.addEventListener('popstate', function() {
	if (document.querySelector(OCA.Onlyoffice.frameSelector)) {
		OCA.Onlyoffice.onRequestClose()
	}
})

const mutationObserver = new MutationObserver((mutationRecords) => {
	if (mutationRecords[0] && mutationRecords[0].removedNodes) {
		mutationRecords[0].removedNodes.forEach((node) => {
			if (node.id && '#' + node.id === OCA.Onlyoffice.frameSelector) {
				OCA.Onlyoffice.changeFavicon(OCA.Onlyoffice.favIconBase)
				window.document.title = OCA.Onlyoffice.titleBase
				OCA.Onlyoffice.frameSelector = null
			}
		})
	}
})

mutationObserver.observe(document.querySelector('body'), {
	childList: true,
	subtree: true,
	characterDataOldValue: true,
})

/**
 *
 * (c) Copyright Ascensio System SIA 2026
 *
 * This program is a free software product.
 * You can redistribute it and/or modify it under the terms of the GNU Affero General Public License
 * (AGPL) version 3 as published by the Free Software Foundation.
 * In accordance with Section 7(a) of the GNU AGPL its Section 15 shall be amended to the effect
 * that Ascensio System SIA expressly excludes the warranty of non-infringement of any third-party rights.
 *
 * This program is distributed WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * For details, see the GNU AGPL at: http://www.gnu.org/licenses/agpl-3.0.html
 *
 * You can contact Ascensio System SIA at 20A-12 Ernesta Birznieka-Upisha street, Riga, Latvia, EU, LV-1050.
 *
 * The interactive user interfaces in modified source and object code versions of the Program
 * must display Appropriate Legal Notices, as required under Section 5 of the GNU AGPL version 3.
 *
 * Pursuant to Section 7(b) of the License you must retain the original Product logo when distributing the program.
 * Pursuant to Section 7(e) we decline to grant you any rights under trademark law for use of our trademarks.
 *
 * All the Product's GUI elements, including illustrations and icon sets, as well as technical
 * writing content are licensed under the terms of the Creative Commons Attribution-ShareAlike 4.0 International.
 * See the License terms at http://creativecommons.org/licenses/by-sa/4.0/legalcode
 *
 */

import '@nextcloud/dialogs/style.css'
import { showError, showSuccess, getFilePickerBuilder } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

/* global _ */

/**
 * @param {object} OCA Nextcloud OCA object
 */
(function(OCA) {

	OCA.Onlyoffice = _.extend({
		AppName: 'onlyoffice',
		frameSelector: null,
		titleBase: window.document.title,
		favIconBase: document.querySelector('link[rel="icon"]')?.getAttribute('href'),
	}, OCA.Onlyoffice)

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
					if (!nodes[0]) return
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
				callback: (nodes) => { if (!nodes[0]) return; document.querySelector(OCA.Onlyoffice.frameSelector).contentWindow.OCA.Onlyoffice.editorInsertImage(nodes[0].path) },
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
				callback: (nodes) => { if (!nodes[0]) return; document.querySelector(OCA.Onlyoffice.frameSelector).contentWindow.OCA.Onlyoffice.editorSetRecipient(nodes[0].path) },
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
				callback: (nodes) => { if (!nodes[0]) return; document.querySelector(OCA.Onlyoffice.frameSelector).contentWindow.OCA.Onlyoffice.editorSetRequested.call({ documentSelectionType }, nodes[0].path) },
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
				callback: (nodes) => { if (!nodes[0]) return; document.querySelector(OCA.Onlyoffice.frameSelector).contentWindow.OCA.Onlyoffice.editorReferenceSource(nodes[0].path) },
				variant: 'primary',
			})
			.build()
			.pickNodes()
			.catch(() => {})
	}

	OCA.Onlyoffice.onDocumentReady = function() {
		// eslint-disable-next-line no-console
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

	window.addEventListener('popstate', function(event) {
		if (document.querySelector(OCA.Onlyoffice.frameSelector)) {
			OCA.Onlyoffice.onRequestClose()
		}
	})

	const mutationObserver = new MutationObserver(mutationRecords => {
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

})(OCA)

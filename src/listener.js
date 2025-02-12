/**
 *
 * (c) Copyright Ascensio System SIA 2025
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

/* global _, $ */

/**
 * @param {object} OCA Nextcloud OCA object
 */
(function(OCA) {

	OCA.Onlyoffice = _.extend({
		AppName: 'onlyoffice',
		frameSelector: null,
		titleBase: window.document.title,
		favIconBase: $('link[rel="icon"]').attr('href'),
	}, OCA.Onlyoffice)

	OCA.Onlyoffice.onRequestClose = function() {

		$(OCA.Onlyoffice.frameSelector).remove()

		if (OCA.Viewer && OCA.Viewer.close) {
			OCA.Viewer.close()
		}

		if (OCA.Onlyoffice.CloseEditor) {
			OCA.Onlyoffice.CloseEditor()
		}
	}

	OCA.Onlyoffice.onRequestSaveAs = function(saveData) {

		OC.dialogs.filepicker(t(OCA.Onlyoffice.AppName, 'Save as'),
			function(fileDir) {
				saveData.dir = fileDir
				$(OCA.Onlyoffice.frameSelector)[0].contentWindow.OCA.Onlyoffice.editorSaveAs(saveData)
			},
			false,
			'httpd/unix-directory',
			true,
			OC.dialogs.FILEPICKER_TYPE_CHOOSE,
			saveData.dir)
	}

	OCA.Onlyoffice.onRequestInsertImage = function(imageMimes) {
		OC.dialogs.filepicker(t(OCA.Onlyoffice.AppName, 'Insert image'),
			$(OCA.Onlyoffice.frameSelector)[0].contentWindow.OCA.Onlyoffice.editorInsertImage,
			false,
			imageMimes,
			true)
	}

	OCA.Onlyoffice.onRequestMailMergeRecipients = function(recipientMimes) {
		OC.dialogs.filepicker(t(OCA.Onlyoffice.AppName, 'Select recipients'),
			$(OCA.Onlyoffice.frameSelector)[0].contentWindow.OCA.Onlyoffice.editorSetRecipient,
			false,
			recipientMimes,
			true)
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
		OC.dialogs.filepicker(title,
			$(OCA.Onlyoffice.frameSelector)[0].contentWindow.OCA.Onlyoffice.editorSetRequested.bind({ documentSelectionType }),
			false,
			revisedMimes,
			true)
	}

	OCA.Onlyoffice.onRequestReferenceSource = function(referenceSourceMimes) {
		OC.dialogs.filepicker(t(OCA.Onlyoffice.AppName, 'Select data source'),
			$(OCA.Onlyoffice.frameSelector)[0].contentWindow.OCA.Onlyoffice.editorReferenceSource,
			false,
			referenceSourceMimes,
			true)
	}

	OCA.Onlyoffice.onDocumentReady = function() {
		OCA.Onlyoffice.setViewport()
	}

	OCA.Onlyoffice.changeFavicon = function(favicon) {
		$('link[rel="icon"]').attr('href', favicon)
	}

	OCA.Onlyoffice.setViewport = function() {
		document.querySelector('meta[name="viewport"]').setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0')
	}

	OCA.Onlyoffice.onShowMessage = function(messageObj) {
		switch (messageObj.type) {
		case 'success':
			OCP.Toast.success(messageObj.message, messageObj.props)
			break
		case 'error':
			OCP.Toast.error(messageObj.message, messageObj.props)
			break
		}
	}

	window.addEventListener('message', function(event) {
		if (!$(OCA.Onlyoffice.frameSelector).length
			|| $(OCA.Onlyoffice.frameSelector)[0].contentWindow !== event.source
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
		if ($(OCA.Onlyoffice.frameSelector).length) {
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

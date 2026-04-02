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

import { getCurrentUser } from '@nextcloud/auth'
import '@nextcloud/dialogs/style.css'
import { showError, showSuccess, getFilePickerBuilder } from '@nextcloud/dialogs'
import { getCanonicalLocale, t } from '@nextcloud/l10n'
import { generateUrl, generateOcsUrl, imagePath } from '@nextcloud/router'
import {
	getConfig,
	getHistory,
	getVersionData,
	restoreVersion,
	saveAs,
	getFileUrl,
	fetchReference,
	getUserInfo,
	getUsers,
	sendMention,
	setFavorite,
} from './services/EditorService.ts'
import { encodePath } from '@nextcloud/paths'

/* global DocsAPI, oc_defaults */

OCA.Onlyoffice = Object.assign({
	AppName: 'onlyoffice',
	inframe: false,
	inviewer: false,
	fileId: null,
	shareToken: null,
	insertImageType: null,
}, OCA.Onlyoffice)

OCA.Onlyoffice.InitEditor = function() {

	const iframeEditor = document.getElementById('iframeEditor')
	OCA.Onlyoffice.fileId = iframeEditor.dataset.id
	OCA.Onlyoffice.shareToken = iframeEditor.dataset.sharetoken
	OCA.Onlyoffice.directToken = iframeEditor.dataset.directtoken
	OCA.Onlyoffice.template = iframeEditor.dataset.template
	OCA.Onlyoffice.inframe = !!iframeEditor.dataset.inframe
	OCA.Onlyoffice.inviewer = !!iframeEditor.dataset.inviewer
	OCA.Onlyoffice.filePath = iframeEditor.dataset.path
	OCA.Onlyoffice.anchor = iframeEditor.getAttribute('data-anchor')
	OCA.Onlyoffice.currentWindow = window
	OCA.Onlyoffice.currentUser = getCurrentUser()

	if (OCA.Onlyoffice.inframe) {
		OCA.Onlyoffice.currentWindow = window.parent
		OCA.Onlyoffice.currentUser = OCA.Onlyoffice.currentWindow.OC.getCurrentUser()
	}

	if (!OCA.Onlyoffice.fileId && !OCA.Onlyoffice.shareToken && !OCA.Onlyoffice.directToken) {
		OCA.Onlyoffice.showMessage(t(OCA.Onlyoffice.AppName, 'FileId is empty'), 'error', { timeout: -1 })
		return
	}

	const configUrl = OCA.Onlyoffice.getConfigUrl()

	getConfig(configUrl).then((config) => {
		if (config) {
			OCA.Onlyoffice.device = config.type
			if (OCA.Onlyoffice.device === 'mobile') {
				OCA.Onlyoffice.resizeEvents()
			}

			if (config.redirectUrl) {
				location.href = config.redirectUrl
				return
			}

			if (config.error != null) {
				OCA.Onlyoffice.showMessage(config.error, 'error', { timeout: -1 })
				return
			}

			if (!config.documentServerUrl) {
				OCA.Onlyoffice.showMessage('ONLYOFFICE cannot be reached. Please contact admin', 'error', { timeout: -1 })
				return
			}

			const script = document.createElement('script')
			script.src = config.documentServerUrl + 'web-apps/apps/api/documents/api.js?shardKey=' + config.document.key
			script.setAttribute('nonce', btoa(OC.requestToken))
			script.onerror = function() {
				OCA.Onlyoffice.showMessage(t(OCA.Onlyoffice.AppName, 'ONLYOFFICE cannot be reached. Please contact admin'), 'error', { timeout: -1 })
			}
			script.onload = function() {
				if (typeof DocsAPI === 'undefined') {
					OCA.Onlyoffice.showMessage(t(OCA.Onlyoffice.AppName, 'ONLYOFFICE cannot be reached. Please contact admin'), 'error', { timeout: -1 })
					return
				}

				const docsVersion = DocsAPI.DocEditor.version().split('.')
				if ((docsVersion[0] < 6)
					|| (parseInt(docsVersion[0]) === 6 && parseInt(docsVersion[1]) === 0)) {
					OCA.Onlyoffice.showMessage(t(OCA.Onlyoffice.AppName, 'Not supported version'), 'error', { timeout: -1 })
					return
				}

				let docIsChanged = null
				let docIsChangedTimeout = null

				const setPageTitle = function(event) {
					clearTimeout(docIsChangedTimeout)

					if (docIsChanged !== event.data) {
						const titleChange = function() {
							OCA.Onlyoffice.currentWindow.document.title = config.document.title + (event.data ? ' *' : '') + ' - ' + oc_defaults.title
							docIsChanged = event.data
						}

						if (event === false || event.data) {
							titleChange()
						} else {
							docIsChangedTimeout = setTimeout(titleChange, 500)
						}
					}
				}
				setPageTitle(false)

				OCA.Onlyoffice.documentType = config.documentType

				config.events = {
					onDocumentStateChange: setPageTitle,
					onDocumentReady: OCA.Onlyoffice.onDocumentReady,
					onMakeActionLink: OCA.Onlyoffice.onMakeActionLink,
				}

				if (config.editorConfig.tenant) {
					config.events.onAppReady = function() {
						OCA.Onlyoffice.docEditor.showMessage(t(OCA.Onlyoffice.AppName, 'You are using public demo ONLYOFFICE Docs server. Please do not store private sensitive data.'))
					}
				}

				if ((OCA.Onlyoffice.inframe && !OCA.Onlyoffice.shareToken)
					|| (OCA.Onlyoffice.currentUser.uid)) {
					config.events.onRequestSaveAs = OCA.Onlyoffice.onRequestSaveAs
					config.events.onRequestInsertImage = OCA.Onlyoffice.onRequestInsertImage
					config.events.onRequestMailMergeRecipients = OCA.Onlyoffice.onRequestMailMergeRecipients
					config.events.onRequestCompareFile = OCA.Onlyoffice.onRequestSelectDocument // todo: remove (for editors 7.4)
					config.events.onRequestSelectDocument = OCA.Onlyoffice.onRequestSelectDocument
					config.events.onRequestSendNotify = OCA.Onlyoffice.onRequestSendNotify
					config.events.onRequestReferenceData = OCA.Onlyoffice.onRequestReferenceData
					config.events.onRequestOpen = OCA.Onlyoffice.onRequestOpen
					config.events.onRequestReferenceSource = OCA.Onlyoffice.onRequestReferenceSource
					config.events.onMetaChange = OCA.Onlyoffice.onMetaChange
					config.events.onRequestRefreshFile = OCA.Onlyoffice.onRequestRefreshFile

					if (OCA.Onlyoffice.currentUser.uid) {
						config.events.onRequestUsers = OCA.Onlyoffice.onRequestUsers
					}

					if (!OCA.Onlyoffice.filePath) {
						OCA.Onlyoffice.filePath = config._file_path
					}

					if (!OCA.Onlyoffice.template) {
						config.events.onRequestHistory = OCA.Onlyoffice.onRequestHistory
						config.events.onRequestHistoryData = OCA.Onlyoffice.onRequestHistoryData
						config.events.onRequestRestore = OCA.Onlyoffice.onRequestRestore
						config.events.onRequestHistoryClose = OCA.Onlyoffice.onRequestHistoryClose
					}
				}

				if (OCA.Onlyoffice.directEditor || OCA.Onlyoffice.inframe) {
					config.events.onRequestClose = OCA.Onlyoffice.onRequestClose
				}

				if (OCA.Onlyoffice.inframe
					&& config._files_sharing && !OCA.Onlyoffice.shareToken
					&& window.parent.OCA.Onlyoffice.context) {
					config.events.onRequestSharingSettings = OCA.Onlyoffice.onRequestSharingSettings
				}

				OCA.Onlyoffice.docEditor = new DocsAPI.DocEditor('iframeEditor', config)

				if (OCA.Onlyoffice.directEditor) {
					OCA.Onlyoffice.directEditor.loaded()
				}

				if (!OCA.Onlyoffice.directEditor
					&& config.type === 'mobile' && window.getComputedStyle(document.querySelector('#app > iframe')).position === 'fixed') {
					document.querySelector('#app > iframe').style.height = 'calc(100% - 50px)'
				}

				const favicon = imagePath(OCA.Onlyoffice.AppName, OCA.Onlyoffice.documentType + '.ico')
				if (OCA.Onlyoffice.inframe) {
					window.parent.postMessage({
						method: 'changeFavicon',
						param: favicon,
					},
					'*')
				} else {
					document.querySelector('link[rel="icon"]')?.setAttribute('href', favicon)
				}
			}
			document.head.appendChild(script)
		}
	})
}

OCA.Onlyoffice.onRequestHistory = function(version) {
	getHistory(OCA.Onlyoffice.fileId || 0).then((response) => {
		OCA.Onlyoffice.refreshHistory(response, version)
	})
}

OCA.Onlyoffice.onRequestHistoryData = function(event) {
	const version = event.data

	getVersionData(OCA.Onlyoffice.fileId || 0, version).then((response) => {
		if (response.error) {
			response = {
				error: response.error,
				version,
			}
		}
		OCA.Onlyoffice.docEditor.setHistoryData(response)
	})
}

OCA.Onlyoffice.onRequestRestore = function(event) {
	const version = event.data.version

	restoreVersion(OCA.Onlyoffice.fileId || 0, version).then((response) => {
		OCA.Onlyoffice.refreshHistory(response, response.at(-1).version)

		if (OCA.Onlyoffice.inframe) {
			window.parent.postMessage({
				method: 'onRefreshVersionsDialog',
			},
			'*')
		}
	})
}

OCA.Onlyoffice.onRequestHistoryClose = function() {
	location.reload(true)
}

OCA.Onlyoffice.onDocumentReady = function() {
	// eslint-disable-next-line no-console
	console.log('ONLYOFFICE Editor is loaded')
	if (OCA.Onlyoffice.inframe) {
		window.parent.postMessage({
			method: 'onDocumentReady',
			param: {},
		},
		'*')
	}

	OCA.Onlyoffice.resize()
	OCA.Onlyoffice.setViewport()
}

OCA.Onlyoffice.onRequestSaveAs = function(event) {
	const saveData = {
		name: event.data.title,
		url: event.data.url,
	}

	if (OCA.Onlyoffice.filePath) {
		const arrayPath = OCA.Onlyoffice.filePath.split('/')
		arrayPath.pop()
		arrayPath.shift()
		saveData.dir = '/' + arrayPath.join('/')
	}

	if (OCA.Onlyoffice.inframe) {
		window.parent.postMessage({
			method: 'editorRequestSaveAs',
			param: saveData,
		},
		'*')
	} else {
		getFilePickerBuilder(t(OCA.Onlyoffice.AppName, 'Save as'))
			.setMimeTypeFilter(['httpd/unix-directory'])
			.allowDirectories()
			.startAt(saveData.dir)
			.addButton({
				label: t('core', 'Choose'),
				callback: (nodes) => {
					if (!nodes[0]) return
					saveData.dir = nodes[0].path
					OCA.Onlyoffice.editorSaveAs(saveData)
				},
				variant: 'primary',
			})
			.build()
			.pickNodes()
			.catch(() => {})
	}
}

OCA.Onlyoffice.editorSaveAs = function(saveData) {
	saveAs(saveData).then((response) => {
		if (response.error) {
			OCA.Onlyoffice.showMessage(response.error, 'error')
			return
		}

		OCA.Onlyoffice.showMessage(t(OCA.Onlyoffice.AppName, 'File saved') + ' (' + response.name + ')')
	})
}

OCA.Onlyoffice.onRequestInsertImage = function(event) {
	const imageMimes = [
		'image/bmp', 'image/x-bmp', 'image/x-bitmap', 'application/bmp',
		'image/gif', 'image/tiff',
		'image/jpeg', 'image/jpg', 'application/jpg', 'application/x-jpg',
		'image/png', 'image/x-png', 'application/png', 'application/x-png',
		'image/svg+xml',
	]

	if (event.data) {
		OCA.Onlyoffice.insertImageType = event.data.c
	}

	if (OCA.Onlyoffice.inframe) {
		window.parent.postMessage({
			method: 'editorRequestInsertImage',
			param: imageMimes,
		},
		'*')
	} else {
		getFilePickerBuilder(t(OCA.Onlyoffice.AppName, 'Insert image'))
			.setMimeTypeFilter(imageMimes)
			.addButton({
				label: t('core', 'Choose'),
				callback: (nodes) => { if (!nodes[0]) return; OCA.Onlyoffice.editorInsertImage(nodes[0].path) },
				variant: 'primary',
			})
			.build()
			.pickNodes()
			.catch(() => {})
	}
}

OCA.Onlyoffice.editorInsertImage = function(filePath) {
	getFileUrl(filePath).then((response) => {
		if (response.error) {
			OCA.Onlyoffice.showMessage(response.error, 'error')
			return
		}

		if (OCA.Onlyoffice.insertImageType) {
			response.c = OCA.Onlyoffice.insertImageType
		}

		OCA.Onlyoffice.docEditor.insertImage(response)
	})
}

OCA.Onlyoffice.onRequestMailMergeRecipients = function() {
	const recipientMimes = [
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	]

	if (OCA.Onlyoffice.inframe) {
		window.parent.postMessage({
			method: 'editorRequestMailMergeRecipients',
			param: recipientMimes,
		},
		'*')
	} else {
		getFilePickerBuilder(t(OCA.Onlyoffice.AppName, 'Select recipients'))
			.setMimeTypeFilter(recipientMimes)
			.addButton({
				label: t('core', 'Choose'),
				callback: (nodes) => { if (!nodes[0]) return; OCA.Onlyoffice.editorSetRecipient(nodes[0].path) },
				variant: 'primary',
			})
			.build()
			.pickNodes()
			.catch(() => {})
	}
}

OCA.Onlyoffice.editorSetRecipient = function(filePath) {
	getFileUrl(filePath).then((response) => {
		if (response.error) {
			OCA.Onlyoffice.showMessage(response.error, 'error')
			return
		}

		OCA.Onlyoffice.docEditor.setMailMergeRecipients(response)
	})
}

OCA.Onlyoffice.editorReferenceSource = function(filePath) {
	if (filePath === OCA.Onlyoffice.filePath) {
		OCA.Onlyoffice.showMessage(t(OCA.Onlyoffice.AppName, 'The data source must not be the current document'), 'error')
		return
	}

	fetchReference({ referenceData: {}, path: filePath }).then((response) => {
		if (response.error) {
			OCA.Onlyoffice.showMessage(response.error, 'error')
			return
		}
		OCA.Onlyoffice.docEditor.setReferenceSource(response)
	})
}

OCA.Onlyoffice.onRequestClose = function() {
	if (OCA.Onlyoffice.directEditor) {
		OCA.Onlyoffice.directEditor.close()
		return
	}

	OCA.Onlyoffice.docEditor.destroyEditor()

	window.parent.postMessage({
		method: 'editorRequestClose',
	},
	'*')
}

OCA.Onlyoffice.onRequestSharingSettings = function() {
	window.parent.postMessage({
		method: 'editorRequestSharingSettings',
	},
	'*')
}

OCA.Onlyoffice.onRequestSelectDocument = function(event) {
	const revisedMimes = [
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	]

	if (OCA.Onlyoffice.inframe) {
		window.parent.postMessage({
			method: 'editorRequestSelectDocument',
			param: revisedMimes,
			documentSelectionType: event.data.c,
		},
		'*')
	} else {
		let title
		switch (event.data.c) {
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
				callback: (nodes) => { if (!nodes[0]) return; OCA.Onlyoffice.editorSetRequested.call({ documentSelectionType: event.data.c }, nodes[0].path) },
				variant: 'primary',
			})
			.build()
			.pickNodes()
			.catch(() => {})
	}
}

OCA.Onlyoffice.editorSetRequested = function(filePath) {
	const documentSelectionType = this.documentSelectionType
	getFileUrl(filePath).then((response) => {
		if (response.error) {
			showError(response.error)
			return
		}
		response.c = documentSelectionType

		OCA.Onlyoffice.docEditor.setRequestedDocument(response)
	})
}

OCA.Onlyoffice.onMakeActionLink = function(event) {
	let url = location.href
	if (event && event.data) {
		const indexAnchor = url.indexOf('#')
		if (parseInt(indexAnchor) !== -1) {
			url = url.substring(0, indexAnchor)
		}

		let data = JSON.stringify(event.data)
		data = 'anchor=' + encodeURIComponent(data)

		const inframeRegex = /inframe=([^&]*&?)/g
		if (inframeRegex.test(url)) {
			url = url.replace(inframeRegex, data)
		}

		const anchorRegex = /anchor=([^&]*)/g
		if (anchorRegex.test(url)) {
			url = url.replace(anchorRegex, data)
		} else {
			url += (url.indexOf('?') === -1) ? '?' : '&'
			url += data
		}
	}

	OCA.Onlyoffice.docEditor.setActionLink(url)
}

OCA.Onlyoffice.onRequestUsers = function(event) {
	const operationType = typeof (event.data.c) !== 'undefined' ? event.data.c : null
	switch (operationType) {
	case 'info': {
		getUserInfo(event.data.id).then((response) => {
			OCA.Onlyoffice.docEditor.setUsers({
				c: operationType,
				users: response,
			})
		})
		break
	}
	default: {
		getUsers({
			fileId: OCA.Onlyoffice.fileId || 0,
			operationType,
			...(typeof event.data.search !== 'undefined' && {
				from: event.data.from,
				count: event.data.count,
				search: event.data.search,
			}),
		}).then((response) => {
			OCA.Onlyoffice.docEditor.setUsers({
				c: operationType,
				users: response,
				// support v9.0
				total: 1 + (!event.data.count || response.length < event.data.count ? 0 : (event.data.from + event.data.count)),
				// since v9.0.1
				isPaginated: true,
			})
		})
	}
	}
}

OCA.Onlyoffice.onRequestSendNotify = function(event) {
	const actionLink = event.data.actionLink
	const comment = event.data.message
	const emails = event.data.emails

	const fileId = OCA.Onlyoffice.fileId

	sendMention({
		fileId,
		anchor: JSON.stringify(actionLink),
		comment,
		emails,
	}).then((response) => {
		if (response.error) {
			OCA.Onlyoffice.showMessage(response.error, 'error')
			return
		}

		OCA.Onlyoffice.showMessage(response.message)
	})
}

OCA.Onlyoffice.onRequestReferenceData = function(event) {
	const link = event.data.link
	const referenceData = event.data.referenceData
	const path = event.data.path

	fetchReference({ referenceData, path, link }).then((response) => {
		if (response.error) {
			OCA.Onlyoffice.showMessage(response.error, 'error')
			return
		}

		OCA.Onlyoffice.docEditor.setReferenceData(response)
	})
}

OCA.Onlyoffice.onRequestOpen = function(event) {
	const filePath = event.data.path
	const fileId = event.data.referenceData.fileKey
	const windowName = event.data.windowName
	const sourceUrl = generateUrl(`apps/${OCA.Onlyoffice.AppName}/${fileId}?filePath=${encodePath(filePath)}`)
	window.open(sourceUrl, windowName)
}

OCA.Onlyoffice.onRequestReferenceSource = function(event) {
	const referenceSourceMimes = [
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	]
	if (OCA.Onlyoffice.inframe) {
		window.parent.postMessage({
			method: 'editorRequestReferenceSource',
			param: referenceSourceMimes,
		},
		'*')
	} else {
		getFilePickerBuilder(t(OCA.Onlyoffice.AppName, 'Select data source'))
			.setMimeTypeFilter(referenceSourceMimes)
			.addButton({
				label: t('core', 'Choose'),
				callback: (nodes) => { if (!nodes[0]) return; OCA.Onlyoffice.editorReferenceSource(nodes[0].path) },
				variant: 'primary',
			})
			.build()
			.pickNodes()
			.catch(() => {})
	}
}

OCA.Onlyoffice.onMetaChange = function(event) {
	if (event.data.favorite !== undefined) {
		setFavorite(OCA.Onlyoffice.filePath, event.data.favorite).then(() => {
			OCA.Onlyoffice.docEditor.setFavorite(event.data.favorite)
		})
	}
}

OCA.Onlyoffice.onRequestRefreshFile = function() {
	const configUrl = OCA.Onlyoffice.getConfigUrl()
	getConfig(configUrl).then((config) => {
		OCA.Onlyoffice.docEditor.refreshFile(config)
	})
}

OCA.Onlyoffice.showMessage = function(message, type = 'success', props = null) {
	if (OCA.Onlyoffice.directEditor) {
		OCA.Onlyoffice.directEditor.loaded()
	}

	if (OCA.Onlyoffice.inframe) {
		window.parent.postMessage({
			method: 'onShowMessage',
			param: {
				message,
				type,
				props,
			},
		},
		'*')
		return
	}

	switch (type) {
	case 'success':
		showSuccess(message, props)
		break
	case 'error':
		showError(message, props)
		break
	}
}

OCA.Onlyoffice.refreshHistory = function(response, version) {
	let data = {}
	if (response.error) {
		data = { error: response.error }
	} else {
		let currentVersion = 0
		response.forEach((fileVersion) => {
			if (fileVersion.version >= currentVersion) {
				currentVersion = fileVersion.version
			}

			const formatter = new Intl.DateTimeFormat(getCanonicalLocale(), { dateStyle: 'short', timeStyle: 'medium' })
			fileVersion.created = formatter.format(new Date(fileVersion.created * 1000))
			if (fileVersion.changes) {
				fileVersion.changes.forEach((change) => {
					change.created = formatter.format(new Date(change.created + '+00:00'))
				})
			}
		})

		if (version) {
			currentVersion = Math.min(currentVersion, version)
		}

		data = {
			currentVersion,
			history: response,
		}
	}
	OCA.Onlyoffice.docEditor.refreshHistory(data)
}

OCA.Onlyoffice.resize = function() {
	if (OCA.Onlyoffice.device !== 'mobile') {
		return
	}

	const headerHeight = document.getElementById('header')?.offsetHeight ?? 50
	const wrapEl = document.querySelector('#app>iframe')
	if (wrapEl) {
		wrapEl.style.height = (screen.availHeight - headerHeight) + 'px'
		window.scrollTo(0, -1)
		wrapEl.style.height = (window.top.innerHeight - headerHeight) + 'px'
	}
}

OCA.Onlyoffice.resizeEvents = function() {
	if (window.addEventListener) {
		if (/Android/i.test(navigator.userAgent)) {
			window.addEventListener('resize', OCA.Onlyoffice.resize)
		}
		if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
			window.addEventListener('orientationchange', OCA.Onlyoffice.resize)
		}
	}
}

OCA.Onlyoffice.setViewport = function() {
	document.querySelector('meta[name="viewport"]').setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0')
}

OCA.Onlyoffice.getConfigUrl = function() {
	const guestName = localStorage.getItem('nick')
	let configUrl = generateOcsUrl('apps/onlyoffice/api/v1/config/') + (OCA.Onlyoffice.fileId || 0)

	const params = []
	if (OCA.Onlyoffice.filePath) {
		params.push('filePath=' + encodeURIComponent(OCA.Onlyoffice.filePath))
	}
	if (OCA.Onlyoffice.shareToken) {
		params.push('shareToken=' + encodeURIComponent(OCA.Onlyoffice.shareToken))
	}
	if (OCA.Onlyoffice.directToken) {
		document.documentElement.classList.add('onlyoffice-full-page')
		params.push('directToken=' + encodeURIComponent(OCA.Onlyoffice.directToken))
	}
	if (OCA.Onlyoffice.template) {
		params.push('template=true')
	}
	if (guestName && guestName !== 'null') {
		params.push('guestName=' + encodeURIComponent(guestName))
	}
	if (OCA.Onlyoffice.anchor) {
		params.push('anchor=' + encodeURIComponent(OCA.Onlyoffice.anchor))
	}

	if (OCA.Onlyoffice.inframe || OCA.Onlyoffice.directToken) {
		params.push('inframe=true')
	}

	if (OCA.Onlyoffice.inviewer) {
		params.push('inviewer=true')
	}

	if (OCA.Onlyoffice.Desktop) {
		params.push('desktop=true')
	}
	if (params.length) {
		configUrl += '?' + params.join('&')
	}

	return configUrl
}

OCA.Onlyoffice.InitEditor()

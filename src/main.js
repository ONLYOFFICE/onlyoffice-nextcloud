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

/* global _oc_appswebroots */

import { getCurrentUser, getRequestToken } from '@nextcloud/auth'
import { getFilePickerBuilder, showError, showSuccess } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import {
	addNewFileMenuEntry,
	DefaultType,
	File,
	getNewFileMenuEntries,
	getSidebar,
	Permission,
	registerFileAction,
} from '@nextcloud/files'
import {
	getClient,
	getDefaultPropfind,
	getRootPath,
	resultToNode,
} from '@nextcloud/files/dav'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { generateFilePath, generateUrl } from '@nextcloud/router'
import { getSharingToken, isPublicShare } from '@nextcloud/sharing/public'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import DownloadPicker from './views/DownloadPicker.vue'
import AppDarkSvg from '../img/app-dark.svg?raw'
import NewDocxSvg from '../img/new-docx.svg?raw'
import NewPdfSvg from '../img/new-pdf.svg?raw'
import NewPptxSvg from '../img/new-pptx.svg?raw'
import NewXlsxSvg from '../img/new-xlsx.svg?raw'
import { convertFile, createFile } from './services/FileService.ts'
import { getFileExtension } from './utils/files.ts'

import '@nextcloud/dialogs/style.css'

OCA.Onlyoffice = { AppName: 'onlyoffice', context: null, frameSelector: null, ...OCA.Onlyoffice }

OCA.Onlyoffice.setting = loadState(OCA.Onlyoffice.AppName, 'settings')
OCA.Onlyoffice.mobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|BB|PlayBook|IEMobile|Windows Phone|Kindle|Silk|Opera Mini|Macintosh/i.test(navigator.userAgent)
	&& navigator.maxTouchPoints && navigator.maxTouchPoints > 1

/**
 * @param {string} name file name
 * @param {object} context files context with dir and view
 * @param {number} templateId template file id
 * @param {number} targetId target file id to convert from
 * @param {boolean} open whether to open the editor after creation
 * @param {object|null} filesContext files app context for node creation
 */
function createFileOverload(name, context, templateId, targetId, open = true, filesContext = null) {
	if (!context.view) {
		context.view = OCP.Files.Router._router.app.currentView
	}

	createFileProcess(name, context.dir, templateId, targetId, open, async (response) => {
		if (!context.view && filesContext !== null) {
			const file = new File({
				source: filesContext.source + '/' + response.name,
				id: response.id,
				mtime: new Date(),
				mime: response.mimetype,
				name: response.name,
				owner: getCurrentUser().uid || null,
				permissions: Permission.ALL,
				type: 'file',
				root: filesContext?.root || '/files/' + getCurrentUser().uid,
			})
			emit('files:node:created', file)
		} else {
			const viewContents = await context.view.getContents(context.dir)
			if (viewContents.folder && (viewContents.folder.fileid === response.parentId)) {
				const newFile = viewContents.contents.find((node) => node.fileid === response.id)
				if (newFile) {
					emit('files:node:created', newFile)
				}
			}
		}
	})
}

/**
 * @param {string} name file name
 * @param {string} dir directory path
 * @param {number} templateId template file id
 * @param {number} targetId target file id to convert from
 * @param {boolean} open whether to open the editor after creation
 * @param {Function} callback called with the created file response
 */
function createFileProcess(name, dir, templateId, targetId, open, callback) {
	let winEditor = null
	if (((!OCA.Onlyoffice.setting.sameTab && !OCA.Onlyoffice.setting.enableSharing) || OCA.Onlyoffice.mobile || OCA.Onlyoffice.Desktop) && open) {
		const loaderUrl = OCA.Onlyoffice.Desktop ? '' : generateFilePath(OCA.Onlyoffice.AppName, 'template', 'loader.html')
		winEditor = window.open(loaderUrl)
	}

	const createData = {
		name,
		dir,
	}

	if (templateId) {
		createData.templateId = templateId
	}

	if (targetId) {
		createData.targetId = targetId
	}

	if (isPublicShare()) {
		createData.shareToken = encodeURIComponent(getSharingToken())
	}

	createFile(createData).then((response) => {
		if (response.error) {
			if (winEditor) {
				winEditor.close()
			}
			showError(response.error)
			return
		}

		callback(response)

		if (open) {
			const fileName = response.name
			openEditor(response.id, dir, fileName, winEditor)

			OCA.Onlyoffice.context = {
				fileName: response.name,
				dir,
			}
		}

		showSuccess(t(OCA.Onlyoffice.AppName, 'File created'))
	})
}

/**
 * @param {number} fileId file id
 * @param {string} fileDir directory path
 * @param {string} fileName file name
 * @param {Window|null} winEditor pre-opened editor window
 * @param {boolean} isDefault whether this is the default open action
 */
function openEditor(fileId, fileDir, fileName, winEditor, isDefault = true) {
	let filePath = ''
	if (fileName) {
		filePath = fileDir.replace(/\/$/, '') + '/' + fileName
	}
	let url = generateUrl('/apps/' + OCA.Onlyoffice.AppName + '/{fileId}?filePath={filePath}', {
		fileId,
		filePath,
	})

	if (isPublicShare()) {
		url = generateUrl('apps/' + OCA.Onlyoffice.AppName + '/s/{shareToken}?fileId={fileId}', {
			shareToken: encodeURIComponent(getSharingToken()),
			fileId,
		})
	}

	if (winEditor && winEditor.location) {
		setDefaultUrl()
		winEditor.location.href = url
	} else if ((!OCA.Onlyoffice.setting.sameTab && !OCA.Onlyoffice.setting.enableSharing)
		|| OCA.Onlyoffice.mobile || OCA.Onlyoffice.Desktop || (isPublicShare() && !isPublicFileShare()
			&& !OCA.Onlyoffice.setting.sameTab && OCA.Onlyoffice.setting.enableSharing)
		|| (!OCA.Onlyoffice.setting.sameTab && !isDefault)) {
		setDefaultUrl()
		window.open(url, '_blank')
	} else {
		if (OCA.Onlyoffice.setting.enableSharing
			&& !isPublicShare()
			&& (window.OCP?.Files?.Router?.query?.openfile === undefined || window.OCP?.Files?.Router?.query?.openfile === 'false'
				|| window.OCP?.Files?.Router?.query?.enableSharing === undefined
			)) {
			window.OCP?.Files?.Router?.goToRoute(
				null, // use default route
				{ view: 'files', fileid: fileId },
				{ ...OCP.Files.Router.query, openfile: 'true', enableSharing: 'true' },
			)
			url = window.location.href
			setDefaultUrl()
			window.open(url, '_blank')
			return
		}
		OCA.Onlyoffice.frameSelector = '#onlyofficeFrame'
		const iframeEl = document.createElement('iframe')
		iframeEl.id = 'onlyofficeFrame'
		iframeEl.setAttribute('nonce', btoa(getRequestToken()))
		iframeEl.setAttribute('scrolling', 'no')
		iframeEl.setAttribute('allowfullscreen', '')
		iframeEl.src = url + '&inframe=true'
		const iframeContainer = document.createElement('div')
		iframeContainer.className = 'onlyoffice-iframe-container'
		iframeContainer.append(iframeEl)

		const frameContainer = document.getElementById('app-content') ?? document.getElementById('app-content-vue')
		frameContainer.append(iframeContainer)

		document.body.classList.add('onlyoffice-inline')

		const sidebar = getSidebar()
		if (sidebar) {
			sidebar.close()
		}

		const scrollTop = document.getElementById('app-content')?.scrollTop ?? 0
		document.querySelector(OCA.Onlyoffice.frameSelector).style.top = scrollTop + 'px'

		const currentQuery = { ...OCP.Files.Router.query }
		if (isDefault) {
			currentQuery.openfile = 'true'
		} else {
			delete currentQuery.openfile
		}

		window.OCP?.Files?.Router?.goToRoute(
			null, // use default route
			{ view: 'files', fileid: fileId },
			currentQuery,
		)
	}
}

/**
 * Close the inline editor and restore the Files app view
 */
function closeEditor() {
	document.body.classList.remove('onlyoffice-inline')

	const iframeContainer = document.querySelector('.onlyoffice-iframe-container')
	if (iframeContainer !== null) {
		iframeContainer.remove()
	}

	OCA.Onlyoffice.context = null

	setDefaultUrl()
}

/**
 * Reset the Files router URL, removing openfile and enableSharing query params
 */
function setDefaultUrl() {
	const { openfile, enableSharing, ...query } = OCP.Files.Router.query
	window.OCP?.Files?.Router?.goToRoute(
		null, // use default route
		{ view: 'files', fileid: undefined },
		query,
	)
}

/**
 * Open or close the sharing sidebar for the currently open file
 */
function openShareDialog() {
	if (OCA.Onlyoffice.context) {
		const sidebar = getSidebar()
		if (!sidebar.isOpen) {
			const client = getClient()
			client.stat(`${getRootPath()}${OCA.Onlyoffice.context.dir + '/' + OCA.Onlyoffice.context.fileName}`, {
				details: true,
				data: getDefaultPropfind(),
			}).then((result) => {
				const node = resultToNode(result.data)
				emit('files:node:updated', node)
				sidebar.open(node)
				sidebar.setActiveTab('sharing')
			})
		} else {
			sidebar.close()
		}
	}
}

/**
 * Refresh the versions sidebar tab for the currently open file
 */
function refreshVersionsDialog() {
	if (OCA.Onlyoffice.context) {
		const sidebar = getSidebar()
		if (sidebar.isOpen) {
			sidebar.close()
			const client = getClient()
			client.stat(`${getRootPath()}${OCA.Onlyoffice.context.dir + '/' + OCA.Onlyoffice.context.fileName}`, {
				details: true,
				data: getDefaultPropfind(),
			}).then((result) => {
				const node = resultToNode(result.data)
				emit('files:node:updated', node)
				sidebar.open(node)
				sidebar.setActiveTab('versionsTabView')
			})
		}
	}
}

/**
 * @param {object} params
 * @param {object[]} params.nodes Nextcloud file nodes
 * @param {object} params.view current files view
 * @param {string} params.dir current directory path
 * @param {boolean} params.isDefault whether triggered as the default action
 * @return {null} null
 */
async function fileOpenHandler({ nodes, view, dir, isDefault = true }) {
	const file = nodes[0]
	if (OCA.Onlyoffice.context !== null
		&& document.querySelector('.onlyoffice-iframe-container')
		&& !OCA.Onlyoffice.Desktop) {
		return null
	}

	openEditor(file.fileid, file.dirname, file.basename, 0, isDefault)

	OCA.Onlyoffice.context = {
		fileName: file.basename,
		dir: file.dirname,
	}

	return null
}

/**
 * @param {object} params
 * @param {object[]} params.nodes Nextcloud file nodes
 * @param {object} params.view current files view
 * @param {string} params.dir current directory path
 * @return {null} null
 */
async function fileConvertHandler({ nodes, view, dir }) {
	const file = nodes[0]
	fileConvert(file.fileid, async (response) => {
		const viewContents = await view.getContents(dir)

		if (viewContents.folder && (viewContents.folder.fileid === response.parentId)) {
			const newFile = viewContents.contents.find((node) => node.fileid === response.id)
			if (newFile) {
				emit('files:node:created', newFile)
			}
		}
	})

	return null
}

/**
 * @param {number} fileId file id to convert
 * @param {Function} callback called with the converted file response
 */
function fileConvert(fileId, callback) {
	const convertData = {
		fileId,
	}

	if (isPublicShare()) {
		convertData.shareToken = encodeURIComponent(getSharingToken())
	}

	convertFile(convertData).then((response) => {
		if (response.error) {
			showError(response.error)
			return
		}

		callback(response)

		showSuccess(t(OCA.Onlyoffice.AppName, 'File has been converted. Its content might look different.'))
	})
}

/**
 * @param {object} file Nextcloud file node
 * @param file.nodes
 * @return {null} null
 */
async function fileDownloadAsHandler({ nodes }) {
	const file = nodes[0]
	const fileName = file.basename
	const fileId = file.fileid
	const extension = getFileExtension(fileName)
	const saveasFormats = OCA.Onlyoffice.setting.formats[extension].saveas

	spawnDialog(DownloadPicker, {
		fileName,
		fileId,
		extension,
		saveasFormats,
	})

	return null
}

/**
 * @param {string} name new file name
 * @param {object} filelist files list context with dir
 * @param {object|null} filesContext files app context for node creation
 */
function openFormPicker(name, filelist, filesContext = null) {
	const filterMimes = [
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	]

	const startDir = filelist.getCurrentDirectory ? filelist.getCurrentDirectory() : filelist.dir

	getFilePickerBuilder(t(OCA.Onlyoffice.AppName, 'Create new PDF form'))
		.setMimeTypeFilter(filterMimes)
		.startAt(startDir)
		.addButton({
			label: t(OCA.Onlyoffice.AppName, 'Blank'),
			variant: 'secondary',
			callback: () => createFileOverload(name, filelist, 0, 0, true, filesContext),
		})
		.addButton({
			label: t(OCA.Onlyoffice.AppName, 'From text document'),
			callback: (nodes) => {
				if (!nodes[0]) {
					return
				}
				const targetId = nodes[0].id ?? 0
				createFileOverload(name, filelist, 0, targetId, true, filesContext)
			},
			variant: 'primary',
		})
		.build()
		.pickNodes()
}

/**
 * @param {object} params
 * @param {object[]} params.nodes Nextcloud file nodes
 * @param {object} params.view current files view
 * @param {string} params.dir current directory path
 * @return {null} null
 */
async function fileCreateFormHandler({ nodes, view, dir }) {
	const file = nodes[0]
	const name = file.basename.replace(/\.[^.]+$/, '.pdf')
	const context = {
		dir: file.dirname,
		view,
	}

	createFileOverload(name, context, 0, file.fileid, false)

	return null
}

/**
 * Register all ONLYOFFICE file actions with the Nextcloud Files app
 */
function registerFileActions() {
	const formats = OCA.Onlyoffice.setting.formats

	const getConfig = function(file) {
		const fileExt = getFileExtension(file?.extension || file?.displayname)
		const config = formats[fileExt]

		return config
	}

	registerFileAction({
		id: 'onlyoffice-open-def',
		displayName: () => t(OCA.Onlyoffice.AppName, 'Open in ONLYOFFICE'),
		iconSvgInline: () => AppDarkSvg,
		enabled: ({ nodes }) => {
			if (OCA.Onlyoffice.setting.restrictExternalStorage
				&& (nodes[0].attributes['mount-type'] === 'external' || nodes[0].attributes['mount-type'] === 'external-root')) {
				return false
			}

			const fileExt = getFileExtension(nodes[0]?.extension || nodes[0]?.displayname)
			const config = formats[fileExt]

			if (!config
				|| !config.def
				|| (isPublicFileShare() && (_oc_appswebroots.richdocuments
					|| (_oc_appswebroots.files_pdfviewer && fileExt === 'pdf')
					|| (_oc_appswebroots.text && fileExt === 'txt')))) {
				return false
			}

			if (Permission.READ !== (nodes[0].permissions & Permission.READ)) {
				return false
			}

			return true
		},
		exec: fileOpenHandler,
		default: DefaultType.HIDDEN,
		order: -1,
	})

	registerFileAction({
		id: 'onlyoffice-open',
		displayName: () => t(OCA.Onlyoffice.AppName, 'Open in ONLYOFFICE'),
		iconSvgInline: () => AppDarkSvg,
		enabled: ({ nodes }) => {
			if (OCA.Onlyoffice.setting.restrictExternalStorage
				&& (nodes[0].attributes['mount-type'] === 'external' || nodes[0].attributes['mount-type'] === 'external-root')) {
				return false
			}

			const config = getConfig(nodes[0])

			if (!config) {
				return false
			}
			if (config.def) {
				return false
			}

			if (Permission.READ !== (nodes[0].permissions & Permission.READ)) {
				return false
			}

			return true
		},
		exec({ nodes, view, dir }) {
			fileOpenHandler({ nodes, view, dir, isDefault: false })
		},
	})

	// Skip the rest if the page is public file share
	if (isPublicFileShare()) {
		return
	}

	registerFileAction({
		id: 'onlyoffice-convert',
		displayName: () => t(OCA.Onlyoffice.AppName, 'Convert with ONLYOFFICE'),
		iconSvgInline: () => AppDarkSvg,
		enabled: ({ nodes }) => {
			const config = getConfig(nodes[0])

			if (!config) {
				return false
			}
			if (!config.conv) {
				return false
			}

			const required = isPublicShare() ? Permission.UPDATE : Permission.READ
			if (required !== (nodes[0].permissions & required)) {
				return false
			}

			if (nodes[0].attributes['mount-type'] === 'shared') {
				if (required !== (nodes[0].attributes['share-permissions'] & required)) {
					return false
				}

				const attributes = JSON.parse(nodes[0].attributes['share-attributes'])
				const downloadAttribute = attributes.find((attribute) => attribute.scope === 'permissions' && attribute.key === 'download')
				if (downloadAttribute !== undefined && downloadAttribute.value === false) {
					return false
				}
			}

			return true
		},
		exec: fileConvertHandler,
	})

	registerFileAction({
		id: 'onlyoffice-create-form',
		displayName: () => t(OCA.Onlyoffice.AppName, 'Create form'),
		iconSvgInline: () => AppDarkSvg,
		enabled: ({ nodes }) => {
			const config = getConfig(nodes[0])

			if (!config) {
				return false
			}
			if (!config.createForm) {
				return false
			}

			const required = isPublicShare() ? Permission.UPDATE : Permission.READ
			if (required !== (nodes[0].permissions & required)) {
				return false
			}

			if (nodes[0].attributes['mount-type'] === 'shared') {
				if (required !== (nodes[0].attributes['share-permissions'] & required)) {
					return false
				}

				const attributes = JSON.parse(nodes[0].attributes['share-attributes'])
				const downloadAttribute = attributes.find((attribute) => attribute.scope === 'permissions' && attribute.key === 'download')
				if (downloadAttribute !== undefined && downloadAttribute.value === false) {
					return false
				}
			}

			return true
		},
		exec: fileCreateFormHandler,
	})

	if (!isPublicShare()) {
		registerFileAction({
			id: 'onlyoffice-download-as',
			displayName: () => t(OCA.Onlyoffice.AppName, 'Download as'),
			iconSvgInline: () => AppDarkSvg,
			enabled: ({ nodes }) => {
				if (OCA.Onlyoffice.setting.disableDownload) {
					return false
				}
				const config = getConfig(nodes[0])

				if (!config) {
					return false
				}
				if (!config.saveas) {
					return false
				}

				if (Permission.READ !== (nodes[0].permissions & Permission.READ)) {
					return false
				}

				if (nodes[0].attributes['mount-type'] === 'shared') {
					const attributes = JSON.parse(nodes[0].attributes['share-attributes'])
					const downloadAttribute = attributes.find((attribute) => attribute.scope === 'permissions' && attribute.key === 'download')
					if (downloadAttribute !== undefined && downloadAttribute.value === false) {
						return false
					}
				}

				return true
			},
			exec: fileDownloadAsHandler,
		})
	}
}

/**
 * Register ONLYOFFICE entries in the new file menu
 */
function registerNewFileMenu() {
	const alreadyRegistered = getNewFileMenuEntries().some((menu) => menu.id.includes('onlyoffice'))

	if (isPublicShare() && !alreadyRegistered) {
		// Document
		addNewFileMenuEntry({
			id: 'new-onlyoffice-docx',
			displayName: t(OCA.Onlyoffice.AppName, 'New document'),
			enabled: (folder) => {
				return (folder.permissions & Permission.CREATE) !== 0
			},
			iconSvgInline: NewDocxSvg,
			order: 21,
			handler: (context) => {
				const name = t(OCA.Onlyoffice.AppName, 'New document')
				const dirContext = { dir: context.path }
				createFileOverload(name + '.docx', dirContext, null, null, true, context)
			},
		})

		// Spreadsheet
		addNewFileMenuEntry({
			id: 'new-onlyoffice-xlsx',
			displayName: t(OCA.Onlyoffice.AppName, 'New spreadsheet'),
			enabled: (folder) => {
				return (folder.permissions & Permission.CREATE) !== 0
			},
			iconSvgInline: NewXlsxSvg,
			order: 22,
			handler: (context) => {
				const name = t(OCA.Onlyoffice.AppName, 'New spreadsheet')
				const dirContext = { dir: context.path }
				createFileOverload(name + '.xlsx', dirContext, null, null, true, context)
			},
		})

		// Presentation
		addNewFileMenuEntry({
			id: 'new-onlyoffice-pptx',
			displayName: t(OCA.Onlyoffice.AppName, 'New presentation'),
			enabled: (folder) => {
				return (folder.permissions & Permission.CREATE) !== 0
			},
			iconSvgInline: NewPptxSvg,
			order: 23,
			handler: (context) => {
				const name = t(OCA.Onlyoffice.AppName, 'New presentation')
				const dirContext = { dir: context.path }
				createFileOverload(name + '.pptx', dirContext, null, null, true, context)
			},
		})
	}

	// PDF Form
	addNewFileMenuEntry({
		id: 'new-onlyoffice-pdf',
		displayName: t(OCA.Onlyoffice.AppName, 'New PDF form'),
		enabled: (folder) => {
			return (folder.permissions & Permission.CREATE) !== 0
		},
		iconSvgInline: NewPdfSvg,
		order: 24,
		handler: (context) => {
			const name = t(OCA.Onlyoffice.AppName, 'New PDF form')
			const dirContext = { dir: context.path }
			openFormPicker(name + '.pdf', dirContext, context)
		},
	})
}

/**
 * @return {boolean} true if the current page is a public file share
 */
function isPublicFileShare() {
	return loadState('files_sharing', 'view', null) === 'public-file-share'
}

// Expose cross-bundle API surface consumed by listener.js and share.js
Object.assign(OCA.Onlyoffice, {
	CloseEditor: closeEditor,
	OpenShareDialog: openShareDialog,
	RefreshVersionsDialog: refreshVersionsDialog,
})

if (!OCA.Onlyoffice._initialized) {
	OCA.Onlyoffice._initialized = true
	if (!isPublicFileShare()) {
		registerNewFileMenu()
	}
	registerFileActions()
}

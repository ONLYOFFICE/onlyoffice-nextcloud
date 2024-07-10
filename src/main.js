/**
 *
 * (c) Copyright Ascensio System SIA 2024
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

/* global _, $, _oc_appswebroots */

import {
	FileAction,
	registerFileAction,
	Permission,
	DefaultType,
	File,
	addNewFileMenuEntry,
	davGetClient,
	davRootPath,
	davGetDefaultPropfind,
	davResultToNode,
} from '@nextcloud/files'
import { emit } from '@nextcloud/event-bus'
import AppDarkSvg from '!!raw-loader!../img/app-dark.svg'
import NewPdfSvg from '!!raw-loader!../img/new-pdf.svg';

/**
 * @param {object} OCA Nextcloud OCA object
 */
(function(OCA) {

	OCA.Onlyoffice = _.extend({
		AppName: 'onlyoffice',
		context: null,
		folderUrl: null,
		frameSelector: null,
	}, OCA.Onlyoffice)

	OCA.Onlyoffice.setting = OCP.InitialState.loadState(OCA.Onlyoffice.AppName, 'settings')
	OCA.Onlyoffice.mobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|BB|PlayBook|IEMobile|Windows Phone|Kindle|Silk|Opera Mini|Macintosh/i.test(navigator.userAgent)
                            && navigator.maxTouchPoints && navigator.maxTouchPoints > 1

	OCA.Onlyoffice.CreateFile = function(name, fileList, templateId, targetId, open = true) {
		const dir = fileList.getCurrentDirectory()

		OCA.Onlyoffice.CreateFileProcess(name, dir, templateId, targetId, open, (response) => {
			fileList.add(response, { animate: true })
		})
	}

	OCA.Onlyoffice.CreateFileOverload = function(name, context, templateId, targetId, open = true) {
		if (!context.view) {
			context.view = OCP.Files.Router._router.app.currentView
		}

		OCA.Onlyoffice.CreateFileProcess(name, context.dir, templateId, targetId, open, async (response) => {
			const viewContents = await context.view.getContents(context.dir)

			if (viewContents.folder && (viewContents.folder.fileid === response.parentId)) {
				const newFile = viewContents.contents.find(node => node.fileid === response.id)
				if (newFile) emit('files:node:created', newFile)
			}
		})
	}

	OCA.Onlyoffice.CreateFileProcess = function(name, dir, templateId, targetId, open, callback) {
		let winEditor = null
		if ((!OCA.Onlyoffice.setting.sameTab || OCA.Onlyoffice.mobile || OCA.Onlyoffice.Desktop) && open) {
			const loaderUrl = OCA.Onlyoffice.Desktop ? '' : OC.filePath(OCA.Onlyoffice.AppName, 'templates', 'loader.html')
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

		if ($('#isPublic').val()) {
			createData.shareToken = encodeURIComponent($('#sharingToken').val())
		}

		$.post(OC.generateUrl('apps/' + OCA.Onlyoffice.AppName + '/ajax/new'),
			createData,
			function onSuccess(response) {
				if (response.error) {
					if (winEditor) {
						winEditor.close()
					}
					OCP.Toast.error(response.error)
					return
				}

				callback(response)

				if (open) {
					const fileName = response.name
					const extension = OCA.Onlyoffice.getFileExtension(fileName)
					const forceEdit = OCA.Onlyoffice.setting.formats[extension].fillForms
					OCA.Onlyoffice.OpenEditor(response.id, dir, fileName, winEditor, forceEdit)

					OCA.Onlyoffice.context = {
						fileName: response.name,
						dir,
					}
				}

				OCP.Toast.success(t(OCA.Onlyoffice.AppName, 'File created'))
			},
		)
	}

	OCA.Onlyoffice.OpenEditor = function(fileId, fileDir, fileName, winEditor, forceEdit) {
		let filePath = ''
		if (fileName) {
			filePath = fileDir.replace(/\/$/, '') + '/' + fileName
		}
		let url = OC.generateUrl('/apps/' + OCA.Onlyoffice.AppName + '/{fileId}?filePath={filePath}',
			{
				fileId,
				filePath,
			})

		if ($('#isPublic').val()) {
			url = OC.generateUrl('apps/' + OCA.Onlyoffice.AppName + '/s/{shareToken}?fileId={fileId}',
				{
					shareToken: encodeURIComponent($('#sharingToken').val()),
					fileId,
				})
		}

		if (forceEdit) {
			url += '&forceEdit=true'
		}

		if (winEditor && winEditor.location) {
			winEditor.location.href = url
		} else if (!OCA.Onlyoffice.setting.sameTab || OCA.Onlyoffice.mobile || OCA.Onlyoffice.Desktop) {
			winEditor = window.open(url, '_blank')
		} else if ($('#isPublic').val() === '1' && $('#mimetype').val() !== 'httpd/unix-directory') {
			location.href = url
		} else {
			OCA.Onlyoffice.frameSelector = '#onlyofficeFrame'
			const $iframe = $('<iframe id="onlyofficeFrame" nonce="' + btoa(OC.requestToken) + '" scrolling="no" allowfullscreen src="' + url + '&inframe=true" />')

			const frameContainer = $('#app-content').length > 0 ? $('#app-content') : $('#app-content-vue')
			frameContainer.append($iframe)

			$('body').addClass('onlyoffice-inline')

			if (OCA.Files.Sidebar) {
				OCA.Files.Sidebar.close()
			}

			const scrollTop = $('#app-content').scrollTop()
			$(OCA.Onlyoffice.frameSelector).css('top', scrollTop)

			OCA.Onlyoffice.folderUrl = location.href
			window.history.pushState(null, null, url)
		}
	}

	OCA.Onlyoffice.CloseEditor = function() {
		OCA.Onlyoffice.frameSelector = null

		$('body').removeClass('onlyoffice-inline')

		OCA.Onlyoffice.context = null

		const url = OCA.Onlyoffice.folderUrl
		if (url) {
			window.history.pushState(null, null, url)
			OCA.Onlyoffice.folderUrl = null
		}
	}

	OCA.Onlyoffice.OpenShareDialog = function() {
		if (OCA.Onlyoffice.context) {
			if (!$('#app-sidebar-vue').is(':visible')) {
				OCA.Files.Sidebar.open(OCA.Onlyoffice.context.dir + '/' + OCA.Onlyoffice.context.fileName)
				OCA.Files.Sidebar.setActiveTab('sharing')
			} else {
				OCA.Files.Sidebar.close()
			}
		}
	}

	OCA.Onlyoffice.RefreshVersionsDialog = function() {
		if (OCA.Onlyoffice.context) {
			if ($('#app-sidebar-vue').is(':visible')) {
				OCA.Files.Sidebar.close()
				OCA.Files.Sidebar.open(OCA.Onlyoffice.context.dir + '/' + OCA.Onlyoffice.context.fileName)
				OCA.Files.Sidebar.setActiveTab('versionsTabView')
			}
		}
	}

	OCA.Onlyoffice.FileClick = function(fileName, context) {
		const fileInfoModel = context.fileInfoModel || context.fileList.getModelForFile(fileName)
		const fileId = context.fileId || (context.$file && context.$file[0].dataset.id) || fileInfoModel.id
		const winEditor = !fileInfoModel && !OCA.Onlyoffice.setting.sameTab ? document : null

		OCA.Onlyoffice.OpenEditor(fileId, context.dir, fileName, winEditor)

		OCA.Onlyoffice.context = context
		OCA.Onlyoffice.context.fileName = fileName
	}

	OCA.Onlyoffice.FileClickExec = async function(file, view, dir) {
		OCA.Onlyoffice.OpenEditor(file.fileid, dir, file.basename, 0)

		OCA.Onlyoffice.context = {
			fileName: file.basename,
			dir,
		}

		return null
	}

	OCA.Onlyoffice.FileConvertClick = function(fileName, context) {
		const fileInfoModel = context.fileInfoModel || context.fileList.getModelForFile(fileName)
		const fileList = context.fileList
		const fileId = context.$file ? context.$file[0].dataset.id : fileInfoModel.id

		OCA.Onlyoffice.FileConvert(fileId, (response) => {
			if (response.parentId === fileList.dirInfo.id) {
				fileList.add(response, { animate: true })
			}
		})
	}

	OCA.Onlyoffice.FileConvertClickExec = async function(file, view, dir) {
		OCA.Onlyoffice.FileConvert(file.fileid, async (response) => {
			const viewContents = await view.getContents(dir)

			if (viewContents.folder && (viewContents.folder.fileid === response.parentId)) {
				const newFile = viewContents.contents.find(node => node.fileid === response.id)
				if (newFile) emit('files:node:created', newFile)
			}
		})

		return null
	}

	OCA.Onlyoffice.FileConvert = function(fileId, callback) {
		const convertData = {
			fileId,
		}

		if ($('#isPublic').val()) {
			convertData.shareToken = encodeURIComponent($('#sharingToken').val())
		}

		$.post(OC.generateUrl('apps/' + OCA.Onlyoffice.AppName + '/ajax/convert'),
			convertData,
			function onSuccess(response) {
				if (response.error) {
					OCP.Toast.error(response.error)
					return
				}

				callback(response)

				OCP.Toast.success(t(OCA.Onlyoffice.AppName, 'File has been converted. Its content might look different.'))
			})
	}

	OCA.Onlyoffice.DownloadClick = function(fileName, context) {
		const fileId = context.fileInfoModel.id

		OCA.Onlyoffice.Download(fileName, fileId)
	}

	OCA.Onlyoffice.DownloadClickExec = async function(file) {
		OCA.Onlyoffice.Download(file.basename, file.fileid)

		return null
	}

	OCA.Onlyoffice.Download = function(fileName, fileId) {
		$.get(OC.filePath(OCA.Onlyoffice.AppName, 'templates', 'downloadPicker.html'),
			function(tmpl) {
				const dialog = $(tmpl).octemplate({
					dialog_name: 'download-picker',
					dialog_title: t('onlyoffice', 'Download as'),
				})

				$(dialog[0].querySelectorAll('p')).text(t(OCA.Onlyoffice.AppName, 'Choose a format to convert {fileName}', { fileName }))

				const extension = OCA.Onlyoffice.getFileExtension(fileName)
				const selectNode = dialog[0].querySelectorAll('select')[0]
				const optionNodeOrigin = selectNode.querySelectorAll('option')[0]

				$(optionNodeOrigin).attr('data-value', extension)
				$(optionNodeOrigin).text(t(OCA.Onlyoffice.AppName, 'Origin format'))

				dialog[0].dataset.format = extension
				selectNode.onchange = function() {
					dialog[0].dataset.format = $('#onlyoffice-download-select option:selected').attr('data-value')
				}

				OCA.Onlyoffice.setting.formats[extension].saveas.forEach(ext => {
					const optionNode = optionNodeOrigin.cloneNode(true)

					$(optionNode).attr('data-value', ext)
					$(optionNode).text(ext)

					selectNode.append(optionNode)
				})

				$('body').append(dialog)

				$('#download-picker').ocdialog({
					closeOnEscape: true,
					modal: true,
					buttons: [{
						text: t('core', 'Cancel'),
						classes: 'cancel',
						click() {
							$(this).ocdialog('close')
						},
					}, {
						text: t('onlyoffice', 'Download'),
						classes: 'primary',
						click() {
							const format = this.dataset.format
							const downloadLink = OC.generateUrl('apps/' + OCA.Onlyoffice.AppName + '/downloadas?fileId={fileId}&toExtension={toExtension}', {
								fileId,
								toExtension: format,
							})

							location.href = downloadLink
							$(this).ocdialog('close')
						},
					}],
				})
			})
	}

	OCA.Onlyoffice.OpenFormPicker = function(name, filelist) {
		const filterMimes = [
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		]

		const buttons = [
			{
				text: t(OCA.Onlyoffice.AppName, 'Blank'),
				type: 'blank',
			},
			{
				text: t(OCA.Onlyoffice.AppName, 'From text document'),
				type: 'target',
				defaultButton: true,
			},
		]

		OC.dialogs.filepicker(t(OCA.Onlyoffice.AppName, 'Create new PDF form'),
			async function(filePath, type) {
				let dialogFileList = OC.dialogs.filelist
				let targetId = 0

				const targetFileName = OC.basename(filePath)
				const targetFolderPath = OC.dirname(filePath)

				if (!dialogFileList) {
					const results = await davGetClient().getDirectoryContents(davRootPath + targetFolderPath, {
						details: true,
						data: davGetDefaultPropfind(),
					})
					dialogFileList = results.data.map((result) => davResultToNode(result))
				}

				if (type === 'target') {
					dialogFileList.forEach(item => {
						const itemName = item.name ? item.name : item.basename
						if (itemName === targetFileName) {
							targetId = item.id ? item.id : item.fileid
						}
					})
				}

				if (filelist.getCurrentDirectory) {
					OCA.Onlyoffice.CreateFile(name, filelist, 0, targetId)
				} else {
					OCA.Onlyoffice.CreateFileOverload(name, filelist, 0, targetId)
				}
			},
			false,
			filterMimes,
			true,
			OC.dialogs.FILEPICKER_TYPE_CUSTOM,
			filelist.getCurrentDirectory ? filelist.getCurrentDirectory() : filelist.dir,
			{
				buttons,
			})
	}

	OCA.Onlyoffice.CreateFormClick = function(fileName, context) {
		const fileList = context.fileList
		const name = fileName.replace(/\.[^.]+$/, '.pdf')
		const targetId = context.fileInfoModel.id

		OCA.Onlyoffice.CreateFile(name, fileList, 0, targetId, false)
	}

	OCA.Onlyoffice.CreateFormClickExec = async function(file, view, dir) {
		const name = file.basename.replace(/\.[^.]+$/, '.pdf')
		const context = {
			dir,
			view,
		}

		OCA.Onlyoffice.CreateFileOverload(name, context, 0, file.fileid, false)

		return null
	}

	OCA.Onlyoffice.registerAction = function() {
		const formats = OCA.Onlyoffice.setting.formats

		const getConfig = function(file) {
			const fileExt = file?.extension?.toLowerCase()?.replace('.', '')
			const config = formats[fileExt]

			return config
		}

		if (OCA.Files && OCA.Files.fileActions) {
			$.each(formats, function(ext, config) {
				if (!config.mime) {
					return true
				}

				const mimeTypes = config.mime
				mimeTypes.forEach((mime) => {
					OCA.Files.fileActions.registerAction({
						name: 'onlyofficeOpen',
						displayName: t(OCA.Onlyoffice.AppName, 'Open in ONLYOFFICE'),
						mime,
						permissions: OC.PERMISSION_READ,
						iconClass: 'icon-onlyoffice-open',
						actionHandler: OCA.Onlyoffice.FileClick,
					})

					if (config.def) {
						OCA.Files.fileActions.setDefault(mime, 'onlyofficeOpen')
					}

					if (config.conv) {
						OCA.Files.fileActions.registerAction({
							name: 'onlyofficeConvert',
							displayName: t(OCA.Onlyoffice.AppName, 'Convert with ONLYOFFICE'),
							mime,
							permissions: ($('#isPublic').val() ? OC.PERMISSION_UPDATE : OC.PERMISSION_READ),
							iconClass: 'icon-onlyoffice-convert',
							actionHandler: OCA.Onlyoffice.FileConvertClick,
						})
					}

					if (config.createForm) {
						OCA.Files.fileActions.registerAction({
							name: 'onlyofficeCreateForm',
							displayName: t(OCA.Onlyoffice.AppName, 'Create form'),
							mime,
							permissions: ($('#isPublic').val() ? OC.PERMISSION_UPDATE : OC.PERMISSION_READ),
							iconClass: 'icon-onlyoffice-create',
							actionHandler: OCA.Onlyoffice.CreateFormClick,
						})
					}

					if (config.saveas && !$('#isPublic').val()) {
						OCA.Files.fileActions.registerAction({
							name: 'onlyofficeDownload',
							displayName: t(OCA.Onlyoffice.AppName, 'Download as'),
							mime,
							permissions: OC.PERMISSION_READ,
							iconClass: 'icon-onlyoffice-download',
							actionHandler: OCA.Onlyoffice.DownloadClick,
						})
					}
				})
			})
		} else {
			registerFileAction(new FileAction({
				id: 'onlyoffice-open-def',
				displayName: () => t(OCA.Onlyoffice.AppName, 'Open in ONLYOFFICE'),
				iconSvgInline: () => AppDarkSvg,
				enabled: (files) => {
					const config = getConfig(files[0])

					if (!config) return
					if (!config.def) return

					if (Permission.READ !== (files[0].permissions & Permission.READ)) { return false }

					return true
				},
				exec: OCA.Onlyoffice.FileClickExec,
				default: DefaultType.HIDDEN,
			}))

			registerFileAction(new FileAction({
				id: 'onlyoffice-open',
				displayName: () => t(OCA.Onlyoffice.AppName, 'Open in ONLYOFFICE'),
				iconSvgInline: () => AppDarkSvg,
				enabled: (files) => {
					const config = getConfig(files[0])

					if (!config) return false
					if (config.def) return false

					if (Permission.READ !== (files[0].permissions & Permission.READ)) { return false }

					return true
				},
				exec: OCA.Onlyoffice.FileClickExec,
			}))

			registerFileAction(new FileAction({
				id: 'onlyoffice-convert',
				displayName: () => t(OCA.Onlyoffice.AppName, 'Convert with ONLYOFFICE'),
				iconSvgInline: () => AppDarkSvg,
				enabled: (files) => {
					const config = getConfig(files[0])

					if (!config) return
					if (!config.conv) return false

					const required = $('#isPublic').val() ? Permission.UPDATE : Permission.READ
					if (required !== (files[0].permissions & required)) { return false }

					if (files[0].attributes['mount-type'] === 'shared') {
						if (required !== (files[0].attributes['share-permissions'] & required)) { return false }

						const attributes = JSON.parse(files[0].attributes['share-attributes'])
						const downloadAttribute = attributes.find((attribute) => attribute.scope === 'permissions' && attribute.key === 'download')
						if (downloadAttribute !== undefined && downloadAttribute.enabled === false) { return false }
					}

					return true
				},
				exec: OCA.Onlyoffice.FileConvertClickExec,
			}))

			registerFileAction(new FileAction({
				id: 'onlyoffice-create-form',
				displayName: () => t(OCA.Onlyoffice.AppName, 'Create form'),
				iconSvgInline: () => AppDarkSvg,
				enabled: (files) => {
					const config = getConfig(files[0])

					if (!config) return
					if (!config.createForm) return false

					const required = $('#isPublic').val() ? Permission.UPDATE : Permission.READ
					if (required !== (files[0].permissions & required)) { return false }

					if (files[0].attributes['mount-type'] === 'shared') {
						if (required !== (files[0].attributes['share-permissions'] & required)) { return false }

						const attributes = JSON.parse(files[0].attributes['share-attributes'])
						const downloadAttribute = attributes.find((attribute) => attribute.scope === 'permissions' && attribute.key === 'download')
						if (downloadAttribute !== undefined && downloadAttribute.enabled === false) { return false }
					}

					return true
				},
				exec: OCA.Onlyoffice.CreateFormClickExec,
			}))

			if (!$('#isPublic').val()) {
				registerFileAction(new FileAction({
					id: 'onlyoffice-download-as',
					displayName: () => t(OCA.Onlyoffice.AppName, 'Download as'),
					iconSvgInline: () => AppDarkSvg,
					enabled: (files) => {
						const config = getConfig(files[0])

						if (!config) return
						if (!config.saveas) return false

						if (Permission.READ !== (files[0].permissions & Permission.READ)) { return false }

						if (files[0].attributes['mount-type'] === 'shared') {
							const attributes = JSON.parse(files[0].attributes['share-attributes'])
							const downloadAttribute = attributes.find((attribute) => attribute.scope === 'permissions' && attribute.key === 'download')
							if (downloadAttribute !== undefined && downloadAttribute.enabled === false) { return false }
						}

						return true
					},
					exec: OCA.Onlyoffice.DownloadClickExec,
				}))
			}
		}
	}

	OCA.Onlyoffice.registerNewFileMenu = function() {
		addNewFileMenuEntry({
			id: 'new-onlyoffice-pdf',
			displayName: t(OCA.Onlyoffice.AppName, 'New PDF form'),
			enabled: (folder) => {
				if (Permission.CREATE !== (folder.permissions & Permission.CREATE)) { return false }
				if (Permission.CREATE !== (folder.attributes['share-permissions'] & Permission.CREATE)) { return false }

				return true
			},
			iconSvgInline: NewPdfSvg,
			handler: (folder) => {
				const name = t(OCA.Onlyoffice.AppName, 'New PDF form')
				const context = { dir: folder.path }

				OCA.Onlyoffice.OpenFormPicker(name + '.pdf', context)
			},
		})
	}

	OCA.Onlyoffice.NewFileMenu = {
		attach(menu) {
			const fileList = menu.fileList

			if (fileList.id !== 'files' && fileList.id !== 'files.public') {
				return
			}

			if ($('#isPublic').val() === '1' && $('#mimetype').val() === 'httpd/unix-directory') {
				menu.addMenuEntry({
					id: 'onlyofficeDocx',
					displayName: t(OCA.Onlyoffice.AppName, 'New document'),
					templateName: t(OCA.Onlyoffice.AppName, 'New document'),
					iconClass: 'icon-onlyoffice-new-docx',
					fileType: 'docx',
					actionHandler(name) {
						if (!$('#isPublic').val() && OCA.Onlyoffice.TemplateExist('document')) {
							OCA.Onlyoffice.OpenTemplatePicker(name, '.docx', 'document')
						} else {
							OCA.Onlyoffice.CreateFile(name + '.docx', fileList)
						}
					},
				})

				menu.addMenuEntry({
					id: 'onlyofficeXlsx',
					displayName: t(OCA.Onlyoffice.AppName, 'New spreadsheet'),
					templateName: t(OCA.Onlyoffice.AppName, 'New spreadsheet'),
					iconClass: 'icon-onlyoffice-new-xlsx',
					fileType: 'xlsx',
					actionHandler(name) {
						if (!$('#isPublic').val() && OCA.Onlyoffice.TemplateExist('spreadsheet')) {
							OCA.Onlyoffice.OpenTemplatePicker(name, '.xlsx', 'spreadsheet')
						} else {
							OCA.Onlyoffice.CreateFile(name + '.xlsx', fileList)
						}
					},
				})

				menu.addMenuEntry({
					id: 'onlyofficePpts',
					displayName: t(OCA.Onlyoffice.AppName, 'New presentation'),
					templateName: t(OCA.Onlyoffice.AppName, 'New presentation'),
					iconClass: 'icon-onlyoffice-new-pptx',
					fileType: 'pptx',
					actionHandler(name) {
						if (!$('#isPublic').val() && OCA.Onlyoffice.TemplateExist('presentation')) {
							OCA.Onlyoffice.OpenTemplatePicker(name, '.pptx', 'presentation')
						} else {
							OCA.Onlyoffice.CreateFile(name + '.pptx', fileList)
						}
					},
				})

				if (OCA.Onlyoffice.GetTemplates) {
					OCA.Onlyoffice.GetTemplates()
				}
			}

			menu.addMenuEntry({
				id: 'onlyofficePdf',
				displayName: t(OCA.Onlyoffice.AppName, 'New PDF form'),
				templateName: t(OCA.Onlyoffice.AppName, 'New PDF form'),
				iconClass: 'icon-onlyoffice-new-pdf',
				fileType: 'pdf',
				actionHandler(name) {
					OCA.Onlyoffice.OpenFormPicker(name + '.pdf', fileList)
				},
			})
		},
	}

	OCA.Onlyoffice.getFileExtension = function(fileName) {
		const extension = fileName.substr(fileName.lastIndexOf('.') + 1).toLowerCase()
		return extension
	}

	const initPage = function() {
		if ($('#isPublic').val() === '1' && $('#mimetype').val() !== 'httpd/unix-directory') {
			// file by shared link
			const fileName = $('#filename').val()
			const extension = OCA.Onlyoffice.getFileExtension(fileName)

			const formats = OCA.Onlyoffice.setting.formats

			const config = formats[extension]
			if (!config) {
				return
			}

			const editorUrl = OC.generateUrl('apps/' + OCA.Onlyoffice.AppName + '/s/' + encodeURIComponent($('#sharingToken').val()))

			if (_oc_appswebroots.richdocuments
                || (_oc_appswebroots.files_pdfviewer && extension === 'pdf')
                || (_oc_appswebroots.text && extension === 'txt')) {

				const button = document.createElement('a')
				button.href = editorUrl
				button.className = 'onlyoffice-public-open button'
				button.innerText = t(OCA.Onlyoffice.AppName, 'Open in ONLYOFFICE')

				if (!OCA.Onlyoffice.setting.sameTab) {
					button.target = '_blank'
				}

				$('#preview').prepend(button)
			} else {
				OCA.Onlyoffice.frameSelector = '#onlyofficeFrame'
				const $iframe = $('<iframe id="onlyofficeFrame" nonce="' + btoa(OC.requestToken) + '" scrolling="no" allowfullscreen src="' + editorUrl + '?inframe=true" />')
				$('#app-content').append($iframe)
				$('body').addClass('onlyoffice-inline')
			}
		} else {
			OC.Plugins.register('OCA.Files.NewFileMenu', OCA.Onlyoffice.NewFileMenu)

			OCA.Onlyoffice.registerNewFileMenu()

			OCA.Onlyoffice.registerAction()
		}
	}

	initPage()

})(OCA)

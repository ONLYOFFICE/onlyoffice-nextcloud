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

/* eslint-disable import/no-webpack-loader-syntax */
/* eslint-disable import/no-unresolved */

/* global _, jQuery */

import AppDarkSvg from '!!raw-loader!../img/app-dark.svg';

/**
 * @param {object} $ JQueryStatic object
 * @param {object} OC Nextcloud OCA object
 */
(function($, OC) {

	OCA.Onlyoffice = _.extend({
		AppName: 'onlyoffice',
	}, OCA.Onlyoffice)

	OCA.Onlyoffice.Permissions = {
		None: 0,
		Review: 1,
		Comment: 2,
		FillForms: 4,
		ModifyFilter: 8,
	}

	let tabcontext = null

	const advancedTab = new OCA.Files.Sidebar.Tab({
		id: 'onlyofficeSharingTabView',
		name: t(OCA.Onlyoffice.AppName, 'Advanced'),
		iconSvg: AppDarkSvg,

		mount(el, fileInfo, context) {
			if (!tabcontext) {
				tabcontext = advancedContext()
			}

			tabcontext.init(el, fileInfo)
		},

		update(fileInfo) {
			tabcontext.update(fileInfo)
		},

		destroy() {
			tabcontext.clear()
		},

		enabled(fileInfo) {
			let canDisplay = false

			if (!fileInfo.isDirectory()) {
				const ext = OCA.Onlyoffice.getFileExtension(fileInfo.name)
				const format = OCA.Onlyoffice.setting.formats[ext]
				if (format && (format.review
					|| format.comment
					|| format.fillForms
					|| format.modifyFilter)) {
					canDisplay = true

					if (($('#sharing').hasClass('active') || $('#tab-button-sharing').hasClass('active'))
						&& tabcontext.fileInfo
						&& tabcontext.fileInfo.id === fileInfo.id) {
						this.update(fileInfo)
					}
				}
			}

			return canDisplay
		},
	})

	const advancedContext = function() {
		let $el = null

		let format = null
		let fileInfo = null
		let collection = null
		let customEvents = null
		let permissionsMenu = null
		let templateItem = null

		const getContainer = function() {
			return $el.find('.onlyoffice-share-container')
		}

		const getTemplate = function(callback) {
			if ($el.find('.onlyoffice-share-container').length === 0) {
				$('<ul>', { class: 'onlyoffice-share-container' }).appendTo($el)
				$('<div>').html(t(OCA.Onlyoffice.AppName, 'Provide advanced document permissions using ONLYOFFICE Docs')).prependTo($el)
			}

			if (templateItem) {
				callback()
				return
			}

			$.get(OC.filePath(OCA.Onlyoffice.AppName, 'templates', 'share.html'),
				function(tmpl) {
					templateItem = $(tmpl)

					callback()
				})
		}

		const render = function() {
			getTemplate(() => {
				collection.forEach(extra => {
					const itemNode = templateItem.clone()
					const descNode = itemNode.find('span')
					const avatar = itemNode.find('img')
					const actionButton = itemNode.find('#onlyoffice-share-action')

					let avatarSrc = '/index.php/avatar/' + extra.shareWith + '/32?v=0'
					let label = extra.shareWithName
					if (extra.type === OC.Share.SHARE_TYPE_GROUP
						|| extra.type === OC.Share.SHARE_TYPE_ROOM) {
						avatarSrc = '/index.php/avatar/guest/' + extra.shareWith + '/32?v=0'
						label = extra.shareWith + ' (' + t(OCA.Onlyoffice.AppName, 'group') + ')'
					}

					if (extra.type === OC.Share.SHARE_TYPE_ROOM) {
						label = extra.shareWith + ' (' + t(OCA.Onlyoffice.AppName, 'conversation') + ')'
					}

					if (extra.type === OC.Share.SHARE_TYPE_LINK) {
						label = t(OCA.Onlyoffice.AppName, 'Share link')

						const avatarWrapper = itemNode.find('.avatardiv')
						avatarWrapper.addClass('onlyoffice-share-link-avatar')

						avatarSrc = '/core/img/actions/public.svg'
					}

					actionButton.click(onClickPermissionMenu)

					avatar[0].src = avatarSrc
					descNode[0].innerText = label

					itemNode[0].id = extra.share_id

					getContainer().append(itemNode)
				})
			})
		}

		const onClickSetPermissions = function(e) {
			const permissionValues = permissionsMenu.getValues()
			const shareId = permissionsMenu.getTargetId()
			const fileId = fileInfo.id
			const extra = collection.find(item => item.share_id === shareId)

			let permissions = OCA.Onlyoffice.Permissions.None
			if (permissionValues[OCA.Onlyoffice.Permissions.Review]) {
				permissions |= OCA.Onlyoffice.Permissions.Review
			}
			if (permissionValues[OCA.Onlyoffice.Permissions.Comment]
				&& (permissions & OCA.Onlyoffice.Permissions.Review) !== OCA.Onlyoffice.Permissions.Review
				&& (permissions & OCA.Onlyoffice.Permissions.ModifyFilter) !== OCA.Onlyoffice.Permissions.ModifyFilter) {
				permissions |= OCA.Onlyoffice.Permissions.Comment
			}
			if (permissionValues[OCA.Onlyoffice.Permissions.FillForms]
				&& (permissions & OCA.Onlyoffice.Permissions.Review) !== OCA.Onlyoffice.Permissions.Review) {
				permissions |= OCA.Onlyoffice.Permissions.FillForms
			}
			if (permissionValues[OCA.Onlyoffice.Permissions.ModifyFilter]
				&& (permissions & OCA.Onlyoffice.Permissions.Comment) !== OCA.Onlyoffice.Permissions.Comment) {
				permissions |= OCA.Onlyoffice.Permissions.ModifyFilter
			}

			permissionsMenu.block(true)
			OCA.Onlyoffice.SetShares(extra.id, shareId, fileId, permissions, (extra) => {
				collection.forEach(item => {
					if (item.share_id === extra.share_id) {
						item.id = extra.id
						item.permissions = extra.permissions
						item.available = extra.available
					}
				})

				const attributes = getPermissionAttributes(extra)

				permissionsMenu.refresh(attributes)
				permissionsMenu.block(false)
			})
		}

		const listenOuterClicks = function(event) {
			if (event.target.id === 'onlyoffice-share-action') {
				return
			}
			const target = document.querySelector('#onlyoffice-share-popup-menu')
			if (target) {
				const eventPath = event.composedPath().includes(target)
				if (!eventPath && typeof (permissionsMenu) !== 'undefined' && permissionsMenu.isOpen()) {
					permissionsMenu.close()
				}
			}
		}

		const onClickPermissionMenu = function(e) {
			if (!permissionsMenu) {
				permissionsMenu = getPermissionMenu()
			}
			window.addEventListener('click', listenOuterClicks)

			const shareNode = $(e.target).closest('.onlyoffice-share-item')[0]
			const shareId = shareNode.id

			if (permissionsMenu.isOpen()) {
				const previousId = permissionsMenu.getTargetId()
				permissionsMenu.close()

				if (previousId === shareId) return
			}

			const extra = collection.find(item => item.share_id === shareId)

			const attributes = getPermissionAttributes(extra)

			permissionsMenu.open(extra.share_id, attributes, $(e.target).position())
		}

		const getCustomEvents = function() {
			let init = false

			return {
				on() {
					if (!init) {
						$('#content').on('click', function(e) {
							const target = $(e.target)[0]
							if (!permissionsMenu
								|| !permissionsMenu.isOpen()
								|| target.id === 'onlyoffice-share-action'
								|| target.className === 'onlyoffice-share-label'
								|| target.closest('.onlyoffice-share-action')) {
								return
							}

							permissionsMenu.close()
						})

						init = true
					}
				},
			}
		}

		const getPermissionAttributes = function(extra) {
			const attributes = []

			if (format.review
				&& (OCA.Onlyoffice.Permissions.Review & extra.available) === OCA.Onlyoffice.Permissions.Review) {
				const review = (OCA.Onlyoffice.Permissions.Review & extra.permissions) === OCA.Onlyoffice.Permissions.Review
				attributes.push({
					checked: review,
					extra: OCA.Onlyoffice.Permissions.Review,
					label: t(OCA.Onlyoffice.AppName, 'Review only'),
				})
			}
			if (format.comment
				&& (OCA.Onlyoffice.Permissions.Comment & extra.available) === OCA.Onlyoffice.Permissions.Comment) {
				const comment = (OCA.Onlyoffice.Permissions.Comment & extra.permissions) === OCA.Onlyoffice.Permissions.Comment
				attributes.push({
					checked: comment,
					extra: OCA.Onlyoffice.Permissions.Comment,
					label: t(OCA.Onlyoffice.AppName, 'Comment only'),
				})
			}
			if (format.fillForms
				&& (OCA.Onlyoffice.Permissions.FillForms & extra.available) === OCA.Onlyoffice.Permissions.FillForms) {
				const fillForms = (OCA.Onlyoffice.Permissions.FillForms & extra.permissions) === OCA.Onlyoffice.Permissions.FillForms
				attributes.push({
					checked: fillForms,
					extra: OCA.Onlyoffice.Permissions.FillForms,
					label: t(OCA.Onlyoffice.AppName, 'Form filling'),
				})
			}

			if (format.modifyFilter
				&& (OCA.Onlyoffice.Permissions.ModifyFilter & extra.available) === OCA.Onlyoffice.Permissions.ModifyFilter) {
				const modifyFilter = (OCA.Onlyoffice.Permissions.ModifyFilter & extra.permissions) === OCA.Onlyoffice.Permissions.ModifyFilter
				attributes.push({
					checked: modifyFilter,
					extra: OCA.Onlyoffice.Permissions.ModifyFilter,
					label: t(OCA.Onlyoffice.AppName, 'Global filter'),
				})
			}

			return attributes
		}

		const getPermissionMenu = function() {
			const popup = $('<div>', {
				class: 'popovermenu onlyoffice-share-popup',
				id: 'onlyoffice-share-popup-menu',
			}).append($('<ul>'), {
				id: -1,
			})

			const appendItem = function(checked, extra, name) {
				const item = $('<li>').append($('<span>', {
					class: 'onlyoffice-share-action',
				}).append($('<input>', {
					id: 'extra-' + extra,
					type: 'checkbox',
					class: 'checkbox action-checkbox__checkbox focusable',
					checked,
				})).append($('<label>', {
					for: 'extra-' + extra,
					text: name,
					class: 'onlyoffice-share-label',
				})))

				const input = item.find('input')
				input.click(onClickSetPermissions)

				popup.find('ul').append(item)
			}

			const removeItems = function() {
				const items = popup.find('li')
				if (items) {
					items.remove()
				}
			}

			const setTargetId = function(id) {
				popup.find('ul').attr('id', id)
			}

			$el.append(popup)

			return {
				isOpen() {
					return popup.is(':visible')
				},

				open(id, attributes, position) {
					removeItems()

					if (position) {
						popup.css({ top: position.top })
					}

					attributes.forEach(attr => {
						appendItem(attr.checked, attr.extra, attr.label)
					})

					setTargetId(id)
					popup.show()
				},

				close() {
					removeItems()

					setTargetId(-1)
					popup.hide()
					window.removeEventListener('click', listenOuterClicks)
				},

				refresh(attributes) {
					removeItems()

					attributes.forEach(attr => {
						appendItem(attr.checked, attr.extra, attr.label)
					})
				},

				block(value) {
					popup.find('input').prop('disabled', value)
				},

				getValues() {
					const values = []

					const items = popup.find('input')
					for (let i = 0; i < items.length; i++) {
						const extra = items[i].id.split('extra-')[1]
						values[extra] = items[i].checked
					}

					return values
				},

				getTargetId() {
					return popup.find('ul').attr('id')
				},
			}
		}

		return {
			get fileInfo() {
				return fileInfo
			},

			init(_el, _fileInfo) {
				$el = $(_el)

				getTemplate(() => {
					this.update(_fileInfo)
				})
			},

			update(_fileInfo) {
				if (customEvents === null) {
					customEvents = getCustomEvents()
					customEvents.on()
				}

				getContainer().children().remove()

				fileInfo = _fileInfo

				const ext = OCA.Onlyoffice.getFileExtension(fileInfo.name)
				format = OCA.Onlyoffice.setting.formats[ext]

				OCA.Onlyoffice.GetShares(fileInfo.id, (shares) => {
					collection = shares

					render()
				})
			},

			clear() {
				$el = null
				format = null
				fileInfo = null
				collection = null
				permissionsMenu = null
			},
		}
	}

	OCA.Onlyoffice.GetShares = function(fileId, callback) {
		$.ajax({
			url: OC.linkToOCS('apps/' + OCA.Onlyoffice.AppName + '/api/v1/shares', 2) + fileId + '?format=json',
			success: function onSuccess(response) {
				callback(response.ocs.data)
			},
		})
	}

	OCA.Onlyoffice.SetShares = function(id, shareId, fileId, permissions, callback) {
		const data = {
			extraId: id,
			shareId,
			fileId,
			permissions,
		}

		$.ajax({
			method: 'PUT',
			url: OC.linkToOCS('apps/' + OCA.Onlyoffice.AppName + '/api/v1', 2) + 'shares?format=json',
			data,
			success: function onSuccess(response) {
				callback(response.ocs.data)
			},
		})
	}

	if (OCA.Files.Sidebar && OCA.Files.Sidebar.registerTab) {
		OCA.Files.Sidebar.registerTab(advancedTab)
	}

})(jQuery, OC)

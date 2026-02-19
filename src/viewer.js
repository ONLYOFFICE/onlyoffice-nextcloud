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

/* global $ */

/**
 * @param {object} OCA Nextcloud OCA object
 */
(function(OCA) {
	OCA.Onlyoffice = Object.assign({
		AppName: 'onlyoffice',
		frameSelector: null,
		setting: {},
	}, OCA.Onlyoffice || {})

	OCA.Onlyoffice.setting = OCP.InitialState.loadState(OCA.Onlyoffice.AppName, 'settings')

	const OnlyofficeViewerVue = {
		name: 'OnlyofficeViewerVue',
		render(createElement) {
			const self = this

			return createElement('iframe', {
				attrs: {
					id: 'onlyofficeViewerFrame',
					scrolling: 'no',
					src: self.url + '&inframe=true&inviewer=true',
				},
				on: {
					load() {
						self.doneLoading()
					},
				},
			})
		},
		props: {
			filename: {
				type: String,
				default: null,
			},
			fileid: {
				type: Number,
				default: null,
			},
		},
		data() {
			return {
				url: OC.generateUrl('/apps/' + OCA.Onlyoffice.AppName + '/{fileId}?filePath={filePath}',
					{
						fileId: this.fileid,
						filePath: this.filename,
					}),
			}
		},
	}

	let viewerHandlerRegistered = false
	const registerViewerHandler = function() {
		if (viewerHandlerRegistered) {
			return true
		}
		if (!(OCA.Viewer && typeof OCA.Viewer.registerHandler === 'function')) {
			return false
		}

		OCA.Onlyoffice.frameSelector = '#onlyofficeViewerFrame'

		const mimes = $.map(OCA.Onlyoffice.setting.formats, function(format) {
			if (format.def) {
				return format.mime
			}
		})
			.flat()
			.filter((mime) => typeof mime === 'string' && mime.length > 0)

		OCA.Viewer.registerHandler({
			id: OCA.Onlyoffice.AppName,
			group: null,
			mimes,
			component: OnlyofficeViewerVue,
		})

		viewerHandlerRegistered = true
		return true
	}

	if (!registerViewerHandler()) {
		const waitRegister = (attempt = 0) => {
			if (registerViewerHandler() || attempt >= 25) {
				return
			}

			setTimeout(() => waitRegister(attempt + 1), 200)
		}

		waitRegister()
	}

})(OCA)

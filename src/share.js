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

import { createApp } from 'vue'
import { t } from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'
import AppDarkSvg from '../img/app-dark.svg?raw'
import ShareTab from './views/share/ShareTab.vue'
import { getFileExtension } from './utils/files.ts'

const formats = loadState('onlyoffice', 'settings', { formats: {} }).formats ?? {}

let appInstance = null
let componentVm = null

const advancedTab = new OCA.Files.Sidebar.Tab({
	id: 'onlyofficeSharingTabView',
	name: t('onlyoffice', 'Advanced'),
	iconSvg: AppDarkSvg,

	mount(el, fileInfo) {
		appInstance = createApp(ShareTab)
		componentVm = appInstance.mount(el)
		componentVm.update(fileInfo)
	},

	update(fileInfo) {
		componentVm?.update(fileInfo)
	},

	destroy() {
		appInstance?.unmount()
		appInstance = null
		componentVm = null
	},

	enabled(fileInfo) {
		if (fileInfo.isDirectory()) return false
		const ext = getFileExtension(fileInfo.name)
		const format = formats[ext]
		if (!(format && (format.review || format.comment || format.fillForms || format.modifyFilter))) return false
		const sharingTabActive = document.querySelector('#sharing.active, #tab-button-sharing.active')
		if (sharingTabActive && componentVm) {
			componentVm.update(fileInfo)
		}
		return true
	},
})

if (OCA.Files?.Sidebar?.registerTab) {
	OCA.Files.Sidebar.registerTab(advancedTab)
}

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

import { FileType, registerSidebarTab } from '@nextcloud/files'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { defineCustomElement } from 'vue'
import ShareTab from './views/share/ShareTab.vue'
import AppDarkSvg from '../img/app-dark.svg?raw'
import { getFileExtension } from './utils/files.ts'

const formats = loadState('onlyoffice', 'settings', { formats: {} }).formats ?? {}
const tagName = 'onlyoffice-files-advanced-sidebar-tab'

registerSidebarTab({
	id: 'onlyofficeSharingTabView',
	displayName: t(OCA.Onlyoffice.AppName, 'Advanced'),
	iconSvgInline: AppDarkSvg,
	tagName,
	order: 0,

	enabled({ node }) {
		if (node.type === FileType.File) {
			const ext = getFileExtension(node.basename)
			const format = formats[ext]
			if (format && (format.review
				|| format.comment
				|| format.fillForms
				|| format.modifyFilter)) {
				return true
			}
		}
		return false
	},

	async onInit() {
		window.customElements.define(tagName, defineCustomElement(ShareTab, {
			shadowRoot: false,
		}))
	},
})

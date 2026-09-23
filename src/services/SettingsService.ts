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

import type { AddressSettingsResponse } from '../types.ts'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export interface AddressSettingsData {
	documentserver: string
	documentserverInternal: string
	storageUrl: string
	verifyPeerOff: boolean
	secret: string
	jwtHeader: string
	demo: boolean
}

export interface CommonSettingsData {
	defFormats: Record<string, boolean>
	editFormats: Record<string, boolean>
	restrictExternalStorage: boolean
	sameTab: boolean
	enableSharing: boolean
	preview: boolean
	advanced: boolean
	cronChecker: boolean
	emailNotifications: boolean
	versionHistory: boolean
	limitGroups: string[]
	chat: boolean
	compactHeader: boolean
	feedback: boolean
	forcesave: boolean
	liveViewOnShare: boolean
	help: boolean
	reviewDisplay: string
	theme: string
	unknownAuthor: string
}

export interface SecuritySettingsData {
	watermarks: Record<string, unknown>
	plugins: boolean
	macros: boolean
	protection: string
}

/**
 *
 * @param data
 */
export async function saveAddressSettings(data: AddressSettingsData): Promise<AddressSettingsResponse> {
	const response = await axios.put<AddressSettingsResponse>(
		generateUrl('apps/onlyoffice/ajax/settings/address'),
		data,
	)
	return response.data
}

/**
 *
 * @param data
 */
export async function saveCommonSettings(data: CommonSettingsData): Promise<void> {
	await axios.put(generateUrl('apps/onlyoffice/ajax/settings/common'), data)
}

/**
 *
 * @param data
 */
export async function saveSecuritySettings(data: SecuritySettingsData): Promise<void> {
	await axios.put(generateUrl('apps/onlyoffice/ajax/settings/security'), data)
}

/**
 *
 */
export async function clearHistory(): Promise<void> {
	await axios.delete(generateUrl('apps/onlyoffice/ajax/settings/history'))
}

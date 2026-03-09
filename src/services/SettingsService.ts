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

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import type { AddressSettingsResponse } from '../types'

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

export const saveAddressSettings = async (data: AddressSettingsData): Promise<AddressSettingsResponse> => {
	const response = await axios.put<AddressSettingsResponse>(
		generateUrl('apps/onlyoffice/ajax/settings/address'),
		data,
	)
	return response.data
}

export const saveCommonSettings = async (data: CommonSettingsData): Promise<void> => {
	await axios.put(generateUrl('apps/onlyoffice/ajax/settings/common'), data)
}

export const saveSecuritySettings = async (data: SecuritySettingsData): Promise<void> => {
	await axios.put(generateUrl('apps/onlyoffice/ajax/settings/security'), data)
}

export const clearHistory = async (): Promise<void> => {
	await axios.delete(generateUrl('apps/onlyoffice/ajax/settings/history'))
}

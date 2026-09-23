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

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const TAG_FAVORITE = '_$!<Favorite>!$_'

export interface GetUsersParams {
	fileId: number
	operationType: string | null
	from?: number
	count?: number
	search?: string
}

export interface SendMentionData {
	fileId: number
	anchor: string
	comment: string
	emails: string[]
}

/**
 *
 * @param path
 */
function encodePath(path: string): string {
	return path.split('/').map(encodeURIComponent).join('/')
}

/**
 *
 * @param url
 */
export async function getConfig(url: string): Promise<unknown> {
	const response = await axios.get<unknown>(url)
	return response.data
}

/**
 *
 * @param fileId
 */
export async function getHistory(fileId: number): Promise<unknown> {
	const response = await axios.get<unknown>(
		generateUrl('apps/onlyoffice/ajax/history', { fileId }),
		{ params: { fileId } },
	)
	return response.data
}

/**
 *
 * @param fileId
 * @param version
 */
export async function getVersionData(fileId: number, version: number): Promise<unknown> {
	const response = await axios.get<unknown>(
		generateUrl('apps/onlyoffice/ajax/version'),
		{ params: { fileId, version } },
	)
	return response.data
}

/**
 *
 * @param fileId
 * @param version
 */
export async function restoreVersion(fileId: number, version: number): Promise<unknown> {
	const response = await axios.put<unknown>(
		generateUrl('apps/onlyoffice/ajax/restore'),
		{ fileId, version },
	)
	return response.data
}

/**
 *
 * @param saveData
 */
export async function saveAs(saveData: Record<string, unknown>): Promise<unknown> {
	const response = await axios.post<unknown>(
		generateUrl('apps/onlyoffice/ajax/save'),
		saveData,
	)
	return response.data
}

/**
 *
 * @param filePath
 */
export async function getFileUrl(filePath: string): Promise<unknown> {
	const response = await axios.get<unknown>(
		generateUrl('apps/onlyoffice/ajax/url'),
		{ params: { filePath } },
	)
	return response.data
}

/**
 *
 * @param data
 */
export async function fetchReference(data: Record<string, unknown>): Promise<unknown> {
	const response = await axios.post<unknown>(
		generateUrl('apps/onlyoffice/ajax/reference'),
		data,
	)
	return response.data
}

/**
 * Fetches all sender email addresses for the currently logged-in user.
 *
 * @return A promise resolving to the list of email addresses from the server.
 */
export async function fetchEmails(): Promise<unknown> {
	const response = await axios.get<unknown[]>(generateUrl('apps/onlyoffice/ajax/emails'))
	return response.data
}

/**
 *
 * @param userIds
 */
export async function getUserInfo(userIds: unknown[]): Promise<unknown[]> {
	const response = await axios.get<unknown[]>(
		generateUrl('apps/onlyoffice/ajax/userInfo'),
		{ params: { userIds: JSON.stringify(userIds) } },
	)
	return response.data
}

/**
 *
 * @param params
 */
export async function getUsers(params: GetUsersParams): Promise<unknown[]> {
	const { fileId, operationType, ...rest } = params
	const response = await axios.get<unknown[]>(
		generateUrl('apps/onlyoffice/ajax/users'),
		{ params: { fileId, operationType, ...rest } },
	)
	return response.data
}

/**
 *
 * @param data
 */
export async function sendMention(data: SendMentionData): Promise<unknown> {
	const response = await axios.post<unknown>(
		generateUrl('apps/onlyoffice/ajax/mention'),
		data,
	)
	return response.data
}

/**
 *
 * @param filePath
 * @param favorite
 */
export async function setFavorite(filePath: string, favorite: boolean): Promise<void> {
	await axios.post(
		generateUrl(`apps/files/api/v1/files/${encodePath(filePath)}`),
		{ tags: favorite ? [TAG_FAVORITE] : [] },
	)
}

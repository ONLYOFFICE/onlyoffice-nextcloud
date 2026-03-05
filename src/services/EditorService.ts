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

export interface ReferenceData {
	referenceData?: unknown
	path?: string
	link?: string
}

const encodePath = (path: string): string =>
	path.split('/').map(encodeURIComponent).join('/')

export const getConfig = async (url: string): Promise<unknown> => {
	const response = await axios.get<unknown>(url)
	return response.data
}

export const getHistory = async (fileId: number): Promise<unknown> => {
	const response = await axios.get<unknown>(
		generateUrl('apps/onlyoffice/ajax/history', { fileId }),
		{ params: { fileId } },
	)
	return response.data
}

export const getVersionData = async (fileId: number, version: number): Promise<unknown> => {
	const response = await axios.get<unknown>(
		generateUrl('apps/onlyoffice/ajax/version'),
		{ params: { fileId, version } },
	)
	return response.data
}

export const restoreVersion = async (fileId: number, version: number): Promise<unknown> => {
	const response = await axios.put<unknown>(
		generateUrl('apps/onlyoffice/ajax/restore'),
		{ fileId, version },
	)
	return response.data
}

export const saveAs = async (saveData: Record<string, unknown>): Promise<unknown> => {
	const response = await axios.post<unknown>(
		generateUrl('apps/onlyoffice/ajax/save'),
		saveData,
	)
	return response.data
}

export const getFileUrl = async (filePath: string): Promise<unknown> => {
	const response = await axios.get<unknown>(
		generateUrl('apps/onlyoffice/ajax/url'),
		{ params: { filePath } },
	)
	return response.data
}

export const setReferenceSource = async (path: string): Promise<unknown> => {
	const response = await axios.post<unknown>(
		generateUrl('apps/onlyoffice/ajax/reference'),
		{ path },
	)
	return response.data
}

export const getReferenceData = async (data: ReferenceData): Promise<unknown> => {
	const response = await axios.post<unknown>(
		generateUrl('apps/onlyoffice/ajax/reference'),
		data,
	)
	return response.data
}

export const getUserInfo = async (userIds: unknown[]): Promise<unknown[]> => {
	const response = await axios.get<unknown[]>(
		generateUrl('apps/onlyoffice/ajax/userInfo'),
		{ params: { userIds: JSON.stringify(userIds) } },
	)
	return response.data
}

export const getUsers = async (params: GetUsersParams): Promise<unknown[]> => {
	const { fileId, operationType, ...rest } = params
	const response = await axios.get<unknown[]>(
		generateUrl('apps/onlyoffice/ajax/users'),
		{ params: { fileId, operationType, ...rest } },
	)
	return response.data
}

export const sendMention = async (data: SendMentionData): Promise<unknown> => {
	const response = await axios.post<unknown>(
		generateUrl('apps/onlyoffice/ajax/mention'),
		data,
	)
	return response.data
}

export const setFavorite = async (filePath: string, favorite: boolean): Promise<void> => {
	await axios.post(
		generateUrl(`apps/files/api/v1/files/${encodePath(filePath)}`),
		{ tags: favorite ? [TAG_FAVORITE] : [] },
	)
}

/*
 * Copyright (C) Ascensio System SIA 2026
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */
import { createAPIRequest, ocsHeadersWithAuth } from './ocs'
import { User } from './users'

export async function getDirectEditingUrl(
	filePath: string,
	user: User,
): Promise<string> {
	const request = await createAPIRequest()
	const response = await request.post('/ocs/v2.php/apps/files/api/v1/directEditing/open', {
		headers: {
			...ocsHeadersWithAuth(user.username, user.password),
			'Content-Type': 'application/x-www-form-urlencoded',
		},
		form: {
			path: filePath,
			editorId: 'onlyoffice',
		},
		failOnStatusCode: true,
	})
	const { ocs: { data } } = await response.json()
	await request.dispose()
	return data.url
}

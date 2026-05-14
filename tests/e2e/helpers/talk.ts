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

export async function createRoom(
	name: string,
	user: User,
): Promise<string> {
	const request = await createAPIRequest()
	const response = await request.post('/ocs/v2.php/apps/spreed/api/v4/room', {
		form: { roomType: 3, roomName: name },
		headers: ocsHeadersWithAuth(user.username, user.password),
		failOnStatusCode: true,
	})
	const json = await response.json()
	await request.dispose()

	return json.ocs.data.token
}

export async function shareFileToRoom(
	token: string,
	fileId: number,
	user: User,
): Promise<void> {
	const request = await createAPIRequest()
	await request.post(`/ocs/v2.php/apps/spreed/api/v1/recording/${token}/share-chat`, {
		form: {
			fileId: String(fileId),
			timestamp: Date.now(),
		},
		headers: ocsHeadersWithAuth(user.username, user.password),
		failOnStatusCode: true,
	})
	await request.dispose()
}

export async function deleteRoom(
	token: string,
	user: User,
): Promise<void> {
	const request = await createAPIRequest()
	await request.delete(`/ocs/v2.php/apps/spreed/api/v4/room/${token}`, {
		headers: ocsHeadersWithAuth(user.username, user.password),
		failOnStatusCode: true,
	})
	await request.dispose()
}

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
import { admin } from './users'

export async function createGroup(groupName: string): Promise<void> {
	const request = await createAPIRequest()
	await request.post('/ocs/v2.php/cloud/groups', {
		headers: {
			...ocsHeadersWithAuth(admin.username, admin.password),
			'Content-Type': 'application/x-www-form-urlencoded',
		},
		form: { groupid: groupName },
		failOnStatusCode: true,
	})
	await request.dispose()
}

export async function deleteGroup(groupName: string): Promise<void> {
	const request = await createAPIRequest()
	await request.delete(`/ocs/v2.php/cloud/groups/${encodeURIComponent(groupName)}`, {
		headers: ocsHeadersWithAuth(admin.username, admin.password),
		failOnStatusCode: true,
	})
	await request.dispose()
}

export async function addUserToGroup(username: string, groupName: string): Promise<void> {
	const request = await createAPIRequest()
	await request.post(`/ocs/v2.php/cloud/users/${encodeURIComponent(username)}/groups`, {
		headers: {
			...ocsHeadersWithAuth(admin.username, admin.password),
			'Content-Type': 'application/x-www-form-urlencoded',
		},
		form: { groupid: groupName },
		failOnStatusCode: true,
	})
	await request.dispose()
}

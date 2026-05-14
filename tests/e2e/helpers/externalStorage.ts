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

export async function createGlobalStorage(config: {
	mountPoint: string
	backend: string
	authMechanism: string
	backendOptions: Record<string, string>
	mountOptions?: Record<string, unknown>
}): Promise<number> {
	const request = await createAPIRequest()
	const response = await request.post('/index.php/apps/files_external/globalstorages', {
		headers: {
			...ocsHeadersWithAuth(admin.username, admin.password),
			'Content-Type': 'application/json',
		},
		data: config,
		failOnStatusCode: true,
	})
	const body = await response.json()
	await request.dispose()
	return body.id
}

export async function deleteGlobalStorage(storageId: number): Promise<void> {
	const request = await createAPIRequest()
	await request.delete(`/index.php/apps/files_external/globalstorages/${storageId}`, {
		headers: ocsHeadersWithAuth(admin.username, admin.password),
		failOnStatusCode: true,
	})
	await request.dispose()
}

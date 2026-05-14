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
import { createAPIRequest, ocsHeadersWithAuth } from './ocs';
import { randomName } from './utils';

export interface User {
	username: string
	password: string
}

export const admin: User = {
	username: process.env.NEXTCLOUD_ADMIN_USER ?? 'admin',
	password: process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin',
}

export async function createUser(user: User): Promise<void> {
	const request = await createAPIRequest()
	const response = await request.post('/ocs/v2.php/cloud/users', {
		headers: {
			...ocsHeadersWithAuth(admin.username, admin.password),
			'Content-Type': 'application/x-www-form-urlencoded',
		},
		form: { userid: user.username, password: user.password },
	});
	if (!response.ok()) {
		const body = await response.json();
		const status = response.status()
		await request.dispose()

		if (status === 400 && body?.ocs?.meta?.statuscode === 102) return;
		throw new Error(`Failed to create user ${user.username}: ${status}`);
	}
}

export async function deleteUser(user: User): Promise<void> {
	const request = await createAPIRequest()
	await request.delete(`/ocs/v2.php/cloud/users/${encodeURIComponent(user.username)}`, {
		headers: ocsHeadersWithAuth(admin.username, admin.password),
		failOnStatusCode: true,
	});
	await request.dispose()
}

export async function createRandomUser(): Promise<User> {
	const user: User = {
		username: `user-${randomName()}`,
		password: 'password',
	}
	await createUser(user)
	return user
}

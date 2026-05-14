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
import { User } from './users';

const SHARE_TYPE_USER = 0;
const SHARE_TYPE_PUBLIC_LINK = 3;
const PERMISSION_READ = 1;
const PERMISSION_READ_WRITE = 7;
const PERMISSION_ALL_NO_RESHARE = 15; // read + update + create + delete, no share(16)

export type ShareInfo = {
	id: number;
	token: string;
}

export async function createPublicShare(
	path: string,
	user: User,
): Promise<ShareInfo> {
	const request = await createAPIRequest()
	const response = await request.post('/ocs/v2.php/apps/files_sharing/api/v1/shares', {
		headers: {
			...ocsHeadersWithAuth(user.username, user.password),
			'Content-Type': 'application/x-www-form-urlencoded',
		},
		form: {
			path,
			shareType: SHARE_TYPE_PUBLIC_LINK,
			permissions: PERMISSION_READ,
		},
		failOnStatusCode: true,
	});

	const { ocs: { data } } = await response.json();
	await request.dispose()

	return { id: data.id, token: data.token };
}

export async function createPublicEditShare(
	path: string,
	user: User,
): Promise<ShareInfo> {
	const request = await createAPIRequest()
	const response = await request.post('/ocs/v2.php/apps/files_sharing/api/v1/shares', {
		headers: {
			...ocsHeadersWithAuth(user.username, user.password),
			'Content-Type': 'application/x-www-form-urlencoded',
		},
		form: {
			path,
			shareType: SHARE_TYPE_PUBLIC_LINK,
			permissions: PERMISSION_READ_WRITE,
		},
		failOnStatusCode: true,
	});

	const { ocs: { data } } = await response.json();
	await request.dispose()

	return { id: data.id, token: data.token };
}

export async function createUserShare(
	path: string,
	shareWith: string,
	user: User
): Promise<ShareInfo> {
	const request = await createAPIRequest()
	const response = await request.post('/ocs/v2.php/apps/files_sharing/api/v1/shares', {
		headers: {
			...ocsHeadersWithAuth(user.username, user.password),
			'Content-Type': 'application/x-www-form-urlencoded',
		},
		form: {
			path,
			shareType: SHARE_TYPE_USER,
			shareWith,
			permissions: PERMISSION_ALL_NO_RESHARE,
		},
		failOnStatusCode: true,
	});

	const { ocs: { data } } = await response.json();
	await request.dispose()

	return { id: data.id, token: data.token };
}

export async function deleteShare(
	shareId: number,
	user: User,
): Promise<void> {
	const request = await createAPIRequest()
	await request.delete(`/ocs/v2.php/apps/files_sharing/api/v1/shares/${shareId}`, {
		headers: ocsHeadersWithAuth(user.username, user.password),
		failOnStatusCode: true,
	});
	await request.dispose()
}

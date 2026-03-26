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

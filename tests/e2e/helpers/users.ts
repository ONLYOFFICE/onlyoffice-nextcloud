import { createAPIRequest, ocsHeadersWithAuth } from './ocs';

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
		username: `user-${crypto.randomUUID().slice(0, 8)}`,
		password: 'password',
	}
	await createUser(user)
	return user
}

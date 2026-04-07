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

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

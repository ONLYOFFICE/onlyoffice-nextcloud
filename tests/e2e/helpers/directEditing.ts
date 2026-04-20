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

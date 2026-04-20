import { createAPIRequest, ocsHeadersWithAuth } from './ocs'
import { admin } from './users'

const SETTINGS_BASE = '/index.php/apps/onlyoffice/ajax/settings'

interface CommonSettings {
	defFormats: object
	editFormats: object
	restrictExternalStorage: boolean
	sameTab: boolean
	enableSharing: boolean
	preview: boolean
	advanced: boolean
	cronChecker: boolean
	emailNotifications: boolean
	versionHistory: boolean
	chat: boolean
	compactHeader: boolean
	feedback: boolean
	forcesave: boolean
	liveViewOnShare: boolean
	help: boolean
	reviewDisplay: string
	theme: string
	unknownAuthor: string
	limitGroups: string[]
}

const commonDefaults: CommonSettings = {
	defFormats: {},
	editFormats: {},
	restrictExternalStorage: false,
	sameTab: true,
	enableSharing: false,
	preview: true,
	advanced: false,
	cronChecker: true,
	emailNotifications: true,
	versionHistory: true,
	chat: true,
	compactHeader: true,
	feedback: true,
	forcesave: false,
	liveViewOnShare: false,
	help: true,
	reviewDisplay: 'original',
	theme: 'theme-system',
	unknownAuthor: '',
	limitGroups: [],
}

export async function saveAddressSettings(settings: object): Promise<void> {
	throw new Error('Not implemented')
}

export async function saveCommonSettings(settings: Partial<CommonSettings>): Promise<void> {
	const request = await createAPIRequest()
	await request.put(`${SETTINGS_BASE}/common`, {
		headers: {
			...ocsHeadersWithAuth(admin.username, admin.password),
			'Content-Type': 'application/json',
		},
		data: JSON.stringify({ ...commonDefaults, ...settings }),
		failOnStatusCode: true,
	})
	await request.dispose()
}

export async function saveSecuritySettings(settings: object): Promise<void> {
	throw new Error('Not implemented')
}

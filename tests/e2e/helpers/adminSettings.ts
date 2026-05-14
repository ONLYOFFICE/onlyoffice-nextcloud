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

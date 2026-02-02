/**
 *
 * (c) Copyright Ascensio System SIA 2026
 *
 * This program is a free software product.
 * You can redistribute it and/or modify it under the terms of the GNU Affero General Public License
 * (AGPL) version 3 as published by the Free Software Foundation.
 * In accordance with Section 7(a) of the GNU AGPL its Section 15 shall be amended to the effect
 * that Ascensio System SIA expressly excludes the warranty of non-infringement of any third-party rights.
 *
 * This program is distributed WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * For details, see the GNU AGPL at: http://www.gnu.org/licenses/agpl-3.0.html
 *
 * You can contact Ascensio System SIA at 20A-12 Ernesta Birznieka-Upisha street, Riga, Latvia, EU, LV-1050.
 *
 * The interactive user interfaces in modified source and object code versions of the Program
 * must display Appropriate Legal Notices, as required under Section 5 of the GNU AGPL version 3.
 *
 * Pursuant to Section 7(b) of the License you must retain the original Product logo when distributing the program.
 * Pursuant to Section 7(e) we decline to grant you any rights under trademark law for use of our trademarks.
 *
 * All the Product's GUI elements, including illustrations and icon sets, as well as technical
 * writing content are licensed under the terms of the Creative Commons Attribution-ShareAlike 4.0 International.
 * See the License terms at http://creativecommons.org/licenses/by-sa/4.0/legalcode
 *
 */

const SystemTagsService = {
	_tags: [],
	_fetched: false,

	async fetch() {
		if (this._fetched) {
			return this._tags
		}

		const url = OC.linkToRemote('dav') + '/systemtags'
		const response = await fetch(url, {
			method: 'PROPFIND',
			headers: {
				'Content-Type': 'application/xml',
				'X-Requested-With': 'XMLHttpRequest',
				requesttoken: OC.requestToken,
			},
			body: `<?xml version="1.0"?>
				<d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns" xmlns:nc="http://nextcloud.org/ns">
					<d:prop>
						<oc:id />
						<oc:display-name />
						<oc:user-visible />
						<oc:user-assignable />
						<oc:can-assign />
					</d:prop>
				</d:propfind>`,
		})

		const xml = await response.text()
		this._tags = this._parseTags(xml)
		this._fetched = true
		return this._tags
	},

	filterByName(term) {
		const lowerTerm = term.toLowerCase()
		return this._tags.filter(tag =>
			tag.name.toLowerCase().startsWith(lowerTerm),
		)
	},

	get(id) {
		return this._tags.find(tag => String(tag.id) === String(id))
	},

	getDescriptiveTag(tag) {
		const span = document.createElement('span')
		span.textContent = tag.name

		let scope = null
		if (!tag.userAssignable) {
			scope = t('core', 'Restricted')
		}
		if (!tag.userVisible) {
			scope = t('core', 'Invisible')
		}

		if (scope) {
			const em = document.createElement('em')
			em.textContent = ` (${scope})`
			span.appendChild(em)
		}
		return span
	},

	_parseTags(xml) {
		const parser = new DOMParser()
		const doc = parser.parseFromString(xml, 'application/xml')
		const responses = doc.querySelectorAll('response')

		return Array.from(responses)
			.map(resp => {
				const id = resp.querySelector('id')?.textContent
				if (!id) {
					return null
				}

				return {
					id,
					name: resp.querySelector('display-name')?.textContent || '',
					userVisible: resp.querySelector('user-visible')?.textContent === 'true',
					userAssignable: resp.querySelector('user-assignable')?.textContent === 'true',
					canAssign: resp.querySelector('can-assign')?.textContent === 'true',
				}
			})
			.filter(Boolean)
	},
}

export default SystemTagsService

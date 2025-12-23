/**
 *
 * (c) Copyright Ascensio System SIA 2025
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

/* global _, jQuery */

/**
 * @param {object} $ JQueryStatic object
 * @param {object} OC Nextcloud OCA object
 */
(function($, OC) {

	$(document).ready(function() {
		OCA.Onlyoffice = _.extend({}, OCA.Onlyoffice)
		if (!OCA.Onlyoffice.AppName) {
			OCA.Onlyoffice = {
				AppName: 'onlyoffice',
			}
		}

		const advToogle = function() {
			$('#onlyofficeSecretPanel').toggleClass('onlyoffice-hide')
			$('#onlyofficeAdv .icon').toggleClass('icon-triangle-s icon-triangle-n')
		}

		if ($('#onlyofficeInternalUrl').val().length
			|| $('#onlyofficeStorageUrl').val().length
			|| $('#onlyofficeJwtHeader').val().length) {
			advToogle()
		}

		$('#onlyofficeAdv').click(advToogle)

		$('#onlyofficeGroups').prop('checked', $('#onlyofficeLimitGroups').val() !== '')

		const groupListToggle = function() {
			if ($('#onlyofficeGroups').prop('checked')) {
				OC.Settings.setupGroupsSelect($('#onlyofficeLimitGroups'))
			} else {
				$('#onlyofficeLimitGroups').select2('destroy')
			}
		}

		$('#onlyofficeGroups').click(groupListToggle)
		groupListToggle()

		const demoToggle = function() {
			$('#onlyofficeAddrSettings input:not(#onlyofficeStorageUrl)').prop('disabled', $('#onlyofficeDemo').prop('checked'))
		}

		$('#onlyofficeDemo').click(demoToggle)
		demoToggle()

		const watermarkToggle = function() {
			$('#onlyofficeWatermarkSettings').toggleClass('onlyoffice-hide', !$('#onlyofficeWatermark_enabled').prop('checked'))
		}

		$('#onlyofficeWatermark_enabled').click(watermarkToggle)

		$('#onlyofficeWatermark_shareAll').click(function() {
			$('#onlyofficeWatermark_shareRead').parent().toggleClass('onlyoffice-hide')
		})

		$('#onlyofficeWatermark_linkAll').click(function() {
			$('#onlyofficeWatermark_link_sensitive').toggleClass('onlyoffice-hide')
		})

		const watermarkGroupLists = [
			'allGroups',
		]

		const watermarkTagLists = [
			'allTags',
			'linkTags',
		]

		const watermarkNodeBehaviour = function(watermark) {
			const watermarkListToggle = function() {
				if ($('#onlyofficeWatermark_' + watermark).prop('checked')) {
					if (watermark.indexOf('Group') >= 0) {
						OC.Settings.setupGroupsSelect($('#onlyofficeWatermark_' + watermark + 'List'))
					} else {
						$('#onlyofficeWatermark_' + watermark + 'List').select2({
							allowClear: true,
							closeOnSelect: false,
							multiple: true,
							separator: '|',
							toggleSelect: true,
							placeholder: t(OCA.Onlyoffice.AppName, 'Select tag'),
							query: _.debounce(function(query) {
								query.callback({
									results: OC.SystemTags.collection.filterByName(query.term),
								})
							}, 100, true),
							initSelection(element, callback) {
								const selection = ($(element).val() || []).split('|').map(function(tagId) {
									return OC.SystemTags.collection.get(tagId)
								})
								callback(selection)
							},
							formatResult(tag) {
								return OC.SystemTags.getDescriptiveTag(tag)
							},
							formatSelection(tag) {
								return tag.get('name')
							},
							sortResults(results) {
								results.sort(function(a, b) {
									return OC.Util.naturalSortCompare(a.get('name'), b.get('name'))
								})
								return results
							},
						})
					}
				} else {
					$('#onlyofficeWatermark_' + watermark + 'List').select2('destroy')
				}
			}

			$('#onlyofficeWatermark_' + watermark).click(watermarkListToggle)
			watermarkListToggle()
		}

		$.each(watermarkGroupLists, function(i, watermarkGroup) {
			watermarkNodeBehaviour(watermarkGroup)
		})

		if (OC.SystemTags && OC.SystemTags.collection) {
			OC.SystemTags.collection.fetch({
				success() {
					$.each(watermarkTagLists, function(i, watermarkTag) {
						watermarkNodeBehaviour(watermarkTag)
					})
				},
			})
		}

		const connectionError = document.getElementById('onlyofficeSettingsState').value
		if (connectionError !== '') {
			OCP.Toast.error(t(OCA.Onlyoffice.AppName, 'Error when trying to connect') + ' (' + connectionError + ')')
		}

		$('#onlyofficeAddrSave').click(function() {
			$('.section-onlyoffice').addClass('icon-loading')
			const onlyofficeUrl = $('#onlyofficeUrl').val().trim()

			if (!onlyofficeUrl.length) {
				$('#onlyofficeInternalUrl, #onlyofficeStorageUrl, #onlyofficeSecret, #onlyofficeJwtHeader').val('')
			}

			const onlyofficeInternalUrl = ($('#onlyofficeInternalUrl').val() || '').trim()
			const onlyofficeStorageUrl = ($('#onlyofficeStorageUrl').val() || '').trim()
			const onlyofficeVerifyPeerOff = $('#onlyofficeVerifyPeerOff').prop('checked')
			const onlyofficeSecret = ($('#onlyofficeSecret').val() || '').trim()
			const jwtHeader = ($('#onlyofficeJwtHeader').val() || '').trim()
			const demo = $('#onlyofficeDemo').prop('checked')

			$.ajax({
				method: 'PUT',
				url: OC.generateUrl('apps/' + OCA.Onlyoffice.AppName + '/ajax/settings/address'),
				data: {
					documentserver: onlyofficeUrl,
					documentserverInternal: onlyofficeInternalUrl,
					storageUrl: onlyofficeStorageUrl,
					verifyPeerOff: onlyofficeVerifyPeerOff,
					secret: onlyofficeSecret,
					jwtHeader,
					demo,
				},
				success: function onSuccess(response) {
					$('.section-onlyoffice').removeClass('icon-loading')
					if (response && (response.documentserver != null || demo)) {
						$('#onlyofficeUrl').val(response.documentserver)
						$('#onlyofficeInternalUrl').val(response.documentserverInternal)
						$('#onlyofficeStorageUrl').val(response.storageUrl)
						$('#onlyofficeSecret').val(response.secret)
						$('#onlyofficeJwtHeader').val(response.jwtHeader)

						$('.section-onlyoffice-common, .section-onlyoffice-templates, .section-onlyoffice-watermark').toggleClass('onlyoffice-hide', (response.documentserver == null && !demo) || !!response.error.length)

						const versionMessage = response.version ? (' (' + t(OCA.Onlyoffice.AppName, 'version') + ' ' + response.version + ')') : ''

						if (response.error) {
							OCP.Toast.error(t(OCA.Onlyoffice.AppName, 'Error when trying to connect') + ' (' + response.error + ')' + versionMessage)
						} else {
							if (response.secret !== null) {
								OCP.Toast.success(t(OCA.Onlyoffice.AppName, 'Server settings have been successfully updated') + versionMessage)
							} else {
								const securityUrl = 'https://api.onlyoffice.com/docs/docs-api/get-started/how-it-works/security/'
								const content = '<div class="onlyoffice-popup-info"><p>' + t(OCA.Onlyoffice.AppName, 'Server settings have been successfully updated') + '</p>'
									+ '<p>' + t(OCA.Onlyoffice.AppName, 'To ensure the security of important parameters in ONLYOFFICE Docs requests, please set a Secret Key on the Settings page. To learn more, <a href="{url}" target="_blank">click here</a>.', { url: securityUrl }) + '</p>'
								OC.dialogs.message(content, t(OCA.Onlyoffice.AppName, t(OCA.Onlyoffice.AppName, 'Info')), null, OC.dialogs.OK_BUTTONS, () => {}, null, true)
							}
						}
					} else {
						$('.section-onlyoffice-common, .section-onlyoffice-templates, .section-onlyoffice-watermark').addClass('onlyoffice-hide')
					}
				},
			})
		})

		$('#onlyofficeSave').click(function() {
			$('.section-onlyoffice').addClass('icon-loading')

			const defFormats = {}
			$('input[id^="onlyofficeDefFormat"]').each(function() {
				defFormats[this.name] = this.checked
			})

			const editFormats = {}
			$('input[id^="onlyofficeEditFormat"]').each(function() {
				editFormats[this.name] = this.checked
			})

			const sameTab = $('#onlyofficeSameTab').is(':checked')
			const enableSharing = $('#onlyofficeEnableSharing').is(':checked')
			const preview = $('#onlyofficePreview').is(':checked')
			const advanced = $('#onlyofficeAdvanced').is(':checked')
			const cronChecker = $('#onlyofficeCronChecker').is(':checked')
			const emailNotifications = $('#onlyofficeEmailNotifications').is(':checked')
			const versionHistory = $('#onlyofficeVersionHistory').is(':checked')

			const limitGroupsString = $('#onlyofficeGroups').prop('checked') ? $('#onlyofficeLimitGroups').val() : ''
			const limitGroups = limitGroupsString ? limitGroupsString.split('|') : []

			const chat = $('#onlyofficeChat').is(':checked')
			const compactHeader = $('#onlyofficeCompactHeader').is(':checked')
			const feedback = $('#onlyofficeFeedback').is(':checked')
			const forcesave = $('#onlyofficeForcesave').is(':checked')
			const liveViewOnShare = $('#onlyofficeLiveViewOnShare').is(':checked')
			const help = $('#onlyofficeHelp').is(':checked')
			const reviewDisplay = $("input[type='radio'][name='reviewDisplay']:checked").attr('id').replace('onlyofficeReviewDisplay_', '')
			const theme = $("input[type='radio'][name='theme']:checked").attr('id').replace('onlyofficeTheme_', '')
			const unknownAuthor = $('#onlyofficeUnknownAuthor').val().trim()

			$.ajax({
				method: 'PUT',
				url: OC.generateUrl('apps/' + OCA.Onlyoffice.AppName + '/ajax/settings/common'),
				data: {
					defFormats,
					editFormats,
					sameTab,
					enableSharing,
					preview,
					advanced,
					cronChecker,
					emailNotifications,
					versionHistory,
					limitGroups,
					chat,
					compactHeader,
					feedback,
					forcesave,
					liveViewOnShare,
					help,
					reviewDisplay,
					theme,
					unknownAuthor,
				},
				success: function onSuccess(response) {
					$('.section-onlyoffice').removeClass('icon-loading')
					if (response) {
						OCP.Toast.success(t(OCA.Onlyoffice.AppName, 'Common settings have been successfully updated'))
					}
				},
			})
		})

		$('#onlyofficeSecuritySave').click(function() {
			$('.section-onlyoffice').addClass('icon-loading')

			const plugins = $('#onlyofficePlugins').is(':checked')
			const macros = $('#onlyofficeMacros').is(':checked')
			const protection = $("input[type='radio'][name='protection']:checked").attr('id').replace('onlyofficeProtection_', '')

			const watermarkSettings = {
				enabled: $('#onlyofficeWatermark_enabled').is(':checked'),
			}
			if (watermarkSettings.enabled) {
				watermarkSettings.text = ($('#onlyofficeWatermark_text').val() || '').trim()

				const watermarkLabels = [
					'allGroups',
					'allTags',
					'linkAll',
					'linkRead',
					'linkSecure',
					'linkTags',
					'shareAll',
					'shareRead',
				]
				$.each(watermarkLabels, function(i, watermarkLabel) {
					watermarkSettings[watermarkLabel] = $('#onlyofficeWatermark_' + watermarkLabel).is(':checked')
				})

				$.each(watermarkGroupLists.concat(watermarkTagLists), function(i, watermarkList) {
					const list = $('#onlyofficeWatermark_' + watermarkList).is(':checked') ? $('#onlyofficeWatermark_' + watermarkList + 'List').val() : ''
					watermarkSettings[watermarkList + 'List'] = list ? list.split('|') : []
				})
			}

			$.ajax({
				method: 'PUT',
				url: OC.generateUrl('apps/' + OCA.Onlyoffice.AppName + '/ajax/settings/security'),
				data: {
					watermarks: watermarkSettings,
					plugins,
					macros,
					protection,
				},
				success: function onSuccess(response) {
					$('.section-onlyoffice').removeClass('icon-loading')
					if (response) {
						OCP.Toast.success(t(OCA.Onlyoffice.AppName, 'Security settings have been successfully updated'))
					}
				},
			})
		})

		$('.section-onlyoffice-addr input').keypress(function(e) {
			const code = e.keyCode || e.which
			if (code === 13) {
				$('#onlyofficeAddrSave').click()
			}
		})

		$('#onlyofficeSecret-show').click(function() {
			if ($('#onlyofficeSecret').attr('type') === 'password') {
				$('#onlyofficeSecret').attr('type', 'text')
			} else {
				$('#onlyofficeSecret').attr('type', 'password')
			}
		})

		$('#onlyofficeClearVersionHistory').click(function() {
			OC.dialogs.confirm(
				t(OCA.Onlyoffice.AppName, 'Are you sure you want to clear metadata?'),
				t(OCA.Onlyoffice.AppName, 'Confirm metadata removal'),
				(clicked) => {
					if (!clicked) {
						return
					}

					$('.section-onlyoffice').addClass('icon-loading')

					$.ajax({
						method: 'DELETE',
						url: OC.generateUrl('apps/' + OCA.Onlyoffice.AppName + '/ajax/settings/history'),
						success: function onSuccess(response) {
							$('.section-onlyoffice').removeClass('icon-loading')
							if (response) {
								OCP.Toast.success(t(OCA.Onlyoffice.AppName, 'All history successfully deleted'))
							}
						},
					})
				},
			)
		})

		$('#onlyofficeAddTemplate').change(function() {
			const file = this.files[0]
			const data = new FormData()

			data.append('file', file)

			$('.section-onlyoffice').addClass('icon-loading')
			OCA.Onlyoffice.AddTemplate(file, (template, error) => {

				$('.section-onlyoffice').removeClass('icon-loading')
				const message = error
					? t(OCA.Onlyoffice.AppName, 'Error') + ': ' + error
					: t(OCA.Onlyoffice.AppName, 'Template successfully added')

				if (error) {
					OCP.Toast.error(message)
					return
				}

				if (template) {
					OCA.Onlyoffice.AttachItemTemplate(template)
				}
				OCP.Toast.success(message)
			})
		})

		$(document).on('click', '.onlyoffice-template-delete', function(event) {
			const item = $(event.target).parents('.onlyoffice-template-item')
			const templateId = $(item).attr('data-id')

			$('.section-onlyoffice').addClass('icon-loading')
			OCA.Onlyoffice.DeleteTemplate(templateId, (response) => {
				$('.section-onlyoffice').removeClass('icon-loading')

				const message = response.error
					? t(OCA.Onlyoffice.AppName, 'Error') + ': ' + response.error
					: t(OCA.Onlyoffice.AppName, 'Template successfully deleted')
				if (response.error) {
					OCP.Toast.error(message)
					return
				}

				$(item).detach()
				OCP.Toast.success(message)
			})
		})

		$(document).on('click', '.onlyoffice-template-item p', function(event) {
			const item = $(event.target).parents('.onlyoffice-template-item')
			const templateId = $(item).attr('data-id')

			const url = OC.generateUrl('/apps/' + OCA.Onlyoffice.AppName + '/{fileId}?template={template}',
				{
					fileId: templateId,
					template: 'true',
				})

			window.open(url)
		})

		$(document).on('click', '.onlyoffice-template-download', function(event) {
			const item = $(event.target).parents('.onlyoffice-template-item')
			const templateId = $(item).attr('data-id')

			const downloadLink = OC.generateUrl('apps/' + OCA.Onlyoffice.AppName + '/downloadas?fileId={fileId}&template={template}', {
				fileId: templateId,
				template: 'true',
			})

			location.href = downloadLink
		})

		const sameTabCheckbox = document.getElementById('onlyofficeSameTab')
		const sharingBlock = document.getElementById('onlyofficeEnableSharingBlock')
		const sharingCheckbox = document.getElementById('onlyofficeEnableSharing')

		sameTabCheckbox.onclick = function() {
			const isChecked = sameTabCheckbox.checked
			sharingBlock.style.display = isChecked ? 'none' : 'block'
			sharingCheckbox.checked = isChecked ? sharingCheckbox.checked : false
		}
	})

})(jQuery, OC)

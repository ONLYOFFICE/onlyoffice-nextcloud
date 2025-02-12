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

	OCA.Onlyoffice = _.extend({
		AppName: 'onlyoffice',
		templates: null,
	}, OCA.Onlyoffice)

	OCA.Onlyoffice.OpenTemplatePicker = function(name, extension, type) {

		$('#onlyoffice-template-picker').remove()

		$.get(OC.filePath(OCA.Onlyoffice.AppName, 'templates', 'templatePicker.html'),
			function(tmpl) {
				const $tmpl = $(tmpl)
				const dialog = $tmpl.octemplate({
					dialog_name: 'onlyoffice-template-picker',
					dialog_title: t(OCA.Onlyoffice.AppName, 'Select template'),
				})

				OCA.Onlyoffice.AttachTemplates(dialog, type)

				$('body').append(dialog)

				$('#onlyoffice-template-picker').ocdialog({
					closeOnEscape: true,
					modal: true,
					buttons: [{
						text: t('core', 'Cancel'),
						classes: 'cancel',
						click() {
							$(this).ocdialog('close')
						},
					}, {
						text: t(OCA.Onlyoffice.AppName, 'Create'),
						classes: 'primary',
						click() {
							const templateId = this.dataset.templateId
							const fileList = OCA.Files.App.fileList
							OCA.Onlyoffice.CreateFile(name + extension, fileList, templateId)
							$(this).ocdialog('close')
						},
					}],
				})
			})
	}

	OCA.Onlyoffice.GetTemplates = function() {
		if (OCA.Onlyoffice.templates != null) {
			return
		}

		$.get(OC.generateUrl('apps/' + OCA.Onlyoffice.AppName + '/ajax/template'),
			function onSuccess(response) {
				if (response.error) {
					OC.Notification.show(response.error, {
						type: 'error',
						timeout: 3,
					})
					return
				}

				OCA.Onlyoffice.templates = response

			})
	}

	OCA.Onlyoffice.AddTemplate = function(file, callback) {
		const data = new FormData()
		data.append('file', file)

		$.ajax({
			method: 'POST',
			url: OC.generateUrl('apps/' + OCA.Onlyoffice.AppName + '/ajax/template'),
			data,
			processData: false,
			contentType: false,
			success: function onSuccess(response) {
				if (response.error) {
					callback(null, response.error)
					return
				}

				callback(response, null)
			},
		})
	}

	OCA.Onlyoffice.DeleteTemplate = function(templateId, callback) {
		$.ajax({
			method: 'DELETE',
			url: OC.generateUrl('apps/' + OCA.Onlyoffice.AppName + '/ajax/template?templateId={templateId}',
				{
					templateId,
				}),
			success: function onSuccess(response) {
				if (response) {
					callback(response)
				}
			},
		})
	}

	OCA.Onlyoffice.AttachTemplates = function(dialog, type) {
		const emptyItem = dialog[0].querySelector('.onlyoffice-template-item')

		OCA.Onlyoffice.templates.forEach(template => {
			if (template.type !== type) {
				return
			}
			const item = emptyItem.cloneNode(true)

			$(item.querySelector('label')).attr('for', 'template_picker-' + template.id)
			item.querySelector('input').id = 'template_picker-' + template.id
			item.querySelector('img').src = template.icon
			item.querySelector('p').textContent = template.name
			item.onclick = function() {
				dialog[0].dataset.templateId = template.id
			}
			dialog[0].querySelector('.onlyoffice-template-container').appendChild(item)
		})

		$(emptyItem.querySelector('label')).attr('for', 'template_picker-0')
		emptyItem.querySelector('input').id = 'template_picker-0'
		emptyItem.querySelector('input').checked = true
		emptyItem.querySelector('img').src = OC.generateUrl('/core/img/filetypes/x-office-' + type + '.svg')
		emptyItem.querySelector('p').textContent = t(OCA.Onlyoffice.AppName, 'Empty')
		emptyItem.onclick = function() {
			dialog[0].dataset.templateId = '0'
		}
	}

	OCA.Onlyoffice.AttachItemTemplate = function(template) {
		$.get(OC.filePath(OCA.Onlyoffice.AppName, 'templates', 'templateItem.html'),
			function(item) {
				item = $(item)

				item.attr('data-id', template.id)
				item.children('img').attr('src', template.icon)
				item.children('p').text(template.name)

				$('.onlyoffice-template-container').append(item)
			})
	}

	OCA.Onlyoffice.TemplateExist = function(type) {
		const isExist = OCA.Onlyoffice.templates.some((template) => {
			return template.type === type
		})

		return isExist
	}

})(jQuery, OC)

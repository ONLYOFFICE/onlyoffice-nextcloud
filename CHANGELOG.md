# Change Log

## 9.11.0
## Changed
- compatible with Nextcloud 32

## 9.10.0
## Added
- pagination support in getUser
- insert svg to editor
- view vsdx, vssx, vstx, vsdm, vssm, vstm
- view odg, md
- setting for enabling live-view for shared docs
- refresh file when connection is restored

## Changed
- select user for protected region in team folder
- edit xlsb
- customization_goback setting removed
- there are only system, light and dark themes
- toolbarNoTabs setting removed
- display error when opening settings page if it exists

## 9.9.0
## Added
- support external link for reference data
- disable download setting
- system and contrast-dark themes
- close button
- jwt expire configuration

## Changed
- Nextcloud v29 is no longer supported

## 9.8.0
## Added
- sharing panel when editing in a new tab
- shardKey param to url for requests to Docs

## Changed
- fix opening share link on Nextcloud 29

## 9.7.0
## Added
- support hwp, hwpx, pages, numbers, key formats
## Changed
- compatible with Nextcloud 31

## 9.6.0
## Added
- setting for setup unknown author display name
- setting for sending notifications by email
- support IMPORTRANGE

## Changed
- fix address when opening editor
- URL for requests to Conversion API
- reading JSON instead of XML from Conversion API
- Nextcloud v28 is no longer supported
- skip dashboard when opening from desktop

## 9.5.0
## Added
- default empty templates
- Finnish, Hebrew, Norwegian, Slovenian empty file templates

## Changed
- demo server address changed
- editing pdf by default

## 9.4.0
## Added
- support tiff format for inserting

## Changed
- compatible with Nextcloud 30
- Nextcloud v27 is no longer supported
- using user's timezone for watermark

## 9.3.0
## Changed
- creating and editing pdf form

## 9.2.2
## Changed
- fix settings page when tag app is disabled

## 9.2.0
## Changed
- compatible with Nextcloud 29

## 9.1.2
## Changed
- artifact rebuilt

## 9.1.0
## Added
- support of user avatar in editor
- list of users to protect ranges of cells
- setting for disable editors cron check
- advanced access rights for talk files and share links
- selecting a document to combine from the storage
- reference data from coediting
- opening a reference data source
- changing a reference data source
- Arabic and Serbian empty file templates

## Changed
- fix author in group folder
- fixed opening file without download access
- fixed guest redirect when limiting the app to groups
- fixed error display in the mobile application
- fixed mobile editor size
- offline viewer for share link
- updatable list of supported formats
- filling pdf instead oform
- version opening from right tab is no longer supported
- Nextcloud v25 is no longer supported

## 9.0.0
## Changed
- compatible with Nextcloud 28
- agpl v3 license

## 8.2.4
## Changed
- remove link to docs cloud

## 8.2.2
## Added
- Ukrainian translation

## Changed
- fix disabling background job
- fix opening direct link

## 8.2.0
## Added
- jwt header setting
- Paste Special to add a link between files
- Basque translation
- Link to docs cloud
- background job for checking editors availability

## Changed
- editing by shared link without configuring file protection, chat and without the possibility of mentioning
- fileType to history data
- change page title when editing a file
- jwt library update
- fileType parameter used in callback instead of extension from url (DocumentServer 7.0 required)

## 8.1.0
## Changed
- advanced permissions are only available with full access

## 8.0.0
## Changed
- compatible with Nextcloud 27

## 7.8.0
## Added
- disable plugins setting
- document protection setting
- jwt leeway setting
- Danish translation

## Changed
- fix thumbnails for version files
- fix notification length
- compatible with Nextcloud 26
- additional check availability for group and external files when mention

## 7.6.8
## Changed
- fix download permission

## 7.6.6
## Added
- Dutch translation
- Chinese (Traditional, Taiwan), Basque (Spain) empty file templates

## Changed
- compatible with Nextcloud 25
- generate preview by default
- fix editing with federated share
- fix opening file in new tab
- fix watermark for shared file by link
- fix update application
- Nextcloud v24 is no longer supported

## 7.5.4
## Changed
- fix opening editor in new tab
- fix download as on MacOS

## 7.5.2
## Changed
- fix translation for v24

## 7.5.0
## Changed
- fix viewer app
- Nextcloud v22 is no longer supported
- Nextcloud v23 is no longer supported

## 7.4.2
## Changed
- fix translation

## 7.4.0
## Added
- advanced access rights
- macro launch setting
- opening file location from viewer
- Catalan translation
- theme setting

## Changed
- fix editor lang

## 7.3.4
## Added
- Turkish and Galician empty file templates

## Changed
- Nextcloud v21 is no longer supported
- compatible with Nextcloud 24

## 7.3.2
## Changed
- Nextcloud v20 is no longer supported
- fixed link in mention notifications
- title for new file
- fix editing after desktop sync

## 7.3.0
## Added
- support docxf and oform formats
- create blank docxf from creation menu
- create docxf from docx from creation menu
- create oform from docxf from document manager

## 7.2.1
## Added
- check document server version

## Changed
- fix preview generation
- compatible with Nextcloud 23

## 7.2.0
## Added
- set favicon on editor page
- versions restore from editor

## Changed
- fixed privacy rules for get users when mention
- document server v6.0 and earlier is no longer supported
- editing by link only for available groups
- open share link directly

## 7.1.2
## Changed
- fix editing from mobile app

## 7.1.0
## Added
- mentions in comments
- favorite status in the editor
- creation from a template from the editor
- download as
- downloading a template from settings
- opening action link

## Changed
- redirect from dashboard on desktop
- Nextcloud v19 is no longer supported

## 7.0.4
## Changed
- compatible with Nextcloud 22

## 7.0.2
## Changed
- fixed registration of file actions

## 6.4.2
## Changed
- fixed registration of file actions

## 7.0.0
## Added
- support for templates from Nextcloud v21

## Changed
- Nextcloud v19, v20 is no longer supported

## 6.4.0
## Added
- create file from editor
- more empty files in different languages
- file templates

## Changed
- open a version from the history of supported formats only
- document server v5.5 and earlier is no longer supported
- disabled copying to clipboard if there is no download permission

## 6.3.0
## Added
- save as in current folder
- hide secret key in settings
- configuring version storage
- clearing history data

## Changed
- thumbnails for small files only
- history for federated share files is not stored
- compatible with Nextcloud 21
- Nextcloud v18 is no longer supported

## 6.2.0
## Changed
- the ability to use forcesave for federated share files

## 6.1.0
## Added
- use guest name from talk
- connection test command
- store author name for version
- generate file preview
- Italian translation

## Changed
- display local time in history
- Nextcloud v17 is no longer supported

## 6.0.2
## Changed
- compatible with Nextcloud 20

## 6.0.0
## Added
- saving intermediate versions when editing
- Chinese translation

## Changed
- fix image insertion
- fix styles for inline editor

## 5.0.0
## Added
- support for OpenDocument Templates
- Japanese translation
- certificate verification setting
- version history

## Changed
- apache license
- fix styles for desktop
- loader page when creating a file
- fix share tab opening

## 4.3.0
## Added
- integration to viewer app
- show the version of the Document Service on the settings page

## Changed
- Nextcloud v16 is no longer supported
- notification with toastify
- proper header bar for public share links

## 4.2.0
## Added
- review display settings

## Changed
- compatible with Nextcloud 19

## 4.1.4
## Changed
- fix file opening in Nextcloud Android mobile application

## 4.1.2
## Changed
- fix file opening with a sidebar
- fix file opening in Nextcloud Android mobile application
- fix file opening the federated file when watermark is enabled

## 4.1.1
## Changed
- compatible with Nextcloud 16-17

## 4.1.0
## Added
- inline editor if using the same tab, opening the sidebar, sharing from the editor
- creating, editing and co-authoring files in Nextcloud Android mobile application
- setting zoom and autosave
- selection of a file for comparison (DocumentServer 5.5 required)

## Changed
- compatible with Nextcloud 18
- Nextcloud v15 is no longer supported

## 4.0.0
## Added
- Polish translation
- British (en_GB) file templates

## Changed
- co-editing for federated share
- Nextcloud v14 is no longer supported

## 3.0.2
## Changed
- federated share saving fixed

## 3.0.0
## Added
- "save as" to the folder
- inserting images from the folder
- Mail Merge
- connection to the demo server
- embedding a watermark

## Changed
- updated files for compatibility with MS Office v2016

## 2.4.0
## Added
- compatibility with encryption

## 2.3.0
## Added
- editor customization

## Changed
- the settings page is splitted into two sections
- support master key encryption
- fix getting domain for desktop
- title in the convertation request

## 2.1.10
## Changed
- compatible with Nextcloud 16

## 2.1.6
## Added
- file creation in public folder
- file convertion in public folder
- Bulgarian translation
- file templates in Dutch

## Changed
- fix editor size on mobile
- fix php warning

## 2.1.2
## Added
- restricting access for groups
- goback from editor to shared folder by link

## Changed
- using notification methods
- compatible with Nextcloud 15

## 2.1.0
## Added
- Swedish translation
- support token in the body
- desktop mode

## Changed
- fix opening shared file by registered user
- fix translations

## 2.0.4
## Added
- opening for editing not OOXML

## Changed
- different keys for a file from different instances
- replace hash generator to JWT

## 2.0.2
## Changed
- deleted unsupported methods

## 1.4.0
## Added
- transition from the editor to the list of files in the same tab
- default action for all supported formats
- redirect to the login page if are not logged in
- a separate action to call the file conversion

## Changed
- improved checks when saving connection settings
- expanded the list of formats
- fixed exceptions when opening file shared by link

## 1.3.0
## Added
- add macro-enabled and template formats
- support shared link for document
- customization editor

## Changed
- update template empty files
- fix collaboration editing
- view without converting

## 1.2.0
## Added
- disabling for incorrect settings
- Brazilian Poruguese translation
- detecting mobile

## Changed
- initialization script
- case sensitivity in extension
- сreating files with an existing name

## 1.1.6
## Changed
- update description

## 1.1.5
## Added
- the ability to change the header key

## Changed
- fix opening file from external storage
- fix opening federated shared file

## 1.1.4
## Added
- extended list of languages for new files
- work with self-signed certificates

## Changed
- files of new presentations
- fix German l10n
- changed verification of settings

## 1.1.3
## Added
- fixing bug

## 1.1.2
## Added
- translation
- file name into page title

## 1.1.1
## Added
- translation
- signed code

## 1.0.5
### Added
- default name for new file
- getting default value from server config
- checking the encryption module

### Changed
- included editing for csv format
- fix track activities and versions

### Security
- jwt signature for inbox request from Document Server

## 1.0.4
### Added
- advanced server settings for specifying internal addresses
- opening file editor in a same tab

### Changed
- setting default aplication for editable formats
- new file on user language
- compatible with Nextcloud 12

## 1.0.3
- compatible with ownCloud 10

## 1.0.2
### Added
- logging
- checking Document Server address on save
- checking version of onlyoffice
- set language of editor

### Changed
- replace own Response class to OCP\AppFramework\Http class from core

### Security
- jwt signature for request to Document Server

## 1.0.1
- fix exception when versions app is disabled
- adding protocol to document server url
- onlyofficeOpen is default action
- Nextcloud 11 compatibility

## 1.0.0
- Initial release

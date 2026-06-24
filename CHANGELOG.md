# Change Log

## Added
- AI autofill plugin for fillable PDF forms

## 9.14.2

## Changed
- stop Firefox collapsing the page when toggling checkboxes
- fixed an issue where the editor was not using the user's preferred language

## 9.14.0

### Added
- restrict external storage admin setting
- send mail merge messages using the Nextcloud Mail provider
- cover circle shares in advanced permissions

### Changed
- fixed createUrl and template URLs being incorrectly decoded
- fixed extra permissions being unavailable for user shares when admin resharing is disabled
- fixed insecure direct object reference in share extra permissions API
- updated PHP codebase to Nextcloud 32 API
- adopted PHP 8.1 syntax, internal PHP refactoring
- migrated from legacy hooks to event listeners
- fixed opening editor for shared link

## 9.13.0

### Changed
- support formats from Document Server v9.3
- fixed watermark not applying to link shares with system tags
- fixed opening the editor with a watermark on tagged files
- replaced deprecated TemplateFileCreator::setIconClass with TemplateFileCreator::setIconSvgInline method
- OCA\Onlyoffice\Preview class now implements OCP\Preview\IProviderV2 interface
- fixed invalid IManager::getSharesBy() method call when building share list for advanced sidebar tab

## 9.12.0

### Added
- plugin description and useful links in admin settings

### Changed
- fix opening a shared link when group access to the app is restricted
- Nextcloud v30 is no longer supported
- delete extra permissions on file delete
- replaced general save success message with dedicated messages for each settings section
- show a warning popup when the JWT secret is empty and a success message otherwise
- download all document pages when converting to image types with Download As method
- extend supported watermark placeholders
- fix opening a shared folder link with password

## 9.11.0

### Changed
- compatible with Nextcloud 32

## 9.10.0

### Added
- pagination support in getUser
- insert SVG into the editor
- view vsdx, vssx, vstx, vsdm, vssm, vstm (Document Server 9.0 required)
- view odg, md (Document Server 9.0 required)
- setting for enabling live-view for shared docs
- refresh file when connection is restored

### Changed
- select user for protected region in team folder
- edit xlsb (Document Server 9.0 required)
- customization_goback setting removed
- only system, light and dark themes are available
- toolbarNoTabs setting removed
- display an error when opening the settings page if it exists

## 9.9.0

### Added
- support external links for reference data
- disable download setting
- system and contrast-dark themes
- close button
- JWT expiration configuration

### Changed
- Nextcloud v29 is no longer supported

## 9.8.0

### Added
- sharing panel when editing in a new tab
- shardkey parameter in the URL for requests to Docs

### Changed
- fix opening share link on Nextcloud 29

## 9.7.0

### Added
- support hwp, hwpx, pages, numbers, and key formats (Document Server 8.3 required)

### Changed
- compatible with Nextcloud 31

## 9.6.0

### Added
- setting for setting up the display name of an unknown author
- setting for sending notifications by email
- support IMPORTRANGE

### Changed
- fix address when opening editor
- URL for requests to Conversion API
- reading JSON instead of XML from Conversion API
- Nextcloud v28 is no longer supported
- skip dashboard when opening from desktop

## 9.5.0

### Added
- default empty templates
- Finnish, Hebrew, Norwegian, Slovenian empty file templates

### Changed
- demo server address changed
- editing PDF by default

## 9.4.0

### Added
- support TIFF format for insertion

### Changed
- compatible with Nextcloud 30
- Nextcloud v27 is no longer supported
- using user's timezone for watermark

## 9.3.0

### Changed
- creating and editing PDF forms

## 9.2.2

### Changed
- fix settings page when tag app is disabled

## 9.2.0

### Changed
- compatible with Nextcloud 29

## 9.1.2

### Changed
- artifact rebuilt

## 9.1.0

### Added
- support for user avatars in the editor
- list of users to protect ranges of cells
- setting for disabling the editors cron check
- advanced access rights for talk files and share links
- selecting a document to combine from the storage
- reference data from coediting
- opening a reference data source
- changing a reference data source
- Arabic and Serbian empty file templates

### Changed
- fix author in group folder
- fixed opening file without download access
- fixed guest redirect when limiting the app to groups
- fixed error display in the mobile application
- fixed mobile editor size
- offline viewer for share link
- updatable list of supported formats
- filling PDF instead of OFORM
- opening versions from the right tab is no longer supported
- Nextcloud v25 is no longer supported

## 9.0.0

### Changed
- compatible with Nextcloud 28
- AGPL v3 license

## 8.2.4

### Changed
- remove link to Docs Cloud

## 8.2.2

### Added
- Ukrainian translation

### Changed
- fix disabling background job
- fix opening direct link

## 8.2.0

### Added
- JWT header setting
- Paste Special to add a link between files
- Basque translation
- link to Docs Cloud
- background job for checking editors availability

### Changed
- editing by shared link without configuring file protection or chat, and without the ability to mention users
- fileType to history data
- change page title when editing a file
- JWT library update
- fileType parameter used in callback instead of the extension from the URL (Document Server 7.0 required)

## 8.1.0

### Changed
- advanced permissions are only available with full access

## 8.0.0

### Changed
- compatible with Nextcloud 27

## 7.8.0

### Added
- disable plugins setting
- document protection setting
- JWT leeway setting
- Danish translation

### Changed
- fix thumbnails for version files
- fix notification length
- compatible with Nextcloud 26
- additional availability check for group and external files when mentioning

## 7.6.8

### Changed
- fix download permission

## 7.6.6

### Added
- Dutch translation
- Chinese (Traditional, Taiwan), Basque (Spain) empty file templates

### Changed
- compatible with Nextcloud 25
- generate preview by default
- fix editing with federated share
- fix opening file in new tab
- fix watermark for shared file by link
- fix update application
- Nextcloud v24 is no longer supported

## 7.5.4

### Changed
- fix opening editor in new tab
- fix download as on macOS

## 7.5.2

### Changed
- fix translation for v24

## 7.5.0

### Changed
- fix viewer app
- Nextcloud v22 is no longer supported
- Nextcloud v23 is no longer supported

## 7.4.2

### Changed
- fix translation

## 7.4.0

### Added
- advanced access rights
- macro launch setting
- opening file location from viewer
- Catalan translation
- theme setting

### Changed
- fix editor lang

## 7.3.4

### Added
- Turkish and Galician empty file templates

### Changed
- Nextcloud v21 is no longer supported
- compatible with Nextcloud 24

## 7.3.2

### Changed
- Nextcloud v20 is no longer supported
- fixed link in mention notifications
- title for new file
- fix editing after desktop sync

## 7.3.0

### Added
- support docxf and oform formats
- create blank docxf from creation menu
- create docxf from docx from creation menu
- create oform from docxf from document manager

## 7.2.1

### Added
- check Document Server version

### Changed
- fix preview generation
- compatible with Nextcloud 23

## 7.2.0

### Added
- set favicon on editor page
- restoring versions from the editor

### Changed
- fixed privacy rules for getting users when mentioning
- Document Server v6.0 and earlier is no longer supported
- editing by link only for allowed groups
- open share link directly

## 7.1.2

### Changed
- fix editing from mobile app

## 7.1.0

### Added
- mentions in comments
- favorite status in the editor
- creation from a template from the editor
- download as
- downloading a template from settings
- opening action link

### Changed
- redirect from dashboard on desktop
- Nextcloud v19 is no longer supported

## 7.0.4

### Changed
- compatible with Nextcloud 22

## 7.0.2

### Changed
- fixed registration of file actions

## 6.4.2

### Changed
- fixed registration of file actions

## 7.0.0

### Added
- support for templates from Nextcloud v21

### Changed
- Nextcloud v19, v20 is no longer supported

## 6.4.0

### Added
- create file from editor
- more empty files in different languages
- file templates

### Changed
- open versions from the history of supported formats only
- Document Server v5.5 and earlier is no longer supported
- disabled copying to clipboard if there is no download permission

## 6.3.0

### Added
- save as in current folder
- hide secret key in settings
- configuring version storage
- clearing history data

### Changed
- thumbnails for small files only
- history for federated share files is not stored
- compatible with Nextcloud 21
- Nextcloud v18 is no longer supported

## 6.2.0

### Changed
- ability to use forcesave for federated share files

## 6.1.0

### Added
- use guest name from talk
- connection test command
- store author name for version
- generate file preview
- Italian translation

### Changed
- display local time in history
- Nextcloud v17 is no longer supported

## 6.0.2

### Changed
- compatible with Nextcloud 20

## 6.0.0

### Added
- saving intermediate versions when editing
- Chinese translation

### Changed
- fix image insertion
- fix styles for inline editor

## 5.0.0

### Added
- support for OpenDocument Templates
- Japanese translation
- certificate verification setting
- version history

### Changed
- Apache license
- fix styles for desktop
- loader page when creating a file
- fix share tab opening

## 4.3.0

### Added
- integration with the viewer app
- show the version of the Document Service on the settings page

### Changed
- Nextcloud v16 is no longer supported
- notifications with Toastify
- proper header bar for public share links

## 4.2.0

### Added
- review display settings

### Changed
- compatible with Nextcloud 19

## 4.1.4

### Changed
- fix file opening in Nextcloud Android mobile application

## 4.1.2

### Changed
- fix file opening with a sidebar
- fix file opening in Nextcloud Android mobile application
- fix file opening the federated file when watermark is enabled

## 4.1.1

### Changed
- compatible with Nextcloud 16-17

## 4.1.0

### Added
- inline editor if using the same tab, opening the sidebar, sharing from the editor
- creating, editing, and co-authoring files in the Nextcloud Android mobile application
- setting zoom and autosave
- selection of a file for comparison (Document Server 5.5 required)

### Changed
- compatible with Nextcloud 18
- Nextcloud v15 is no longer supported

## 4.0.0

### Added
- Polish translation
- British (en_GB) file templates

### Changed
- co-editing for federated share
- Nextcloud v14 is no longer supported

## 3.0.2

### Changed
- federated share saving fixed

## 3.0.0

### Added
- "save as" to the folder
- inserting images from the folder
- Mail Merge
- connection to the demo server
- embedding a watermark

### Changed
- updated files for compatibility with MS Office v2016

## 2.4.0

### Added
- compatibility with encryption

## 2.3.0

### Added
- editor customization

### Changed
- the settings page is split into two sections
- support for master key encryption
- fix getting domain for desktop
- title in the conversion request

## 2.1.10

### Changed
- compatible with Nextcloud 16

## 2.1.6

### Added
- file creation in public folder
- file conversion in a public folder
- Bulgarian translation
- file templates in Dutch

### Changed
- fix editor size on mobile
- fix PHP warning

## 2.1.2

### Added
- restricting access for groups
- go back from the editor to the shared folder by link

### Changed
- using notification methods
- compatible with Nextcloud 15

## 2.1.0

### Added
- Swedish translation
- support for token in the body
- desktop mode

### Changed
- fix opening a shared file by a registered user
- fix translations

## 2.0.4

### Added
- opening non-OOXML files for editing

### Changed
- different keys for a file from different instances
- replace hash generator to JWT

## 2.0.2

### Changed
- deleted unsupported methods

## 1.4.0

### Added
- transition from the editor to the list of files in the same tab
- default action for all supported formats
- redirect to the login page if the user is not logged in
- a separate action to call the file conversion

### Changed
- improved checks when saving connection settings
- expanded the list of formats
- fixed exceptions when opening a file shared by link

## 1.3.0

### Added
- add macro-enabled and template formats
- support shared links for documents
- editor customization

### Changed
- update empty template files
- fix collaboration editing
- view without converting

## 1.2.0

### Added
- disabling for incorrect settings
- Brazilian Portuguese translation
- detecting mobile

### Changed
- initialization script
- case sensitivity in extension
- creating files with an existing name

## 1.1.6

### Changed
- update description

## 1.1.5

### Added
- the ability to change the header key

### Changed
- fix opening file from external storage
- fix opening a federated shared file

## 1.1.4

### Added
- extended list of languages for new files
- work with self-signed certificates

### Changed
- files for new presentations
- fix German l10n
- changed verification of settings

## 1.1.3

### Added
- bug fix

## 1.1.2

### Added
- translation
- file name in the page title

## 1.1.1

### Added
- translation
- signed code

## 1.0.5

### Added
- default name for new file
- getting default value from server config
- checking the encryption module

### Changed
- included editing for CSV format
- fix tracking activities and versions
- JWT signature for inbox requests from Document Server

## 1.0.4

### Added
- advanced server settings for specifying internal addresses
- opening the file editor in the same tab

### Changed
- setting the default application for editable formats
- new file in the user's language
- compatible with Nextcloud 12

## 1.0.3

### Changed
- compatible with ownCloud 10

## 1.0.2

### Added
- logging
- checking Document Server address on save
- checking the version of ONLYOFFICE
- set the language of the editor

### Changed
- replace own Response class with OCP\AppFramework\Http class from core
- JWT signature for requests to Document Server

## 1.0.1

### Changed
- fix exception when versions app is disabled
- adding protocol to Document Server URL
- onlyofficeOpen is default action
- Nextcloud 11 compatibility

## 1.0.0

### Added
- Initial release

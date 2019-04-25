# Change Log

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
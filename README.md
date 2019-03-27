# ![](screenshots/icon.png) Nextcloud ONLYOFFICE integration app

This app enables users to edit office documents from [Nextcloud](https://nextcloud.com) using ONLYOFFICE Document Server.
Currently the following document formats can be edited with this app: csv, docx, pptx, txt, xlsx.
The above mentioned formats are also available for viewing together with pdf.
The edited files of the corresponding type can be converted into the Office Open XML formats: doc, docm, dot, dotx, epub, htm, html, odp, odt, pot, potm, potx, pps, ppsm, ppsx, ppt, pptm, rtf, xls, xlsm, xlsx, xlt, xltm, xltx.

The app will create an item in the `new` (+) menu to create **Document**, **Spreadsheet**, **Presentation**.
It will also create a new **Open in ONLYOFFICE** menu option within the document library for Office documents.
This allows multiple users to collaborate in real time and to save back those changes to Nextcloud.

You can also use our **[Docker installation](https://github.com/ONLYOFFICE/docker-onlyoffice-nextcloud)** to get installed and configured Document Server and Nextcloud installation with a couple of commands.


## Installing ONLYOFFICE Document Server

You will need an instance of ONLYOFFICE Document Server that is resolvable and connectable both from Nextcloud and any end clients (version 4.2.7 and later are supported for use with the app).
If that is not the case, use the official ONLYOFFICE Document Server documentation page: [Document Server for Linux](https://helpcenter.onlyoffice.com/server/linux/document/linux-installation.aspx). ONLYOFFICE Document Server must also be able to POST to Nextcloud directly.

Starting with version 4.3.0, ONLYOFFICE Document Server and Nextcloud can be installed either on different computers, or on the same machine.
In case you select the latter variant, you will need to set up a custom port for Document Server as by default both ONLYOFFICE Document Server and Nextcloud work on port 80.
Or you can use Document Server behind a proxy, please refer to [this article](https://helpcenter.onlyoffice.com/server/document/document-server-proxy.aspx) to learn how you can configure it.

The easiest way to start an instance of ONLYOFFICE Document Server is to use [Docker](https://github.com/ONLYOFFICE/Docker-DocumentServer).


## Installing Nextcloud ONLYOFFICE integration app

The Nextcloud administrator can install the integration app from the in-built application market.
For that go to the user name and select **Apps**.
After that find **ONLYOFFICE** in the list of available applications and install it.

If the server with the Nextcloud installed does not have an Internet access, or if you need it for some other reason, the administrator can install the application manually.
To start using ONLYOFFICE Document Server with Nextcloud, the following steps must be performed:

1. Go to the Nextcloud server _apps/_ directory (or some other directory [used](https://docs.nextcloud.com/server/latest/admin_manual/apps_management.html#using-custom-app-directories)):
    ```
    cd apps/
    ```

2. Get the Nextcloud ONLYOFFICE integration app.
There are several ways to do that:

    a. Download the latest signed version from the official store for [Nextcloud](https://apps.nextcloud.com/apps/onlyoffice).

    b. Or you can download the latest signed version from the application [release page](https://github.com/ONLYOFFICE/onlyoffice-nextcloud/releases) on GitHub.

    c. Or you can clone the application source code and compile it yourself: 
    ```
    git clone https://github.com/ONLYOFFICE/onlyoffice-nextcloud.git onlyoffice
    ```

2. Change the owner to update the application right from Nextcloud web interface:
    ```
    chown -R www-data:www-data onlyoffice
    ```

3. In Nextcloud open the `~/index.php/settings/apps?category=disabled` page with _Not enabled_ apps by administrator and click _Enable_ for the **ONLYOFFICE** application.


## Configuring Nextcloud ONLYOFFICE integration app

In Nextcloud open the `~/index.php/settings/admin/onlyoffice` page with administrative settings for **ONLYOFFICE** section.
Enter the following address to connect ONLYOFFICE Document Server:

```
https://<documentserver>/
```

Where the **documentserver** is the name of the server with the ONLYOFFICE Document Server installed.
The address must be accessible for the user browser and from the Nextcloud server.
The Nextcloud server address must also be accessible from ONLYOFFICE Document Server for correct work.

Sometimes your network configuration might not allow the requests between installed Nextcloud and ONLYOFFICE Document Server using the public addresses.
The _Advanced server settings_ allows to set the ONLYOFFICE Document Server address for internal requests from Nextcloud server and the returning Nextcloud address for the internal requests from ONLYOFFICE Document Server.
You need to enter them in the appropriate fields.

To restrict the access to ONLYOFFICE Document Server and for security reasons and data integrity the encrypted signature is used.
Specify the _Secret key_ in the Nextcloud administrative configuration.
In the ONLYOFFICE Document Server [config file](https://api.onlyoffice.com/editors/signature/) specify the same secret key and enable the validation.

Enable or disable the _Open file in the same tab_ setting.

The **Open in ONLYOFFICE** action will be added to the file context menu.
You can specify this action as default and it will be used when the file name is clicked for the selected file types.


## How it works

The ONLYOFFICE integration follows the API documented here https://api.onlyoffice.com/editors/basic:

* When creating a new file, the user navigates to a document folder within Nextcloud and clicks the **Document**, **Spreadsheet** or **Presentation** item in the _new_ (+) menu.

* The browser invokes the `create` method in the `/lib/Controller/EditorController.php` controller.
This method adds the copy of the file from the assets folder to the folder the user is currently in.

* Or, when opening an existing file, the user navigates to it within Nextcloud and selects the **Open in ONLYOFFICE** menu option.

* A new browser tab is opened and the `index` method of the `/lib/Controller/EditorController.php` controller is invoked.

* The app prepares a JSON object with the following properties:

  * **url** - the URL that ONLYOFFICE Document Server uses to download the document;
  * **callback** - the URL that ONLYOFFICE Document Server informs about status of the document editing;
  * **documentServerUrl** - the URL that the client needs to respond to ONLYOFFICE Document Server (can be set at the administrative settings page);
  * **key** - the UUID+Modified Timestamp to instruct ONLYOFFICE Document Server whether to download the document again or not;
  * **fileName** - the document Title (name);
  * **userId** - the identification of the user;
  * **userName** - the name of the user.

* Nextcloud takes this object and constructs a page from `templates/editor.php` template, filling in all of those values so that the client browser can load up the editor.

* The client browser makes a request for the javascript library from ONLYOFFICE Document Server and sends ONLYOFFICE Document Server the DocEditor configuration with the above properties.

* Then ONLYOFFICE Document Server downloads the document from Nextcloud and the user begins editing.

* ONLYOFFICE Document Server sends a POST request to the _callback_ URL to inform Nextcloud that a user is editing the document.

* When all users and client browsers are done with editing, they close the editing window.

* After [10 seconds](https://api.onlyoffice.com/editors/save#savedelay) of inactivity, ONLYOFFICE Document Server sends a POST to the _callback_ URL letting Nextcloud know that the clients have finished editing the document and closed it.

* Nextcloud downloads the new version of the document, replacing the old one.


## Known issues

* If the document is shared using the **Federated Cloud Sharing** app, the co-editing among the servers will not be avaialble.
The users from one and the same server can edit the document in the co-editing mode, but the users from two (or more) different servers will not be able to collaborate on the same document in real time.

* Adding the storage using the **External storages** app has issues with the co-editing in some cases.
If the connection is made using the same authorization keys (the _Username and password_ or _Global credentials_ authentication type is selected), then the co-editing is available for the users.
If different authorization keys are used (_Log-in credentials, save in database_ or _User entered, store in database_ authentication options), the co-editing is not available.
When the _Log-in credentials, save in session_ authentication type is used, the files cannot be opened in the editor.

* Nextcloud provides an option to encrypt the file storage.
But if the encryption with the _per-user encryption keys_ (used by default in Nextcloud **Default encryption module** app) is enabled, ONLYOFFICE Document Server cannot open the encrypted files for editing and save them after the editing.
The ONLYOFFICE section of the administrative settings page will display a notification about it.
However if you set the encryption with the _master key_, ONLYOFFICE application will work as intended.
The instruction on enabling _master key_ based encryption is available in the official documentation on [Nextcloud](https://docs.nextcloud.com/server/latest/admin_manual/configuration_files/encryption_configuration.html#occ-encryption-commands) websites.

* If you are using a self-signed certificate for your **Document Server**, Nextcloud will not validate such a certificate and will not allow connection to/from **Document Server**. This issue can be solved the following way: locate the Nextcloud config file (_/nextcloud/config/config.php_) and open it. Insert the following section to it:
    ```
    'onlyoffice' => array (
        'verify_peer_off' => true
    )
    ```
    This will disable the certificate verification and allow Nextcloud to establish connection with **Document Server**, but you must remember that this is a temporary insecure solution and we strongly recommend that you replace the certificate with the one issued by some CA. Once you do that, do not forget to remove the above section from Nextcloud config file.

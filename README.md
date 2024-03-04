# ![](screenshots/icon.png) Nextcloud ONLYOFFICE integration app

This app enables users to edit office documents from [Nextcloud](https://nextcloud.com) using ONLYOFFICE Docs packaged as Document Server - [Community or Enterprise Edition](#onlyoffice-docs-editions).

## Features

The app allows to:

* Create and edit text documents, spreadsheets, and presentations.
* Share files to other users.
* Protect documents with watermarks.
* Co-edit documents in real-time: use two co-editing modes (Fast and Strict), Track Changes, comments, and built-in chat. Co-editing is also available between several federated Nextcloud instances connected to one Document Server.

Supported formats:

* For editing: DOCM, DOCX, DOCXF, DOTM, DOTX, EPUB, FB2, HTML, ODT, OTT, RTF, TXT, CSV, ODS, OTS, XLSM, XLSX, XLTM, XLTX, ODP, OTP, POTM, POTX, PPSM, PPSX, PPTM, PPTX.
* For viewing only: DJVU, DOC, DOT, FODT, HTM, MHT, MHTML, OFORM, PDF, STW, SXW, WPS, WPT, XML, XPS, ET, ETT, FODS, SXC, XLS, XLSB, XLT, DPS, DPT, FODP, POT, PPS, PPT, SXI.

## Installing ONLYOFFICE Docs

You will need an instance of ONLYOFFICE Docs (Document Server) that is resolvable and connectable both from Nextcloud and any end clients. ONLYOFFICE Document Server must also be able to POST to Nextcloud directly.

ONLYOFFICE Document Server and Nextcloud can be installed either on different computers, or on the same machine. If you use one machine, set up a custom port for Document Server as by default both ONLYOFFICE Document Server and Nextcloud work on port 80.

You can install free Community version of ONLYOFFICE Docs or scalable Enterprise Edition with pro features.

To install free Community version, use [Docker](https://github.com/onlyoffice/Docker-DocumentServer) (recommended) or follow [these instructions](https://helpcenter.onlyoffice.com/installation/docs-community-install-ubuntu.aspx) for Debian, Ubuntu, or derivatives.  

To install Enterprise Edition, follow instructions [here](https://helpcenter.onlyoffice.com/installation/docs-enterprise-index.aspx).

Community Edition vs Enterprise Edition comparison can be found [here](#onlyoffice-docs-editions).

To use ONLYOFFICE behind a proxy, please refer to [this article](https://helpcenter.onlyoffice.com/installation/docs-community-proxy.aspx).

You can also use our **[Docker installation](https://github.com/ONLYOFFICE/docker-onlyoffice-nextcloud)** to install pre-configured Document Server (free version) and Nextcloud with a couple of commands.

## Installing Nextcloud ONLYOFFICE integration app

The Nextcloud administrator can install the integration app from the in-built application market.
For that go to the user name and select **Apps**.

After that find **ONLYOFFICE** in the list of available applications and install it.

If the server with the Nextcloud installed does not have an Internet access, or if you need it for some other reason, the administrator can install the application manually.
To start using ONLYOFFICE Document Server with Nextcloud, the following steps must be performed:

1. Go to the Nextcloud server _apps/_ directory (or some other directory [used](https://docs.nextcloud.com/server/latest/admin_manual/apps_management.html#using-custom-app-directories)):
    ```bash
    cd apps/
    ```
2. Get the Nextcloud ONLYOFFICE integration app.
There are several ways to do that:

    a. Download the latest signed version from the official store for [Nextcloud](https://apps.nextcloud.com/apps/onlyoffice).

    b. Or you can download the latest signed version from the application [release page](https://github.com/ONLYOFFICE/onlyoffice-nextcloud/releases) on GitHub.

    c. Or you can clone the application source code and compile it yourself: 
    ```bash
    git clone https://github.com/ONLYOFFICE/onlyoffice-nextcloud.git onlyoffice
    cd onlyoffice
    git submodule update --init --recursive
    ```
3. Build webpack:
    ```bash
    npm install
    npm run build
    ```
4. Change the owner to update the application right from Nextcloud web interface:
    ```bash
    chown -R www-data:www-data onlyoffice
    ```
5. In Nextcloud open the `~/settings/apps/disabled` page with _Not enabled_ apps by administrator and click _Enable_ for the **ONLYOFFICE** application.

## Configuring Nextcloud ONLYOFFICE integration app

In Nextcloud open the `~/settings/admin/onlyoffice` page with administrative settings for **ONLYOFFICE** section.
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

Starting from version 7.2, JWT is enabled by default and the secret key is generated automatically to restrict the access to ONLYOFFICE Docs and for security reasons and data integrity. 
Specify your own **Secret key** in the Nextcloud administrative configuration. 
In the ONLYOFFICE Docs [config file](https://api.onlyoffice.com/editors/signature/), specify the same secret key and enable the validation.

Enable or disable the _Open file in the same tab_ setting.

The **Open in ONLYOFFICE** action will be added to the file context menu.
You can specify this action as default and it will be used when the file name is clicked for the selected file types.

## Checking the connection 

You can check the connection to ONLYOFFICE Document Server by using the following occ command:

`occ onlyoffice:documentserver --check`

You will see a text either with information about the successful connection or the cause of the error. 

## How it works

The ONLYOFFICE integration follows the API documented here https://api.onlyoffice.com/editors/basic:

* When creating a new file, the user navigates to a document folder within Nextcloud and clicks the **Document**, **Spreadsheet** or **Presentation** item in the _new_ (+) menu.

* The browser invokes the `create` method in the `/lib/Controller/EditorController.php` controller.
This method adds the copy of the file from the assets folder to the folder the user is currently in.

* Or, when opening an existing file, the user navigates to it within Nextcloud and selects the **Open in ONLYOFFICE** menu option.

* A new browser tab is opened and the `index` method of the `/lib/Controller/EditorController.php` controller is invoked.

* The app prepares a JSON object with the following properties:

  * **url** - the URL that ONLYOFFICE Document Server uses to download the document;
  * **callbackUrl** - the URL that ONLYOFFICE Document Server informs about status of the document editing;
  * **documentServerUrl** - the URL that the client needs to respond to ONLYOFFICE Document Server (can be set at the administrative settings page);
  * **key** - the etag to instruct ONLYOFFICE Document Server whether to download the document again or not;

* Nextcloud takes this object and constructs a page from `templates/editor.php` template, filling in all of those values so that the client browser can load up the editor.

* The client browser makes a request for the javascript library from ONLYOFFICE Document Server and sends ONLYOFFICE Document Server the DocEditor configuration with the above properties.

* Then ONLYOFFICE Document Server downloads the document from Nextcloud and the user begins editing.

* ONLYOFFICE Document Server sends a POST request to the _callbackUrl_ to inform Nextcloud that a user is editing the document.

* When all users and client browsers are done with editing, they close the editing window.

* After [10 seconds](https://api.onlyoffice.com/editors/save#savedelay) of inactivity, ONLYOFFICE Document Server sends a POST to the _callbackUrl_  letting Nextcloud know that the clients have finished editing the document and closed it.

* Nextcloud downloads the new version of the document, replacing the old one.


## Known issues

* Adding the storage using the **External storages** app has issues with the co-editing in some cases.
If the connection is made using the same authorization keys (the _Username and password_ or _Global credentials_ authentication type is selected), then the co-editing is available for the users.
If different authorization keys are used (_Log-in credentials, save in database_ or _User entered, store in database_ authentication options), the co-editing is not available.
When the _Log-in credentials, save in session_ authentication type is used, the files cannot be opened in the editor.

* If you are using a self-signed certificate for your **Document Server**, Nextcloud will not validate such a certificate and will not allow connection to/from **Document Server**. This issue can be solved in two ways. 

    You can check the '**Disable certificate verification (insecure)**' box on the ONLYOFFICE administration page, Server settings section, within your Nextcloud.

    Another option is to change the Nextcloud config file manually. Locate the Nextcloud config file (_/nextcloud/config/config.php_) and open it. Insert the following section to it:

    ```php
    'onlyoffice' => array (
        'verify_peer_off' => true
    )
    ```
    This will disable the certificate verification and allow Nextcloud to establish connection with **Document Server**.

    Please remember that this is a temporary insecure solution and we strongly recommend that you replace the certificate with the one issued by some CA. Once you do that, do not forget to uncheck the corresponding setting box or remove the above section from the Nextcloud config file.  

* If the editors don't open or save documents after a period of proper functioning, the reason can be a problem in changing network settings or disabling any relevant services, or issues with the SSL certificate.
    
    To solve this, we added an asynchronous background task which runs on the server to check availability of the editors. It allows testing the connection between your **Nextcloud instance** and **ONLYOFFICE Document Server**, namely availability of server addresses and the validity of the JWT secret are being checked.
 
    If any issue is detected, the ONLYOFFICE integration connector (consequently, the ability to create and open files) will be disabled. As a Nextcloud admin, you will get the corresponding notification. 

    This option allows you to avoid issues when the server settings become incorrect and require changes.

    By default, this background task runs once a day. If necessary, you can change the frequency. To do so, open the Nextcloud config file (_/nextcloud/config/config.php_). Insert the following section and enter the required value in minutes:

    ```php
    'onlyoffice' => array (
        'editors_check_interval' => 3624
    )
    ```
    To disable this check running, enter 0 value. 

* When accessing a document without download permission, file printing and using the system clipboard are not available. Copying and pasting within the editor is available via buttons in the editor toolbar and in the context menu.

## ONLYOFFICE Docs editions

ONLYOFFICE offers different versions of its online document editors that can be deployed on your own servers.

* Community Edition (`onlyoffice-documentserver` package)
* Enterprise Edition (`onlyoffice-documentserver-ee` package)

The table below will help you to make the right choice.

| Pricing and licensing | Community Edition | Enterprise Edition |
| ------------- | ------------- | ------------- |
| | [Get it now](https://www.onlyoffice.com/download-docs.aspx?utm_source=github&utm_medium=cpc&utm_campaign=GitHubNextcloud#docs-community)  | [Start Free Trial](https://www.onlyoffice.com/download-docs.aspx?utm_source=github&utm_medium=cpc&utm_campaign=GitHubNextcloud#docs-enterprise)  |
| Cost  | FREE  | [Go to the pricing page](https://www.onlyoffice.com/docs-enterprise-prices.aspx?utm_source=github&utm_medium=cpc&utm_campaign=GitHubNextcloud)  |
| Simultaneous connections | up to 20 maximum  | As in chosen pricing plan |
| Number of users | up to 20 recommended | As in chosen pricing plan |
| License | GNU AGPL v.3 | Proprietary |
| **Support** | **Community Edition** | **Enterprise Edition** |
| Documentation | [Help Center](https://helpcenter.onlyoffice.com/installation/docs-community-index.aspx) | [Help Center](https://helpcenter.onlyoffice.com/installation/docs-enterprise-index.aspx) |
| Standard support | [GitHub](https://github.com/ONLYOFFICE/DocumentServer/issues) or paid | One year support included |
| Premium support | [Contact us](mailto:sales@onlyoffice.com) | [Contact us](mailto:sales@onlyoffice.com) |
| **Services** | **Community Edition** | **Enterprise Edition** |
| Conversion Service                | + | + |
| Document Builder Service          | + | + |
| **Interface** | **Community Edition** | **Enterprise Edition** |
| Tabbed interface                  | + | + |
| Dark theme                        | + | + |
| 125%, 150%, 175%, 200% scaling    | + | + |
| White Label                       | - | - |
| Integrated test example (node.js) | + | + |
| Mobile web editors                | - | +* |
| **Plugins & Macros** | **Community Edition** | **Enterprise Edition** |
| Plugins                           | + | + |
| Macros                            | + | + |
| **Collaborative capabilities** | **Community Edition** | **Enterprise Edition** |
| Two co-editing modes              | + | + |
| Comments                          | + | + |
| Built-in chat                     | + | + |
| Review and tracking changes       | + | + |
| Display modes of tracking changes | + | + |
| Version history                   | + | + |
| **Document Editor features** | **Community Edition** | **Enterprise Edition** |
| Font and paragraph formatting   | + | + |
| Object insertion                | + | + |
| Adding Content control          | + | + | 
| Editing Content control         | + | + | 
| Layout tools                    | + | + |
| Table of contents               | + | + |
| Navigation panel                | + | + |
| Mail Merge                      | + | + |
| Comparing Documents             | + | + |
| **Spreadsheet Editor features** | **Community Edition** | **Enterprise Edition** |
| Font and paragraph formatting   | + | + |
| Object insertion                | + | + |
| Functions, formulas, equations  | + | + |
| Table templates                 | + | + |
| Pivot tables                    | + | + |
| Data validation                 | + | + |
| Conditional formatting          | + | + |
| Sparklines                      | + | + |
| Sheet Views                     | + | + |
| **Presentation Editor features** | **Community Edition** | **Enterprise Edition** |
| Font and paragraph formatting   | + | + |
| Object insertion                | + | + |
| Transitions                     | + | + |
| Animations                      | + | + |
| Presenter mode                  | + | + |
| Notes                           | + | + |
| **Form creator features** | **Community Edition** | **Enterprise Edition** |
| Adding form fields	          | + | + |
| Form preview                    | + | + |
| Saving as PDF	                  | + | + |
| **Working with PDF**      | **Community Edition** | **Enterprise Edition** |
| Text annotations (highlight, underline, cross out) | + | + |
| Comments                        | + | + |
| Freehand drawings               | + | + |
| Form filling                    | + | + |
| | [Get it now](https://www.onlyoffice.com/download-docs.aspx?utm_source=github&utm_medium=cpc&utm_campaign=GitHubNextcloud#docs-community)  | [Start Free Trial](https://www.onlyoffice.com/download-docs.aspx?utm_source=github&utm_medium=cpc&utm_campaign=GitHubNextcloud#docs-enterprise)  |

\* If supported by DMS.

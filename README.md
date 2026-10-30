[![Joomlashack](docs/images/joomlashack-logo.png)](https://www.joomlashack.com)

Joomlashack Installer
=====================

ShackInstaller is the shared library behind the install scripts of Joomlashack extensions. It handles
the work every extension's installer needs: checking platform requirements, installing and uninstalling
bundled extensions, removing obsolete files, publishing and ordering plugins, handling Free/Pro licensing,
and showing the post-install welcome screen. It runs on Joomla 3.10 through 6.

This is not a standalone extension. Each extension bundles its own copy of the library and its
install script extends `Alledia\Installer\AbstractScript`.

Developers working on the library itself, or building an extension on it, should read the
[Developer Guide](docs/DEVELOPER.md).

## Quick start

For a component, the build copies the library into the admin folder of the package:

```
script.installer.php
mycomponent.xml              <scriptfile>script.installer.php</scriptfile>
admin/
├── language/en-GB/en-GB.lib_shackinstaller.sys.ini
├── library/Installer/       (this library)
└── views/installer/tmpl/    (welcome screen)
media/css/installer.css
```

Joomla runs `script.installer.php` from the package root on install and update. On uninstall it runs
the copy saved in `administrator/components/com_mycomponent`, where there is no `admin/` folder. The
script has to find the library in both places:

```php
<?php

use Alledia\Installer\AbstractScript;
use Joomla\CMS\Installer\InstallerAdapter;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die();

$installPath = __DIR__ . (is_dir(__DIR__ . '/admin') ? '/admin' : '');
require_once $installPath . '/library/Installer/include.php';
// phpcs:enable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps

class Com_MycomponentInstallerScript extends AbstractScript
{
    protected function customPostFlight(string $type, InstallerAdapter $parent): void
    {
        if ($type != 'uninstall') {
            // Extension-specific install/update work goes here
        }
    }
}
```

Plugins and modules keep the library in the extension root next to the script, so they only need
`require_once __DIR__ . '/library/Installer/include.php';`.

Override only the `custom*` methods (`customPreFlight`, `customInstall`, `customDiscoverInstall`,
`customUpdate`, `customPostFlight`, `customUninstall`). The public lifecycle methods Joomla calls are
`final`. See the [Developer Guide](docs/DEVELOPER.md#writing-an-install-script) for details.

## Manifest

### Custom Tags
A special `<alledia>` tag in the manifest controls various parts of the installation.
Boolean attribute values can be written as true/false or 1/0.
```xml
    <alledia>
        <element publish="bool" ordering="string">elementname</element>
        <namespace>ExtensionName</namespace>
        <name>CustomName</name>
        <license>free | pro</license>
        <targetplatform>.*</targetplatform>
        <mysqlminimum>.*</mysqlminimum>
        <phpminimum>.*</phpminimum>
        <previousminimum>.*</previousminimum>

        <relatedExtensions downgrade="bool"
                           publish="bool"
                           uninstall="bool">
            <extension type="string"
                       element="string"
                       group="string"
                       downgrade="bool"
                       publish="bool"
                       ordering="string"
                       uninstall="bool">
                ExtensionFolder
            </extension>
        </relatedExtensions>

        <obsolete>
            <preflight>
                <!-- Same children as <obsolete>, removed during preflight -->
                <folder>/components/com_mycomponent/conflictingfolder</folder>
            </preflight>

            <extension
                type="string"
                group="string"
                element="fullName"/>

            <folder>/components/com_mycomponent/oldfolder</folder>

            <file>/components/com_mycomponent/oldfile.php</file>
            <file>/administrator/components/com_mycomponent/oldfile.php</file>
        </obsolete>
    </alledia>
```

#### Element tag

The Joomla extension element, e.g. `com_mycomponent` or `mymodule`.

For plugins, two optional attributes apply to the plugin itself on a new install:

|Attribute|Values|
|---------|------|
|publish|true &#124; false -- enable the plugin after install|
|ordering|Same values as the `ordering` attribute of `<extension>` below|

#### Namespace and name tags

`namespace` is the extension's PHP namespace under `Alledia\`. It is also used to find the installed
extension when checking its license and paths. `name` is the human-readable name used in messages.
It defaults to `namespace` when omitted.

#### License tag

`free` or `pro`. On a free install the installer deletes the extension's `library/Pro` folder.
On a pro install the welcome screen shows the license key form.

#### Version constraints

`targetplatform`, `mysqlminimum` and `phpminimum` are checked on install and update. `previousminimum`
is checked on update only, against the version of the extension already installed. Each value is a
minimum version. A `*` is read as `0` (so `4.*` means 4.0 or higher), and `.*` accepts any version.
If any check fails, the installation is cancelled with a warning. `mysqlminimum` is skipped on
non-MySQL servers, and any version of MariaDB passes it.

#### relatedExtensions tag

Related extensions are installed as part of a main package. The content of each `<extension>` tag is
the name of a folder in the package's `extensions/` folder. The attributes `publish`, `downgrade`
and `uninstall` on `<relatedExtensions>` set the defaults for the enclosed `<extension>` items. All
three default to false if not set.

- Without `downgrade`, an extension whose installed version is newer than the bundled one is skipped.
- Search and finder plugins are skipped (and uninstalled if present) when `com_search` or `com_finder`
  is not installed.
- The ShackFramework library and the License Key Manager plugin are never uninstalled, even when
  `uninstall` is set, because other extensions may depend on them.

#### Obsolete items

Obsolete items are uninstalled or deleted after the main extension is installed and before any related
extension is installed. Items inside `<obsolete><preflight>` are removed earlier, during preflight. Use
this for leftovers that would break the install itself.

There are 3 types of obsolete items: extension, file and folder.
For files and folders, use paths relative to the site root.

#### Extension tag

The following attributes are recognized in the `<extension>` tag.
Attributes available under `<relatedExtensions>` are marked 'R'. Those available under `<obsolete>`
are marked 'O'.

|Attribute|Valid|Values|
|---------|-----|------|
|type|RO|plugin, module, component, etc.|
|group|RO|For plugins, the plugin folder. Otherwise ignored.|
|element|RO|Extension element name. The `com_`/`mod_` prefix is optional.|
|downgrade|R|true &#124; false -- okay to downgrade on a reinstall|
|publish|R|true &#124; false -- used for plugins only|
|ordering|R|# &#124; first &#124; last &#124; before:pluginelement &#124; after:pluginelement<br>Plugins only. Applies only to new installations|
|uninstall|R|true &#124; false -- uninstall during uninstall of the current extension|

## Requirements

- Joomla 3.10, 4.x, 5.x, 6.x
- PHP 7.4+ (Joomla 6 itself requires PHP 8.3+)

## License

[GNU General Public License v2 or later](http://www.gnu.org/copyleft/gpl.html)

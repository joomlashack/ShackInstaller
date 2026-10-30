# ShackInstaller Developer Guide

This guide is for two groups:

- **Extension developers** who write an install script on top of ShackInstaller.
- **Library maintainers** who change ShackInstaller itself.

For the `<alledia>` manifest schema, see the [README](../README.md#manifest).

- [Repository layout](#repository-layout)
- [How an extension uses the library](#how-an-extension-uses-the-library)
- [Writing an install script](#writing-an-install-script)
- [Lifecycle](#lifecycle)
- [Helper reference](#helper-reference)
- [The welcome screen](#the-welcome-screen)
- [Multiple copies and compatibility](#multiple-copies-and-compatibility)
- [Working on the library](#working-on-the-library)
- [Debugging](#debugging)

## Repository layout

```
src/
├── language/en-GB/
│   ├── en-GB.lib_shackinstaller.sys.ini    Current strings (LIB_SHACKINSTALLER_*)
│   └── en-GB.lib_allediainstaller.sys.ini  Legacy strings, kept for older extensions
├── library/Installer/
│   ├── include.php          Entry point: constants, autoloader, error handler
│   ├── AbstractScript.php   Base install script class
│   ├── AutoLoader.php       PSR-4 and camelCase autoloader
│   ├── Loader.php           Safe include helper (logs missing files)
│   ├── TraitFramework.php   Cross-version DB/model/table/form helpers
│   └── Extension/
│       ├── Generic.php      Wrapper around an installed extension
│       └── Licensed.php     Generic + Free/Pro detection and library paths
├── media/css/installer.css  Welcome screen styles
└── views/installer/tmpl/    Welcome screen templates
    ├── default.php
    ├── default_info.php     Release info, related-extension results, license notice
    └── default_license.php  Pro license key form
```

Everything in the library lives in the `Alledia\Installer` namespace.

## How an extension uses the library

An extension copies the contents of `src/` into its own package. The build (ShackBuilder) normally does
this. The library expects these pieces to end up in specific places:

| Piece | Where it must go | Why |
|-------|------------------|-----|
| `library/Installer/` | Components: `admin/library/Installer/`. Plugins and modules: `library/Installer/` in the extension root. | The install script requires `include.php` from here |
| `language/` | Two levels above `library/Installer/` (a sibling of `library/`) | `initProperties()` loads `lib_shackinstaller.sys` from `__DIR__ . '/../..'` |
| `views/installer/` | The **installed** extension's path (e.g. `administrator/components/com_foo/views/installer`) | `displayWelcome()` includes the template from the installed location, not the package |
| `media/css/installer.css` | `css/installer.css` inside the extension's `<media>` destination | Inlined by `displayWelcome()` |

Extensions built on FOF can put the view in `alledia_views/installer/` instead. FOF scans `views/` and
would otherwise expose an "Installer" view in the admin.

The extension's own language file (`com_foo`, `plg_system_foo`, etc.) is also loaded. The installer
looks in the package source first, then in `administrator/language`.

## Writing an install script

The examples below follow OSDownloads (`com_osdownloads`), which uses all the usual patterns.

### Finding the library

The manifest names the script with `<scriptfile>script.installer.php</scriptfile>`. Joomla runs it
from two places:

- **Install and update:** from the unpacked package. For a component, the library is under
  `admin/library/Installer/`.
- **Uninstall:** from the copy Joomla saved in `administrator/components/com_foo/`. That folder is the
  package's `admin/` folder, so the library is at `library/Installer/`.

A component script checks for the `admin/` folder to handle both. Plugins and modules are installed
into a single folder that holds both the script and the library, so they can require
`__DIR__ . '/library/Installer/include.php'` directly.

### A simple script

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
    protected function customPreFlight(string $type, InstallerAdapter $parent): bool
    {
        // Return false to cancel the install
        return true;
    }

    protected function customPostFlight(string $type, InstallerAdapter $parent): void
    {
        // customPostFlight also runs on uninstall
        if ($type != 'uninstall') {
            if ($type == 'update') {
                $this->addColumns([
                    '#__mycomponent_items.featured' => 'TINYINT NOT NULL DEFAULT 0',
                ]);
            }
        }
    }
}
```

The class name follows Joomla's `<Prefix>_<element>InstallerScript` convention for components
(`Com_OsdownloadsInstallerScript`). Plugins use `PlgSystemFooInstallerScript`. The `phpcs:disable`
lines cover the side effects before the class and the non-namespaced, non-CamelCase class name that
Joomla requires.

### Sharing install code between Free and Pro

If the Free and Pro editions of an extension share install logic, put it in a namespaced base class
inside the extension's own library, not in the root script. OSDownloads uses
`admin/library/Free/Installer/AbstractScript.php`:

```php
<?php

namespace Alledia\OSDownloads\Free\Installer;

use Alledia\Installer\AbstractScript as AllediaAbstractScript;
use Joomla\CMS\Installer\InstallerAdapter;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die();

require_once realpath(__DIR__ . '/../../Installer/include.php');
// phpcs:enable PSR1.Files.SideEffects

class AbstractScript extends AllediaAbstractScript
{
    protected function customPostFlight(string $type, InstallerAdapter $parent): void
    {
        if ($type != 'uninstall') {
            // Work shared by Free and Pro
        }
    }
}
```

The root `script.installer.php` in each edition then requires that file instead of `include.php`, and
extends it:

```php
<?php

use Alledia\OSDownloads\Free\Installer\AbstractScript;
use Joomla\CMS\Installer\InstallerAdapter;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die();

$installPath = __DIR__ . (is_dir(__DIR__ . '/admin') ? '/admin' : '');
require_once $installPath . '/library/Free/Installer/AbstractScript.php';
// phpcs:enable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps

class Com_OsdownloadsInstallerScript extends AbstractScript
{
    protected function customPostFlight(string $type, InstallerAdapter $parent): void
    {
        parent::customPostFlight($type, $parent);

        if ($type != 'uninstall') {
            // Pro-only work
        }
    }
}
```

The Free edition's script can be an empty class body. Remember to call the parent hook when the Pro
script overrides one.

Rules:

- **Only override `custom*` methods.** `preFlight`, `install`, `discover_install`, `update`,
  `postFlight` and `uninstall` are `final`. Almost all helpers are `final protected`.
- **Throw instead of returning false for errors.** Each lifecycle method wraps its `custom*` call in
  `try/catch`. A thrown `Throwable` is shown as an error message and sets `cancelInstallation`, which
  makes `postFlight` skip its work.
- **Return `false` from a `bool` hook to cancel.** `customPreFlight` is the usual place for extra
  requirement checks.
- `$type` is one of `TYPE_INSTALL`, `TYPE_DISCOVER_INSTALL`, `TYPE_UPDATE` or `TYPE_UNINSTALL`.

The `<alledia><namespace>` value is lowercased and used as the element to look up the installed
extension for licensing, paths and the welcome screen. It must match the element (without the
`com_`/`mod_` prefix), ignoring case.

### Useful properties

| Property | Contents |
|----------|----------|
| `$this->manifest` | Incoming manifest (`SimpleXMLElement`) |
| `$this->previousManifest` | Manifest of the currently installed version, if any (updates) |
| `$this->schemaVersion` | Current `#__schemas` version for this extension, or null on a new install |
| `$this->type`, `$this->group` | Extension type and plugin group from the manifest |
| `$this->installer` | The Joomla `Installer` running this install |
| `$this->dbo` | Database driver (works on J3 through J6) |
| `$this->app` | The application |
| `$this->mediaFolder` | Absolute path to the extension's media destination |
| `$this->debug` | Set to `true` to emit debug messages |

## Lifecycle

Joomla calls the public methods in this order. Indented items are what ShackInstaller does around
your hook.

**Constructor**
- Loads the installer language strings and runs `checkInheritance()` (see
  [Multiple copies](#multiple-copies-and-compatibility)).
- Sets up `dbo`, `installer`, `manifest`, `previousManifest`, `schemaVersion`, `type`, `group` and
  `mediaFolder`, and loads the extension's language file.

**`preFlight($type)`**
- Returns false right away if the install was already cancelled.
- On update: clears this extension's update servers, so a changed update URL in the new manifest
  takes effect.
- On install/update: checks `targetplatform`, `mysqlminimum` and `phpminimum`. On update, also checks
  `previousminimum`.
- → `customPreFlight()`
- Removes `<obsolete><preflight>` items (not on uninstall).

**`install()` / `update()` / `discover_install()`**
- → `customInstall()` / `customUpdate()`. `discover_install` runs `install()` and then
  `customDiscoverInstall()`.

**`uninstall()`**
- Uninstalls related extensions marked `uninstall`.
- → `customUninstall()`

**`postFlight($type)`**
- If cancelled, shows "Install process canceled" and stops.
- Except on uninstall:
  - Removes `<obsolete>` items, plus any legacy `lib_allediainstaller` language files.
  - Installs or updates `relatedExtensions` from the package's `extensions/` folder.
  - Tags the extension (and the ShackFramework library) with `custom_data.author = Joomlashack`.
  - For plugins on a new install: applies `<element publish>` and `<element ordering>`.
  - For free licenses: deletes `library/Pro`.
- → `customPostFlight()` (runs on uninstall too, since Joomla 4 calls `postFlight` there)
- Except on uninstall: renders the welcome screen.

## Helper reference

All of these are `final protected` methods on `AbstractScript`.

### Extensions

| Method | Purpose |
|--------|---------|
| `findExtension($type, $element, $group)` | Load the `#__extensions` row as a `Table\Extension`, or null. Adds `com_`/`mod_` prefixes when missing. |
| `findThisExtension()` | `findExtension()` for the extension being installed |
| `uninstallExtension($type, $element, $group)` | Uninstall an extension if present and report the result |
| `setExtensionState(['plugin.system.foo', 'module.mod_bar'], $state)` | Enable (1) or disable (0) extensions. Returns their previous states. |
| `setPluginOrder($extension, $order)` | Reorder a plugin: `first`, `last`, a number ≤ 1, `before:element`, `after:element` |
| `getExtensionId($type, $element, $group)` | Extension ID, or 0 |
| `getExtensionPath($type, $element, $group)` | Absolute install path |
| `getManifestPath($type, $element, $group)` | Path of the installed manifest |
| `getInfoFromManifest($path)` | Top-level manifest attributes and scalar children as a `Registry` |
| `getFullElement($type, $element, $group)` | e.g. `com_foo`, `plg_system_foo`. Defaults to this extension. |
| `getExtensionInfoFromElement('plg_system_foo')` | Split a full element into type/group/name/prefix/namespace |
| `getLicense()` | `Extension\Licensed` for this extension |
| `getName()` | `<alledia><name>`, falling back to `<namespace>` |
| `clearObsolete($xml = null)` | Process an `<obsolete>`-style node (defaults to the manifest's) |
| `clearUpdateServers()` | Remove this extension's update sites |
| `fixMenus()` | For components: point existing menu items at the new extension ID, and delete the admin menu when `<menu hidden="true">` |
| `parseConditionalExpression($expr)` | `'true'`/`'1'`, or `'com_foo=*'` (true if that extension is installed) |

### Versions

| Method | Purpose |
|--------|---------|
| `validateTargetVersion($actual, $target, $compare = 'ge')` | Version compare with `*` → `0`. `.*` always passes. |
| `validatePreviousVersion($target, $compare = 'ge')` | Compare against the installed version. Passes when there is no previous manifest. |

### Database schema

Table names may use the `#__` prefix. Column, index and constraint IDs are written as `table.name`.

| Method | Purpose |
|--------|---------|
| `getTables($force = false)` / `findTable($table)` | List tables, or check that one exists |
| `findColumn('#__t.col')` | Column definition, or null |
| `addColumns(['#__t.col' => 'INT NOT NULL DEFAULT 0'])` | Add columns that don't exist yet |
| `dropColumns(['#__t.col'])` | Drop columns |
| `findIndex('#__t.idx')` | Index rows, or an empty array |
| `addIndexes(['#__t.idx' => ['col1', 'col2'], '#__t.idx2.UNIQUE' => ['col']])` | Add missing indexes. An optional third segment sets the type. |
| `dropIndexes(['#__t.idx'])` | Drop indexes that exist |
| `findConstraint('#__t.fk')` / `dropConstraints(['#__t.fk'])` | Find or drop foreign keys |
| `executeQuery($schemaVersion, $queries)` | Run queries only when updating from a schema version older than `$schemaVersion`. Returns `true` or the `Throwable`; it does not throw. |
| `clearDBUpdateFiles($basePath)` | Delete `sql/updates/*.sql` and leave the newest one empty. This works around Joomla 4's database checker. |

Column, index and constraint lookups are cached per table for the life of the script.

### Framework helpers (`TraitFramework`)

These are copies of ShackFramework helpers, so they work before the framework is installed:
`getDatabase()`, `getJoomlaModel()`, `getJoomlaTable()`, `getJoomlaForm()`. Each one uses the correct
API for Joomla 3 or 4+.

### Messages

| Method | Purpose |
|--------|---------|
| `sendMessage($text, $type = 'message')` | Queue a message (admin only) |
| `sendErrorMessage($throwable, $cancel = true)` | Show an error with its location. Cancels the install unless `$cancel` is false. |
| `sendDebugMessage($text)` | Show a message only when `$this->debug` is true |
| `addStyle($paths)` | Inline CSS files. Useful in postFlight, when the document head can't be changed. |

### Extension wrappers

`Extension\Generic($namespace, $type, $folder = '', $basePath = JPATH_SITE)` loads an installed
extension's DB row (`id`, `name`, `enabled`, `params`), manifest and `config.xml`. It also provides
path helpers, `getUpdateURL()`/`setUpdateURL()` and `storeParams()`.

`Extension\Licensed` adds `isPro()`/`isFree()` (from `<alledia><license>`), `getLibraryPath()`
(`<extension>/library`), `getProLibraryPath()` (`library/Pro`) and `loadLibrary()`. `loadLibrary()`
registers `Alledia\<Namespace>` with the autoloader, and throws on a Pro license if `library/Pro`
is missing.

## The welcome screen

`displayWelcome()` runs at the end of `postFlight` for install and update. It runs only in the
administrator (`$this->outputAllowed`), so CLI and site-side installs print nothing.

It sets up the following and then includes `views/installer/tmpl/default.php` from the installed
extension:

| Variable | Contents |
|----------|----------|
| `$this->welcomeMessage` | "Thanks for installing/updating {name}[ Pro]!" |
| `$this->mediaURL` | URL of `media/<full element>` |
| `$this->footer` | Rendered `customfooter` field, if the manifest or `config.xml` declares one and `form/fields/customfooter.php` exists |
| `$this->licenseKey`, `$this->isLicensesManagerInstalled` | Pro only: the current key from the License Key Manager plugin |
| `$this->relatedExtensionFeedback` | Per related extension: `name`, `message`, and optionally `publish`/`ordering` |
| `$license` | The `Licensed` instance (local variable in scope of the template) |

To add extension-specific content, create `default_custom.php` next to `default.php` in the
extension. The template includes it automatically.

## Multiple copies and compatibility

Several installed Joomlashack extensions can each ship a copy of this library, and one request (for
example, a package install) can load more than one. PHP can only define each class once, so:

- `include.php` guards everything with `defined()`. The **first** copy loaded sets
  `SHACK_INSTALLER_BASE`, `SHACK_INSTALLER_VERSION` and `SHACK_INSTALLER_COMPATIBLE`, and installs the
  error handler.
- Every copy registers its own folder with the autoloader, prepended. A class that has not loaded yet
  comes from the most recently included copy. A class that is already loaded stays as it is.
- `checkInheritance()` finds the file the loaded `AbstractScript` came from. If that file is not in
  `SHACK_INSTALLER_BASE` and its `VERSION` is older than `SHACK_INSTALLER_COMPATIBLE`, the install is
  cancelled. The user sees which extension is in the way (`LIB_SHACKINSTALLER_ABORT_INSTALL`).

What this means for maintainers:

- A subclass can end up running against an **older or newer** `AbstractScript` than the one it was
  built with. Treat protected and final method signatures as public API.
- Adding methods and optional parameters is safe. Removing or renaming methods, changing a signature,
  or changing behavior that subclasses rely on is a **breaking change**. Bump
  `SHACK_INSTALLER_COMPATIBLE` to the new version when you make one.

## Working on the library

### Supported environments

Code must run on Joomla 3.10, 4.x, 5.x and 6.x at the same time, and on PHP 7.4 (the library's
minimum) up to the newest PHP that Joomla 6 supports.

- Check `Version::MAJOR_VERSION` or use `is_callable()` instead of calling version-specific APIs
  directly. See `getNewInstaller()`, `getDispatcher()` and `TraitFramework` for examples.
- Don't use syntax newer than PHP 7.4 (no constructor promotion, `match`, union types, enums,
  `readonly`, etc.).
- Don't use anything deprecated in PHP 8.x either: make nullable parameters explicit (`?string $x = null`)
  and don't use dynamic properties on classes you define.
- Joomla 6 no longer wires the database into a new `Installer` automatically. Always get one through
  `getNewInstaller()`, never `new Installer()`.

### Legacy Joomla APIs still in use

These calls work on Joomla 3 through 6 but are deprecated in Joomla 4.3+ and marked for removal in
Joomla 7. They are kept because they are the only form that also runs on Joomla 3. Supporting Joomla 7
will mean replacing them, most likely behind a `Version::MAJOR_VERSION` branch.

| Call | Where | Joomla 4+ replacement |
|------|-------|-----------------------|
| `Factory::getDbo()` | `Extension\Generic` | `Factory::getContainer()->get(DatabaseInterface::class)` (see `TraitFramework::getDatabase()`) |
| `Factory::getLanguage()` | `AbstractScript::initProperties()` | `Factory::getApplication()->getLanguage()` |
| `$db->getQuery(true)` | Throughout | `$db->createQuery()` (Joomla 5+ only) |
| `Table::getInstance()` | `findExtension()`, `fixMenus()`, `getJoomlaTable()` (J3 branch) | `new Extension($db)` or the component's MVC factory |
| `BaseDatabaseModel::addIncludePath()` / `getInstance()` | `setPluginOrder()` | `bootComponent('com_plugins')->getMVCFactory()->createModel('Plugin', 'Administrator')` |
| `Table::getError()` | `findExtension()` | Catch exceptions instead |

The deprecated Joomla methods raise `E_USER_DEPRECATED`. The error handler set in `include.php`
ignores that level, so they don't show up as installer warnings.

### Code style

PSR-12, checked with phpcs. Use `// phpcs:disable <Sniff>` / `// phpcs:enable` around code that
Joomla requires in another form. Examples are the `discover_install` method name and the side effects
in `include.php` and the top of `AbstractScript.php`. Every PHP file starts with the GPL header and
`defined('_JEXEC') or die();`.

### Language strings

Add new strings to `src/language/en-GB/en-GB.lib_shackinstaller.sys.ini` with the
`LIB_SHACKINSTALLER_` prefix, in alphabetical order. Don't add to the `allediainstaller` file, which is
legacy.

### Building

There is no composer, test suite or lint config in this repository. Packaging uses Phing and the
external ShackBuilder project:

1. Copy `build.dist.properties` to `build.properties` (gitignored).
2. Set `builder.path` to your local ShackBuilder checkout.
3. Run `phing`. Packages are written to `packages/`.

Optional properties in `build.properties` include `builder.phplint=1` to lint all PHP after
packaging, and `project.movePackage`/`project.movePackageTo`.

### Testing changes

With no automated tests, check changes by building a client extension that bundles your working copy
and installing it on a test site. Cover each Joomla major version you touched code for. At a minimum,
run:

- a fresh install, an update over the previous release, and an uninstall
- a package where another extension already ships an older copy of the library
- Free and Pro builds, if you touched licensing or the welcome screen

### Releasing

The version is in **two** places that must match:

- `AbstractScript::VERSION` in `src/library/Installer/AbstractScript.php`
- `SHACK_INSTALLER_VERSION` in `src/library/Installer/include.php`

Update `SHACK_INSTALLER_COMPATIBLE` only for breaking changes (see above).

Commit conventions: `Release vX.Y.Z` for the release commit, then `Bump dev version` afterwards.

## Debugging

- Set `protected bool $debug = true;` in your subclass. Each lifecycle step, related-extension
  decision and schema update is then reported as a Joomla message (debug type on J4+,
  `Debug-<class>` on J3).
- `include.php` sets `AbstractScript::errorHandler` for `E_ALL` minus deprecations (and minus
  `E_NOTICE` on Joomla 4, because of a notice in core). PHP warnings then appear as admin messages.
  To change this, set `$reportErrors` to an error mask before including `include.php`, or to `0` to
  turn the handler off. This only takes effect in the first copy of the library loaded in the request.
- `Loader::includeFile()` logs missing files to `shackframework.loader.errors.php` in the Joomla log
  folder.

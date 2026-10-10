# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

ShackInstaller is a shared PHP library that Joomlashack Joomla extensions bundle to power their install scripts. It is not a standalone extension: each extension copies `src/` into its own package, and its install script requires `library/Installer/include.php` and extends `Alledia\Installer\AbstractScript`. It must run under Joomla 3, 4 and 5+ at once, so code often branches on `Version::MAJOR_VERSION` or uses `is_callable` checks instead of calling version-specific APIs directly.

## Build

This repo has no composer, test suite or lint config. Packaging uses Phing via the external ShackBuilder project:

- Copy `build.dist.properties` to `build.properties` (gitignored) and set `builder.path` to a local ShackBuilder checkout.
- Run `phing` (default target imports `${builder.path}/src/build-include.xml`). Built packages go to `packages/`.

Code follows PSR-12 / phpcs conventions. Use `// phpcs:disable` comments where Joomla needs something else, such as `discover_install` or the side effects in `include.php`.

## Versioning / releases

The version string lives in **two** places that must stay in sync:
- `AbstractScript::VERSION` in `src/library/Installer/AbstractScript.php`
- `SHACK_INSTALLER_VERSION` in `src/library/Installer/include.php`

`SHACK_INSTALLER_COMPATIBLE` (in `include.php`) is the oldest `AbstractScript::VERSION` that can run against the currently loaded base. Bump it only when you make a breaking change to the base. Commit conventions: "Bump dev version" after a release, "Release vX.Y.Z" for the release commit.

## Architecture

**Multiple copies loaded at once.** Several installed Joomlashack extensions may each ship their own copy of this library, and one install request can load more than one of them. `include.php` guards everything with `defined(...)` checks, so the first copy loaded sets `SHACK_INSTALLER_BASE`/`SHACK_INSTALLER_VERSION`. `AbstractScript::checkInheritance()` cancels the install when the subclass's script comes from a different copy whose `VERSION` is older than `SHACK_INSTALLER_COMPATIBLE`. Keep this in mind whenever you change protected/final method signatures.

**Lifecycle (template method pattern).** Joomla calls the public `final` methods `preFlight`, `install`, `discover_install`, `update`, `postFlight`, `uninstall`. Each wraps its work in try/catch (errors go to `sendErrorMessage`, which also sets `cancelInstallation`) and delegates to an overridable `custom*` hook (`customPreFlight`, `customInstall`, `customUpdate`, `customPostFlight`, `customUninstall`, `customDiscoverInstall`). Subclasses in client extensions override only the `custom*` methods. Almost all helpers are `final protected`.
- `preFlight` checks the `<alledia>` manifest constraints (`targetplatform`, `mysqlminimum`, `phpminimum`, `previousminimum`), clears update servers on update, and runs `<obsolete><preflight>` cleanup.
- `postFlight` (skipped for uninstall) clears obsolete items, installs `relatedExtensions` from the package's `extensions/` folder, publishes/reorders plugins, deletes the `library/Pro` folder for free licenses, then renders the welcome view.

**Manifest-driven behavior.** Most behavior is driven by the custom `<alledia>` block in the extension's XML manifest (see README.md for the full schema: element, namespace, license free/pro, relatedExtensions, obsolete). `$this->manifest` is the incoming manifest. `$this->previousManifest` is the installed one, available on updates.

**Supporting classes** (`src/library/Installer/`):
- `AutoLoader`: a PSR-4 and camelCase autoloader that registers `Alledia\Installer`. `Extension\Licensed::loadLibrary()` also uses it to register the client extension's `Alledia\<Namespace>` library.
- `Extension\Generic` / `Extension\Licensed`: wrappers around an installed extension (DB row, manifest, params, paths). `Licensed` adds free/pro detection and the `library/` and `library/Pro` paths.
- `TraitFramework`: copies of AllediaFramework helpers (DB, models, tables, forms) so the installer works even when the framework library isn't installed yet.

**UI.** `displayWelcome()` includes `views/installer/tmpl/default.php` (or `alledia_views/...` as a fallback for FOF-based extensions) from the *installed extension's* path, not from this repo's path. The template has access to `$this` and its properties (`welcomeMessage`, `mediaURL`, `footer`, `licenseKey`, etc.). Output only happens when running in administrator (`outputAllowed`).

**Language.** Strings use the `LIB_SHACKINSTALLER_*` prefix in `src/language/en-GB/en-GB.lib_shackinstaller.sys.ini`. The `allediainstaller` ini file is legacy, kept for older extensions.

**Debugging.** Set `$this->debug = true` in a subclass to emit `sendDebugMessage` traces as Joomla messages. `include.php` installs `AbstractScript::errorHandler` unless the including script predefines `$reportErrors`.
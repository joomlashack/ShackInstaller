[![Joomlashack](https://www.joomlashack.com/images/logo_circle_small.png)](https://www.joomlashack.com)

Joomlashack Installer
============

Common libraries for our extension installer scripts

## Manifest

### Custom Tags
A special `<alledia>` tag is recognized in the manifest for control of various aspects
of installation. Note that boolean attribute values can be specified using true/false or 1/0.

    <alledia>
        <element publish="bool">elementname</element>
        <namespace>ExtensionName</namespace>
        <name>CustomName</name>
        <license>free | pro</license>
        <targetplatform>.*</targetplatform>
        <phpminimum>.*</phpminimum>
        <previousminimum>.*</previousminimum>
        
        <relatedExtensions downgrade="bool"
                           publish="bool"
                           uninstall="bool">
            <extension type="string"
                       element="string"
                       group="string"
                       publish="bool"
                       ordering="string"
                       uninstall="bool">
                ExtensionFolder
            </extension>
        </relatedExtensions>
        
        <obsolete>
            <extension
                type="string"
                group="string"
                element="fullName"/>

            <folder>/components/com_mycomponent/oldfolder</folder>
    
            <file>/components/com_mycomponent/oldfile.php</file>
            <file>/administrator/components/com_mycomponent/oldfile.php</file>
        </obsolete>
    </alledia>

#### Element tag

This is the Joomla extension name.e.g. `com_mycomponent`.

#### relatedExtensions tag

related extensions can be installed as part of a main package. the attributes `publish`, `downgrade`
and `uninstall` can be used as defaults for the enclosed `<extension>` items. All three default to false
if not used.

#### Obsolete items

Obsolete items will be unistalled or deleted before installing any related extension.
You can set 3 types of obsolete items: extension, file and folder.
For file and folder, use relative paths to the site root.

#### Extension tag

The following attributes are recognized in the `<extension>` tag.
Atributes available under 
`<relatedExtensions>` are marked 'R'. Those available under `<obsolete>` with 'O'.

|Tag |Valid|Values|
|----|-----|------|
|type|RO|plugin, module, component, etc.|
|group|RO|For plugins, the plugin folder. Otherwise ignored.|
|element|RO|extension element name (without prefix, e.g. 'com_'|
|downgrade|R|true &#124; false -- okay to downgrade on a reinstall|
|publish|R|true &#124; false -- used for plugins only
|ordering|R|# &#124; first &#124; last &#124; before:pluginelement &#124; after:pluginelement<br>Applies only for new installations
|uninstall|R|true &#124; false -- uninstall during uninstall of the current extension|

## Requirements

Joomla 3.7+
php 5.6+

## License

[GNU General Public License v2 or later](http://www.gnu.org/copyleft/gpl.html)

[![Joomlashack](https://www.joomlashack.com/images/logo_circle_small.png)](https://www.joomlashack.com)

Joomlashack Installer
============

Common libraries for our extension installer scripts

## Manifest

### Custom Tags
A special `<alledia>` tag is recognized in the manifest for control of various aspects
of installation.

    <alledia>
        <element>elementname</element>
        <namespace>ExtensionName</namespace>
        <name>CustomName</name>
        <targetplatform>.*</targetplatform>
        <phpminimum>.*</phpminimum>
        <previousminimum>.*</previousminimum>
        
        <relatedExtensions>
            <extension
                type=""
                element=""
                group=""
                publish=""
                ordering=""
                uninstall=""
            >extensionsfolder</relatedExtensions>
        </relatedExtensions>
        
        <obsolete>
            <extension
                type="plugin"
                group="system"
                element="osoldextension"/>
    
            <file>/components/com_mycomponent/oldfile.php</file>
            <file>/administrator/components/com_mycomponent/oldfile.php</file>
    
            <folder>/components/com_mycomponent/oldfolder</folder>
        </obsolete>
    </alledia>
   
#### Extension tag

The following attributes are recognized in the `<element>` tag. Atributes available under 
`<relatedExtensions>` are marked 'R'. Those available under `<obsoslete>` with 'O'.

|Tag |Valid|Values|
|----|-----|------|
|type|RO|plugin, module, component, etc.|
|element|RO|extension element name (without prefix, e.g. 'com_'|
|group|RO|For plugins, the plugin folder. Otherwise ignored.|
|publish|R|true &#124; false -- for plugins
|ordering|R|# &#124; first &#124; &#124; last &#124; before:pluginelement &#124; after:pluginelement<br/>Applies only for new installations
|uninstall|R|true &#124; false -- uninstall during uninstall of the current extension|

#### Obsolete items

Obsolete items will be unistalled or deleted before installing any related extension.
You can set 3 types of obsolete items: extension, file and folder.
For file and folder, use relative paths to the site root.

## Requirements

Joomla 3.x
php 5.3 +

## License

[GNU General Public License v2 or later](http://www.gnu.org/copyleft/gpl.html)

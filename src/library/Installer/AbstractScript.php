<?php
/**
 * @package   AllediaInstaller
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright Copyright (C) 2016 Open Sources Training, LLC, All rights reserved
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Alledia\Installer;

defined('_JEXEC') or die();

use Alledia\Installer\Extension\Generic;
use Alledia\Installer\Extension\Licensed;
use JFormFieldCustomFooter;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Table\Extension;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use SimpleXMLElement;

require_once 'include.php';

abstract class AbstractScript
{
    public const VERSION = '1.6.25b1';

    /**
     * @var CMSApplication
     */
    protected $app = null;

    /**
     * @var \JDatabaseDriver
     */
    protected $dbo = null;

    /**
     * @var Installer
     */
    protected $installer = null;

    /**
     * @var SimpleXMLElement
     */
    protected $manifest = null;

    /**
     * @var SimpleXMLElement
     */
    protected $previousManifest = null;

    /**
     * @var string
     */
    protected $mediaFolder = null;

    /**
     * @var string
     */
    protected $element = null;

    /**
     * @var bool
     */
    protected $isLicensesManagerInstalled = false;

    /**
     * @var Licensed
     */
    protected $license = null;

    /**
     * @var string
     */
    protected $licenseKey = null;

    /**
     * @var string
     */
    protected $footer = null;

    /**
     * @var string
     */
    protected $mediaURL = null;

    /**
     * @var string[]
     */
    protected $messages = [];

    /**
     * @var string
     */
    protected $type = null;

    /**
     * @var string
     */
    protected $group = null;

    /**
     * List of tables and respective columns
     *
     * @var array
     */
    protected $columns = null;

    /**
     * List of tables and respective indexes
     *
     * @var array
     */
    protected $indexes = null;

    /**
     * List of tables
     *
     * @var array
     */
    protected $tables = null;

    /**
     * Flag to cancel the installation
     *
     * @var bool
     */
    protected $cancelInstallation = false;

    /**
     * Feedback of the install by related extension
     *
     * @var array
     */
    protected $relatedExtensionFeedback = [];

    /**
     * @var string
     */
    protected $welcomeMessage = null;

    /**
     * @param InstallerAdapter $parent
     *
     * @return void
     * @throws \Exception
     */
    public function __construct(InstallerAdapter $parent)
    {
        $this->initProperties($parent);
    }

    /**
     * @param InstallerAdapter $parent
     *
     * @return void
     * @throws \Exception
     */
    public function initProperties(InstallerAdapter $parent)
    {
        $this->app       = Factory::getApplication();
        $this->dbo       = Factory::getDbo();
        $this->installer = $parent->getParent();
        $this->manifest  = $this->installer->getManifest();
        $this->messages  = [];

        if ($media = $this->manifest->media) {
            $this->mediaFolder = JPATH_SITE . '/' . $media['folder'] . '/' . $media['destination'];
        }

        $attributes = (array)$this->manifest->attributes();
        $attributes = $attributes['@attributes'];
        $this->type = $attributes['type'];

        if ($this->type === 'plugin') {
            $this->group = $attributes['group'];
        }

        // Get the previous manifest for use in upgrades
        // @TODO: Is there a better way? This should work for components, modules and plugins.
        $targetPath   = $this->installer->getPath('extension_administrator')
            ?: $this->installer->getPath('extension_root');
        $manifestPath = $targetPath . '/' . basename($this->installer->getPath('manifest'));
        if (is_file($manifestPath)) {
            $this->previousManifest = simplexml_load_file($manifestPath);
        }

        // Determine basepath for localized files
        $language = Factory::getLanguage();
        $basePath = $this->installer->getPath('source');
        if (is_dir($basePath)) {
            if ($this->type == 'component' && $basePath != $targetPath) {
                // For components sourced by manifest, need to find the admin folder
                if ($files = $this->manifest->administration->files) {
                    if ($files = (string)$files['folder']) {
                        $basePath .= '/' . $files;
                    }
                }
            }

        } else {
            $basePath = $this->getExtensionPath($this->type, (string)$this->manifest->alledia->element, $this->group);
        }

        // All the files we want to load
        $languageFiles = [
            'lib_allediainstaller.sys',
            $this->getFullElement()
        ];

        // Load from localized or core language folder
        foreach ($languageFiles as $languageFile) {
            $language->load($languageFile, $basePath) || $language->load($languageFile, JPATH_ADMINISTRATOR);
        }
    }

    /**
     * @param InstallerAdapter $parent
     *
     * @return bool
     */
    public function install(InstallerAdapter $parent): bool
    {
        return true;
    }

    /**
     * @param InstallerAdapter $parent
     *
     * @return bool
     */
    public function discover_install(InstallerAdapter $parent): bool
    {
        return $this->install($parent);
    }

    /**
     * @param InstallerAdapter $parent
     *
     * @return void
     * @throws \Exception
     */
    public function uninstall(InstallerAdapter $parent)
    {
        try {
            $this->uninstallRelated();
            $this->showMessages();

        } catch (\Throwable $e) {
            $this->app->enqueueMessage(
                sprintf('%s:%s - %s', $e->getFile(), $e->getLine(), $e->getMessage()),
                'error'
            );
        }
    }

    /**
     * @param InstallerAdapter $parent
     *
     * @return bool
     */
    public function update(InstallerAdapter $parent): bool
    {
        return true;
    }

    /**
     * @param string           $type
     * @param InstallerAdapter $parent
     *
     * @return bool
     * @throws \Exception
     */
    public function preFlight(string $type, InstallerAdapter $parent): bool
    {
        $success = true;

        if ($type === 'update') {
            $this->clearUpdateServers();
        }

        if (in_array($type, ['install', 'update'])) {
            // Check minimum target Joomla Platform
            if (isset($this->manifest->alledia->targetplatform)) {
                $targetPlatform = (string)$this->manifest->alledia->targetplatform;

                if (!$this->validateTargetVersion(JVERSION, $targetPlatform)) {
                    // Platform version is invalid. Displays a warning and cancel the install
                    $targetPlatform = str_replace('*', 'x', $targetPlatform);

                    $msg = Text::sprintf('LIB_ALLEDIAINSTALLER_WRONG_PLATFORM', $this->getName(), $targetPlatform);
                    $this->app->enqueueMessage($msg, 'warning');
                    $success = false;
                }
            }

            // Check for minimum mysql version
            if ($targetMySQLVersion = $this->manifest->alledia->mysqlminimum) {
                $targetMySQLVersion = (string)$targetMySQLVersion;

                if ($this->dbo->getServerType() == 'mysql') {
                    $dbVersion = $this->dbo->getVersion();
                    if (stripos($dbVersion, 'maria') !== false) {
                        // For MariaDB this is a bit of a punt. We'll assume any version of Maria will do
                        $dbVersion = $targetMySQLVersion;
                    }

                    if (!$this->validateTargetVersion($dbVersion, $targetMySQLVersion)) {
                        // mySQL version too low
                        $minimumMySQL = str_replace('*', 'x', $targetMySQLVersion);

                        $msg = Text::sprintf('LIB_ALLEDIAINSTALLER_WRONG_MYSQL', $this->getName(), $minimumMySQL);
                        $this->app->enqueueMessage($msg, 'warning');
                        $success = false;
                    }
                }
            }

            // Check for minimum php version
            if (isset($this->manifest->alledia->phpminimum)) {
                $targetPhpVersion = (string)$this->manifest->alledia->phpminimum;

                if (!$this->validateTargetVersion(phpversion(), $targetPhpVersion)) {
                    // php version is too low
                    $minimumPhp = str_replace('*', 'x', $targetPhpVersion);

                    $msg = Text::sprintf('LIB_ALLEDIAINSTALLER_WRONG_PHP', $this->getName(), $minimumPhp);
                    $this->app->enqueueMessage($msg, 'warning');
                    $success = false;
                }
            }

            // Check for minimum previous version
            $targetVersion = (string)$this->manifest->alledia->previousminimum;
            if ($type == 'update' && $targetVersion) {
                if (!$this->validatePreviousVersion($targetVersion)) {
                    // Previous minimum is not installed
                    $minimumVersion = str_replace('*', 'x', $targetVersion);

                    $msg = Text::sprintf('LIB_ALLEDIAINSTALLER_WRONG_PREVIOUS', $this->getName(), $minimumVersion);
                    $this->app->enqueueMessage($msg, 'warning');
                    $success = false;
                }
            }
        }

        $this->cancelInstallation = !$success;

        if ($type === 'update' && $success) {
            $this->preserveFavicon();
        }

        return $success;
    }

    /**
     * @param string           $type
     * @param InstallerAdapter $parent
     *
     * @return void
     * @throws \Exception
     */
    public function postFlight(string $type, InstallerAdapter $parent)
    {
        try {
            if ($this->cancelInstallation) {
                $this->app->enqueueMessage('LIB_ALLEDIAINSTALLER_INSTALL_CANCELLED', 'warning');

                return;
            }

            $this->clearObsolete();
            $this->installRelated();
            $this->addAllediaAuthorshipToExtension();

            // @TODO: Stop the script here if this is a related extension (but still remove pro folder, if needed)

            $this->element = (string)$this->manifest->alledia->element;

            // Check and publish/reorder the plugin, if required
            if (strpos($type, 'install') !== false && $this->type === 'plugin') {
                $this->publishThisPlugin();
                $this->reorderThisPlugin();
            }

            // If Free, remove any missed Pro library
            $license = $this->getLicense();
            if (!$license->isPro()) {
                $proLibraryPath = $license->getProLibraryPath();
                if (file_exists($proLibraryPath)) {
                    Folder::delete($proLibraryPath);
                }
            }
            \JLoader::register(
                '\\JFormFieldCustomFooter',
                $license->getExtensionPath() . '/form/fields/customfooter.php'
            );

            // Check if we are on the backend before display anything. This fixes an issue
            // on the updates triggered by Watchful, which is always triggered on the frontend
            if (JPATH_BASE === JPATH_ROOT) {
                // Frontend
                return;
            }

            // Get the footer content
            $this->footer  = '';
            $footerElement = null;

            // Check if we have a dedicated config.xml file
            $configPath = $license->getExtensionPath() . '/config.xml';
            if (is_file($configPath)) {
                $config = $license->getConfig();

                if (!empty($config)) {
                    $footerElement = $config->xpath('//field[@type="customfooter"]');
                }
            } else {
                $footerElement = $this->manifest->xpath('//field[@type="customfooter"]');
            }

            if (class_exists('\\JFormFieldCustomFooter') && $footerElement) {
                $field                = new JFormFieldCustomFooter();
                $field->fromInstaller = true;
                $this->footer         = $field->getInputUsingCustomElement($footerElement[0]);

                unset($field, $footerElement);
            }

            // Show additional installation messages
            $extensionPath = $this->getExtensionPath(
                $this->type,
                (string)$this->manifest->alledia->element,
                $this->group
            );

            // If Pro extension, includes the license form view
            if ($license->isPro()) {
                // Get the OSMyLicensesManager extension to handle the license key
                if ($licensesManagerExtension = new Generic('osmylicensesmanager', 'plugin', 'system')) {
                    if (isset($licensesManagerExtension->params)) {
                        $this->licenseKey = $licensesManagerExtension->params->get('license-keys', '');
                    } else {
                        $this->licenseKey = '';
                    }

                    $this->isLicensesManagerInstalled = true;
                }
            }

            $name = $this->getName() . ($license->isPro() ? ' Pro' : '');

            if ($type === 'update') {
                $this->preserveFavicon();
            }

            // Welcome message
            if ($type === 'install') {
                $string = 'LIB_ALLEDIAINSTALLER_THANKS_INSTALL';
            } else {
                $string = 'LIB_ALLEDIAINSTALLER_THANKS_UPDATE';
            }

            // Variables for the included template
            $this->welcomeMessage = Text::sprintf($string, $name);
            $this->mediaURL       = Uri::root() . 'media/' . $license->getFullElement();

            $this->addStyle($this->mediaFolder . '/css/installer.css');

            /*
             * Include the template
             * Try to find the template in an alternative folder, since some extensions
             * which uses FOF will display the "Installers" view on admin, errouniously.
             * FOF look for views automatically reading the views folder. So on that
             * case we move the installer view to another folder.
            */
            $path = $extensionPath . '/views/installer/tmpl/default.php';

            if (is_file($path)) {
                include $path;
            } else {
                $path = $extensionPath . '/alledia_views/installer/tmpl/default.php';
                if (is_file($path)) {
                    include $path;
                }
            }

            $this->showMessages();

        } catch (\Throwable $e) {
            $this->app->enqueueMessage(
                sprintf('%s:%s - %s', $e->getFile(), $e->getLine(), $e->getMessage()),
                'error'
            );
        }
    }

    /**
     * Install related extensions
     *
     * @return void
     */
    protected function installRelated()
    {
        if ($this->manifest->alledia->relatedExtensions) {
            // Directly unused var, but this resets the Installer instance
            $installer = new Installer();
            unset($installer);

            $source         = $this->installer->getPath('source');
            $extensionsPath = $source . '/extensions';

            $defaultAttributes = $this->manifest->alledia->relatedExtensions->attributes();
            $defaultDowngrade  = $this->getXmlValue($defaultAttributes['downgrade'], 'bool');
            $defaultPublish    = $this->getXmlValue($defaultAttributes['publish'], 'bool');

            foreach ($this->manifest->alledia->relatedExtensions->extension as $extension) {
                $path = $extensionsPath . '/' . $this->getXmlValue($extension);

                if (is_dir($path)) {
                    $type    = $this->getXmlValue($extension['type']);
                    $element = $this->getXmlValue($extension['element']);
                    $group   = $this->getXmlValue($extension['group']);

                    $current = $this->findExtension($type, $element, $group);
                    $isNew   = empty($current);

                    $typeName = ucfirst(trim($group . ' ' . $type));

                    // Get data from the manifest
                    $tmpInstaller = new Installer();
                    $tmpInstaller->setPath('source', $path);
                    $newManifest = $tmpInstaller->getManifest();
                    $newVersion  = (string)$newManifest->version;

                    $this->storeFeedbackForRelatedExtension($element, 'name', (string)$newManifest->name);

                    $downgrade = $this->getXmlValue($extension['downgrade'], 'bool', $defaultDowngrade);
                    if (!$isNew && !$downgrade) {
                        $currentManifestPath = $this->getManifestPath($type, $element, $group);
                        $currentManifest     = $this->getInfoFromManifest($currentManifestPath);

                        // Avoid to update for an outdated version
                        $currentVersion = $currentManifest->get('version');

                        if (version_compare($currentVersion, $newVersion, '>')) {
                            // Store the state of the install/update
                            $this->storeFeedbackForRelatedExtension(
                                $element,
                                'message',
                                Text::sprintf(
                                    'LIB_ALLEDIAINSTALLER_RELATED_UPDATE_STATE_SKIPED',
                                    $newVersion,
                                    $currentVersion
                                )
                            );

                            // Skip the install for this extension
                            continue;
                        }
                    }

                    $text = 'LIB_ALLEDIAINSTALLER_RELATED_' . ($isNew ? 'INSTALL' : 'UPDATE');
                    if ($tmpInstaller->install($path)) {
                        $this->setMessage(Text::sprintf($text, $typeName, $element));
                        if ($isNew) {
                            $current = $this->findExtension($type, $element, $group);

                            if (is_object($current)) {
                                if ($type === 'plugin') {
                                    if ($this->getXmlValue($extension['publish'], 'bool', $defaultPublish)) {
                                        $current->publish();

                                        $this->storeFeedbackForRelatedExtension($element, 'publish', true);
                                    }

                                    if ($ordering = $this->getXmlValue($extension['ordering'])) {
                                        $this->setPluginOrder($current, $ordering);

                                        $this->storeFeedbackForRelatedExtension($element, 'ordering', $ordering);
                                    }
                                }
                            }
                        }

                        $this->storeFeedbackForRelatedExtension(
                            $element,
                            'message',
                            Text::sprintf('LIB_ALLEDIAINSTALLER_RELATED_UPDATE_STATE_INSTALLED', $newVersion)
                        );

                    } else {
                        $this->setMessage(Text::sprintf($text . '_FAIL', $typeName, $element), 'error');

                        $this->storeFeedbackForRelatedExtension(
                            $element,
                            'message',
                            Text::sprintf(
                                'LIB_ALLEDIAINSTALLER_RELATED_UPDATE_STATE_FAILED',
                                $newVersion
                            )
                        );
                    }
                    unset($tmpInstaller);
                }
            }
        }
    }

    /**
     * Uninstall the related extensions that are useless without the component
     *
     * @return void
     * @throws \Exception
     */
    protected function uninstallRelated()
    {
        if ($this->manifest->alledia->relatedExtensions) {
            $installer = new Installer();

            $defaultAttributes = $this->manifest->alledia->relatedExtensions->attributes();
            $defaultUninstall  = $this->getXmlValue($defaultAttributes['uninstall'], 'bool');

            foreach ($this->manifest->alledia->relatedExtensions->extension as $extension) {
                $type    = $this->getXmlValue($extension['type']);
                $element = $this->getXmlValue($extension['element']);
                $group   = $this->getXmlValue($extension['group']);

                $uninstall = $this->getXmlValue($extension['uninstall'], 'bool', $defaultUninstall);
                if ($uninstall) {
                    if ($current = $this->findExtension($type, $element, $group)) {
                        $msg     = 'LIB_ALLEDIAINSTALLER_RELATED_UNINSTALL';
                        $msgType = 'message';
                        if (!$installer->uninstall($current->get('type'), $current->get('extension_id'))) {
                            $msg     .= '_FAIL';
                            $msgType = 'error';
                        }
                        $this->setMessage(
                            Text::sprintf($msg, ucfirst($type), $element),
                            $msgType
                        );
                    }
                } elseif ($this->app->get('debug', 0)) {
                    $this->setMessage(
                        Text::sprintf(
                            'LIB_ALLEDIAINSTALLER_RELATED_NOT_UNINSTALLED',
                            ucfirst($type),
                            $element
                        ),
                        'warning'
                    );
                }
            }
        }
    }

    /**
     * @param ?string $type
     * @param ?string $element
     * @param ?string $group
     *
     * @return ?Extension
     */
    protected function findExtension(?string $type, ?string $element, ?string $group = null): ?Extension
    {
        /** @var Extension $row */
        $row = Table::getInstance('extension');

        $prefixes = [
            'component' => 'com_',
            'module'    => 'mod_'
        ];

        // Fix the element, if the prefix is not found
        if (array_key_exists($type, $prefixes)) {
            if (substr_count($element, $prefixes[$type]) === 0) {
                $element = $prefixes[$type] . $element;
            }
        }

        // Fix the element for templates
        if ('template' === $type) {
            $element = str_replace('tpl_', '', $element);
        }

        $terms = [
            'type'    => $type,
            'element' => $element
        ];

        if ($type === 'plugin') {
            $terms['folder'] = $group;
        }

        $eid = $row->find($terms);

        if ($eid) {
            $row->load($eid);
            return $row;
        }

        return null;
    }

    /**
     * Set requested ordering for selected plugin extension
     * Accepted ordering arguments:
     * (n<=1 | first) First within folder
     * (* | last) Last within folder
     * (before:element) Before the named plugin
     * (after:element) After the named plugin
     *
     * @param Extension $extension
     * @param string    $order
     *
     * @return void
     */
    protected function setPluginOrder(Extension $extension, string $order)
    {
        if ($extension->get('type') == 'plugin' && !empty($order)) {
            $db    = $this->dbo;
            $query = $db->getQuery(true);

            $query->select('extension_id, element');
            $query->from('#__extensions');
            $query->where([
                $db->quoteName('folder') . ' = ' . $db->quote($extension->get('folder')),
                $db->quoteName('type') . ' = ' . $db->quote($extension->get('type'))
            ]);
            $query->order($db->quoteName('ordering'));

            $plugins = $db->setQuery($query)->loadObjectList('element');

            // Set the order only if plugin already successfully installed
            if (array_key_exists($extension->get('element'), $plugins)) {
                $target = [
                    $extension->get('element') => $plugins[$extension->get('element')]
                ];
                $others = array_diff_key($plugins, $target);

                if ((is_numeric($order) && $order <= 1) || $order == 'first') {
                    // First in order
                    $neworder = array_merge($target, $others);

                } elseif (($order == '*') || ($order == 'last')) {
                    // Last in order
                    $neworder = array_merge($others, $target);

                } elseif (preg_match('/^(before|after):(\S+)$/', $order, $match)) {
                    // place before or after named plugin
                    $place    = $match[1];
                    $element  = $match[2];
                    $neworder = [];
                    $previous = '';

                    foreach ($others as $plugin) {
                        if (
                            (($place == 'before') && ($plugin->element == $element))
                            || (($place == 'after') && ($previous == $element))
                        ) {
                            $neworder = array_merge($neworder, $target);
                        }
                        $neworder[$plugin->element] = $plugin;
                        $previous                   = $plugin->element;
                    }

                    if (count($neworder) < count($plugins)) {
                        // Make it last if the requested plugin isn't installed
                        $neworder = array_merge($neworder, $target);
                    }

                } else {
                    $neworder = [];
                }

                if (count($neworder) == count($plugins)) {
                    // Only reorder if have a validated new order
                    BaseDatabaseModel::addIncludePath(
                        JPATH_ADMINISTRATOR . '/components/com_plugins/models',
                        'PluginsModels'
                    );
                    /** @var \PluginsModelPlugin $model */
                    $model = BaseDatabaseModel::getInstance('Plugin', 'PluginsModel');

                    $ids = [];
                    foreach ($neworder as $plugin) {
                        $ids[] = $plugin->extension_id;
                    }
                    $order = range(1, count($ids));
                    $model->saveorder($ids, $order);
                }
            }
        }
    }

    /**
     * Display messages from array
     *
     * @return void
     * @throws \Exception
     */
    protected function showMessages()
    {
        foreach ($this->messages as $msg) {
            $this->app->enqueueMessage($msg[0], $msg[1]);
        }

        $this->messages = [];
    }

    /**
     * Add a message to the message list
     *
     * @param string $msg
     * @param string $type
     * @param bool   $prepend
     *
     * @return void
     */
    protected function setMessage(string $msg, string $type = 'message', bool $prepend = false)
    {
        if ($prepend === null) {
            $prepend = in_array($type, ['notice', 'error']);
        }

        if ($prepend) {
            array_unshift($this->messages, [$msg, $type]);
        } else {
            $this->messages[] = [$msg, $type];
        }
    }

    /**
     * Delete obsolete files, folders and extensions.
     * Files and folders are identified from the site
     * root path and should starts with a slash.
     *
     * @return void
     */
    protected function clearObsolete()
    {
        $obsolete = $this->manifest->alledia->obsolete;
        if ($obsolete) {
            // Extensions
            if ($obsolete->extension) {
                foreach ($obsolete->extension as $extension) {
                    $type    = $this->getXmlValue($extension['type']);
                    $element = $this->getXmlValue($extension['element']);
                    $group   = $this->getXmlValue($extension['group']);

                    $current = $this->findExtension($type, $element, $group);
                    if (!empty($current)) {
                        // Try to uninstall
                        $tmpInstaller = new Installer();
                        $uninstalled  = $tmpInstaller->uninstall($type, $current->get('extension_id'));

                        $typeName = ucfirst(trim(($group ?: '') . ' ' . $type));

                        if ($uninstalled) {
                            $this->setMessage(
                                Text::sprintf(
                                    'LIB_ALLEDIAINSTALLER_OBSOLETE_UNINSTALLED_SUCCESS',
                                    strtolower($typeName),
                                    $element
                                )
                            );
                        } else {
                            $this->setMessage(
                                Text::sprintf(
                                    'LIB_ALLEDIAINSTALLER_OBSOLETE_UNINSTALLED_FAIL',
                                    strtolower($typeName),
                                    $element
                                ),
                                'error'
                            );
                        }
                    }
                }
            }

            // Files
            if ($obsolete->file) {
                foreach ($obsolete->file as $file) {
                    $path = JPATH_ROOT . '/' . trim((string)$file, '/');
                    if (file_exists($path)) {
                        File::delete($path);
                    }
                }
            }

            // Folders
            if ($obsolete->folder) {
                jimport('joomla.filesystem.folder');

                foreach ($obsolete->folder as $folder) {
                    $path = JPATH_ROOT . '/' . trim((string)$folder, '/');
                    if (file_exists($path)) {
                        Folder::delete($path);
                    }
                }
            }
        }
    }

    /**
     * Finds the extension row for the main extension
     *
     * @return ?Extension
     */
    protected function findThisExtension(): ?Extension
    {
        return $this->findExtension(
            $this->getXmlValue($this->manifest['type']),
            $this->getXmlValue($this->manifest->alledia->element),
            $this->getXmlValue($this->manifest['group'])
        );
    }

    /**
     * Use this in preflight to clear out obsolete update servers when the url has changed.
     */
    protected function clearUpdateServers()
    {
        $extension = $this->findThisExtension();

        $db = $this->dbo;

        $query = $db->getQuery(true)
            ->select($db->quoteName('update_site_id'))
            ->from($db->quoteName('#__update_sites_extensions'))
            ->where($db->quoteName('extension_id') . '=' . (int)$extension->get('extension_id'));

        if ($list = $db->setQuery($query)->loadColumn()) {
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__update_sites_extensions'))
                ->where($db->quoteName('extension_id') . '=' . (int)$extension->get('extension_id'));
            $db->setQuery($query)->execute();

            array_walk($list, 'intval');
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__update_sites'))
                ->where($db->quoteName('update_site_id') . ' IN (' . join(',', $list) . ')');
            $db->setQuery($query)->execute();
        }
    }

    /**
     * Get the full element, like com_myextension, lib_extension
     *
     * @param ?string $type
     * @param ?string $element
     * @param ?string $group
     *
     * @return string
     */
    protected function getFullElement(?string $type = null, ?string $element = null, ?string $group = null): string
    {
        $prefixes = [
            'component' => 'com',
            'plugin'    => 'plg',
            'template'  => 'tpl',
            'library'   => 'lib',
            'cli'       => 'cli',
            'module'    => 'mod',
            'file'      => 'file'
        ];

        $type    = $type ?: $this->type;
        $element = $element ?: (string)$this->manifest->alledia->element;
        $group   = $group ?: $this->group;

        $fullElement = $prefixes[$type] . '_';

        if ($type === 'plugin') {
            $fullElement .= $group . '_';
        }

        return $fullElement . $element;
    }

    /**
     * @return Licensed
     */
    protected function getLicense(): Licensed
    {
        if ($this->license === null) {
            $this->license = new Licensed(
                (string)$this->manifest->alledia->namespace,
                $this->type,
                $this->group
            );
        }

        return $this->license;
    }

    /**
     * @param string $manifestPath
     *
     * @return Registry
     */
    protected function getInfoFromManifest(string $manifestPath): Registry
    {
        $info = new Registry();

        if (is_file($manifestPath)) {
            $xml = simplexml_load_file($manifestPath);

            $attributes = (array)$xml->attributes();
            $attributes = $attributes['@attributes'];
            foreach ($attributes as $attribute => $value) {
                $info->set($attribute, $value);
            }

            foreach ($xml->children() as $e) {
                if (!$e->children()) {
                    $info->set($e->getName(), (string)$e);
                }
            }

        } else {
            $relativePath = str_replace(JPATH_SITE . '/', '', $manifestPath);
            $this->setMessage(
                Text::sprintf('LIB_ALLEDIAINSTALLER_MANIFEST_NOT_FOUND', $relativePath),
                'error'
            );
        }

        return $info;
    }

    /**
     * @param string  $type
     * @param string  $element
     * @param ?string $group
     *
     * @return string
     */
    protected function getExtensionPath(string $type, string $element, ?string $group = ''): string
    {
        $folders = [
            'component' => 'administrator/components/',
            'plugin'    => 'plugins/',
            'template'  => 'templates/',
            'library'   => 'libraries/',
            'cli'       => 'cli/',
            'module'    => 'modules/',
            'file'      => 'administrator/manifests/files/'
        ];

        $basePath = JPATH_SITE . '/' . $folders[$type];

        switch ($type) {
            case 'plugin':
                $basePath .= $group . '/';
                break;

            case 'module':
                if (!preg_match('/^mod_/', $element)) {
                    $basePath .= 'mod_';
                }
                break;

            case 'component':
                if (!preg_match('/^com_/', $element)) {
                    $basePath .= 'com_';
                }
                break;

            case 'template':
                if (preg_match('/^tpl_/', $element)) {
                    $element = str_replace('tpl_', '', $element);
                }
                break;
        }

        if ($type !== 'file') {
            $basePath .= $element;
        }

        return $basePath;
    }

    /**
     * @param string  $type
     * @param string  $element
     * @param ?string $group
     *
     * @return int
     */
    protected function getExtensionId(string $type, string $element, ?string $group = ''): int
    {
        $db    = $this->dbo;
        $query = $db->getQuery(true)
            ->select('extension_id')
            ->from('#__extensions')
            ->where([
                $db->quoteName('element') . ' = ' . $db->quote($element),
                $db->quoteName('folder') . ' = ' . $db->quote($group),
                $db->quoteName('type') . ' = ' . $db->quote($type)
            ]);
        $db->setQuery($query);

        return (int)$db->loadResult();
    }

    /**
     * Get the path for the manifest file
     *
     * @return string The path
     */
    protected function getManifestPath($type, $element, $group = '')
    {
        $installer = new Installer();

        switch ($type) {
            case 'library':
            case 'file':
                $folders = [
                    'library' => 'libraries',
                    'file'    => 'files'
                ];

                $manifestPath = JPATH_SITE . '/administrator/manifests/' . $folders[$type] . '/' . $element . '.xml';

                if (!file_exists($manifestPath) || !$installer->isManifest($manifestPath)) {
                    $manifestPath = false;
                }
                break;

            default:
                $basePath = $this->getExtensionPath($type, $element, $group);

                $installer->setPath('source', $basePath);
                $installer->getManifest();

                $manifestPath = $installer->getPath('manifest');
                break;
        }

        return $manifestPath;
    }

    /**
     * Check if it needs to publish the extension
     *
     * @return void
     */
    protected function publishThisPlugin()
    {
        $attributes = $this->manifest->alledia->element->attributes();
        $publish    = (string)$attributes['publish'];

        if ($publish === 'true' || $publish === '1') {
            $extension = $this->findThisExtension();
            $extension->publish();
        }
    }

    /**
     * Check if it needs to reorder the extension
     *
     * @return void
     */
    protected function reorderThisPlugin()
    {
        $attributes = $this->manifest->alledia->element->attributes();
        $ordering   = (string)$attributes['ordering'];

        if ($ordering !== '') {
            $extension = $this->findThisExtension();
            $this->setPluginOrder($extension, $ordering);
        }
    }

    /**
     * Stores feedback data for related extensions to display after install
     *
     * @param string $element
     * @param string $key
     * @param string $value
     *
     * @return void
     */
    protected function storeFeedbackForRelatedExtension(string $element, string $key, string $value)
    {
        if (empty($this->relatedExtensionFeedback[$element])) {
            $this->relatedExtensionFeedback[$element] = [];
        }

        $this->relatedExtensionFeedback[$element][$key] = $value;
    }

    /**
     * This method add a mark to the extensions, allowing to detect our extensions
     * on the extensions table.
     *
     * @return void
     */
    protected function addAllediaAuthorshipToExtension()
    {
        if ($extension = $this->findThisExtension()) {
            $db = $this->dbo;

            // Update the extension
            $customData         = json_decode($extension->get('custom_data')) ?: (object)[];
            $customData->author = 'Joomlashack';

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('custom_data') . '=' . $db->quote(json_encode($customData)))
                ->where($db->quoteName('extension_id') . '=' . (int)$extension->get('extension_id'));
            $db->setQuery($query)->execute();

            // Update the Alledia framework
            // @TODO: remove this after libraries be able to have a custom install script
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('custom_data') . '=' . $db->quote('{"author":"Joomlashack"}'))
                ->where([
                    $db->quoteName('type') . '=' . $db->quote('library'),
                    $db->quoteName('element') . '=' . $db->quote('allediaframework')
                ]);
            $db->setQuery($query)->execute();
        }

    }

    /**
     * Add styles to the output. Used because when the postFlight
     * method is called, we can't add stylesheets to the head.
     *
     * @param mixed $stylesheets
     */
    protected function addStyle($stylesheets)
    {
        if (is_string($stylesheets)) {
            $stylesheets = [$stylesheets];
        }

        foreach ($stylesheets as $path) {
            if (file_exists($path)) {
                $style = file_get_contents($path);

                echo '<style>' . $style . '</style>';
            }
        }
    }

    /**
     * On new component install, this will check and fix any menus
     * that may have been created in a previous installation.
     *
     * @return void
     */
    protected function fixMenus()
    {
        if ($this->type == 'component') {
            $db = $this->dbo;

            if ($extension = $this->findThisExtension()) {
                $id     = $extension->get('extension_id');
                $option = $extension->get('name');

                $query = $db->getQuery(true)
                    ->update('#__menu')
                    ->set('component_id = ' . $db->quote($id))
                    ->where([
                        'type = ' . $db->quote('component'),
                        'link LIKE ' . $db->quote("%option={$option}%")
                    ]);
                $db->setQuery($query)->execute();

                // Check hidden admin menu option
                // @TODO:  Remove after Joomla! incorporates this natively
                $menuElement = $this->manifest->administration->menu;
                if (in_array((string)$menuElement['hidden'], ['true', 'hidden'])) {
                    $menu = Table::getInstance('Menu');
                    $menu->load(['component_id' => $id, 'client_id' => 1]);
                    if ($menu->id) {
                        $menu->delete();
                    }
                }
            }
        }
    }

    /**
     * Get and store a cache of columns of a table
     *
     * @param string $table The table name
     *
     * @return string[]
     */
    protected function getColumnsFromTable(string $table): array
    {
        if (!isset($this->columns[$table])) {
            $db = $this->dbo;
            $db->setQuery('SHOW COLUMNS FROM ' . $db->quoteName($table));
            $rows = $db->loadObjectList();

            $columns = [];
            foreach ($rows as $row) {
                $columns[] = $row->Field;
            }

            $this->columns[$table] = $columns;
        }

        return $this->columns[$table];
    }

    /**
     * Get and store a cache of indexes of a table
     *
     * @param string $table The table name
     *
     * @return string[]
     */
    protected function getIndexesFromTable(string $table): array
    {
        if (!isset($this->indexes[$table])) {
            $db = $this->dbo;
            $db->setQuery('SHOW INDEX FROM ' . $db->quoteName($table));
            $rows = $db->loadObjectList();

            $indexes = [];
            foreach ($rows as $row) {
                $indexes[] = $row->Key_name;
            }

            $this->indexes[$table] = $indexes;
        }

        return $this->indexes[$table];
    }

    /**
     * Add columns to a table if they doesn't exists
     *
     * @param string   $table   The table name
     * @param string[] $columns Assoc array of columnNames => definition
     *
     * @return void
     */
    protected function addColumnsIfNotExists(string $table, array $columns)
    {
        $db = $this->dbo;

        $existentColumns = $this->getColumnsFromTable($table);

        foreach ($columns as $column => $specification) {
            if (!in_array($column, $existentColumns)) {
                $db->setQuery(
                    sprintf(
                        'ALTER TABLE %s ADD COLUMN %s %s',
                        $db->quoteName($table),
                        $db->quoteName($column),
                        $specification
                    )
                );
                $db->execute();
            }
        }
    }

    /**
     * Add indexes to a table if they doesn't exists
     *
     * @param string $table   The table name
     * @param array  $indexes Assoc array of indexName => definition
     *
     * @return void
     */
    protected function addIndexesIfNotExists(string $table, array $indexes)
    {
        $db = $this->dbo;

        $existentIndexes = $this->getIndexesFromTable($table);

        foreach ($indexes as $index => $specification) {
            if (!in_array($index, $existentIndexes)) {
                $db->setQuery(
                    sprintf('ALTER TABLE %s CREATE INDEX %s ON %s', $db->quoteName($table), $specification, $index)
                )
                    ->execute();
            }
        }
    }

    /**
     * Drop columns from a table if they exists
     *
     * @param string   $table   The table name
     * @param string[] $columns The column names that needed to be checked and added
     *
     * @return void
     */
    protected function dropColumnsIfExists(string $table, array $columns)
    {
        $db = $this->dbo;

        $existentColumns = $this->getColumnsFromTable($table);

        foreach ($columns as $column) {
            if (in_array($column, $existentColumns)) {
                $db->setQuery(sprintf('ALTER TABLE %s DROP COLUMN %s', $db->quoteName($table), $column))
                    ->execute();
            }
        }
    }

    /**
     * Check if a table exists
     *
     * @param string $name
     *
     * @return bool
     */
    protected function tableExists(string $name): bool
    {
        $tables = $this->getTables(true);

        $name = str_replace('#__', $this->app->get('dbprefix'), $name);

        return in_array($name, $tables);
    }

    /**
     * @param ?bool $force Force to get a fresh list of tables
     *
     * @return string[] List of tables
     */
    protected function getTables(?bool $force = false): array
    {
        if ($force || $this->tables === null) {
            $tables = $this->dbo->setQuery('SHOW TABLES')->loadRowList();

            $this->tables = array_map(
                function ($item) {
                    return $item[0];
                },
                $tables
            );
        }

        return $this->tables;
    }

    /**
     * Parses a conditional string, returning a Boolean value (default: false).
     * For now it only supports an extension name and * as version.
     *
     * @param  string $expression The conditional expression
     *
     * @return bool                According to the evaluation of the expression
     */
    protected function parseConditionalExpression($expression)
    {
        $expression = strtolower($expression);
        $terms      = explode('=', $expression);
        $term0      = trim($terms[0]);

        if (count($terms) === 1) {
            return !(empty($terms[0]) || $terms[0] === 'null');
        } else {
            // Is the first term a name of extension?
            if (preg_match('/^(com_|plg_|mod_|lib_|tpl_|cli_)/', $term0)) {
                $info = $this->getExtensionInfoFromElement($term0);

                $extension = $this->findExtension($info['type'], $term0, $info['group']);

                // @TODO: compare the version, if specified, or different than *
                // @TODO: Check if the extension is enabled, not just installed

                if (!empty($extension)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get extension's info from element string, or extension name
     *
     * @param  string $element The extension name, as element
     *
     * @return array           An associative array with information about the extension
     */
    public static function getExtensionInfoFromElement($element)
    {
        $result = array(
            'type'      => null,
            'name'      => null,
            'group'     => null,
            'prefix'    => null,
            'namespace' => null
        );

        $types = array(
            'com' => 'component',
            'plg' => 'plugin',
            'mod' => 'module',
            'lib' => 'library',
            'tpl' => 'template',
            'cli' => 'cli'
        );

        $element = explode('_', $element);

        $result['prefix'] = $element[0];

        if (array_key_exists($result['prefix'], $types)) {
            $result['type'] = $types[$result['prefix']];

            if ($result['prefix'] === 'plg') {
                $result['group'] = $element[1];
                $result['name']  = $element[2];
            } else {
                $result['name']  = $element[1];
                $result['group'] = null;
            }
        }

        $result['namespace'] = preg_replace_callback(
            '/^(os[a-z])(.*)/i',
            function ($matches) {
                return strtoupper($matches[1]) . $matches[2];
            },
            $result['name']
        );

        return $result;
    }

    /**
     * Check if the actual version is at least the minimum target version.
     *
     * @param string $actualVersion
     * @param string $targetVersion The required target platform
     *
     * @return bool True, if the target version is greater than or equal to actual version
     */
    protected function validateTargetVersion($actualVersion, $targetVersion)
    {
        // If is universal, any version is valid
        if ($targetVersion === '.*') {
            return true;
        }

        $targetVersion = str_replace('*', '0', $targetVersion);

        // Compare with the actual version
        return version_compare($actualVersion, $targetVersion, 'ge');
    }

    /**
     * @param string $targetVersion
     *
     * @return bool
     */
    protected function validatePreviousVersion($targetVersion)
    {
        if ($this->previousManifest) {
            $lastVersion = (string)$this->previousManifest->version;

            return $this->validateTargetVersion($lastVersion, $targetVersion);
        }

        return true;
    }

    /**
     * Get the extension name. If no custom name is set, uses the namespace
     *
     * @return string
     */
    protected function getName(): string
    {
        // Get the extension name. If no custom name is set, uses the namespace
        if (isset($this->manifest->alledia->name)) {
            $name = $this->manifest->alledia->name;

        } else {
            $name = $this->manifest->alledia->namespace;
        }
        return (string)$name;
    }

    /**
     * If a template, preserve the favicon during an update.
     * Rename favicon during preFlight(). Rename back during postFlight()
     */
    protected function preserveFavicon()
    {
        $nameOfExtension = (string)$this->manifest->alledia->element;

        $extensionType = $this->getExtensionInfoFromElement($nameOfExtension);

        if ($extensionType['prefix'] === 'tpl') {
            $pathToTemplate = $this->getExtensionPath($this->type, $nameOfExtension);

            // These will be used to preserve the favicon during an update
            $favicon     = $pathToTemplate . '/favicon.ico';
            $faviconTemp = $pathToTemplate . '/favicon-temp.ico';

            /**
             * Rename favicon.
             * The order of the conditionals should be kept the same, because
             * preFlight() runs before postFLight().
             * If the order is reversed, favicon in update package will replace
             * $faviconTemp during update, which we don't want to happen.
             */
            if (is_file($faviconTemp)) {
                rename($faviconTemp, $favicon);

            } elseif (is_file($favicon)) {
                rename($favicon, $faviconTemp);
            }
        }
    }

    /**
     * @param SimpleXMLElement|string $element
     * @param ?string                  $type
     * @param mixed                   $default
     *
     * @return bool|string
     */
    protected function getXmlValue($element, ?string $type = 'string', $default = null)
    {
        $value = $element ? (string)$element : $default;

        switch ($type) {
            case 'bool':
            case 'boolean':
                $value = $element
                    ? $value == 'true' || $value == '1'
                    : (bool)$default;
                break;

            case 'string':
            default:
                $value = trim($value);
                break;
        }

        return $value;
    }

}

<?php
/**
 * @package   ShackInstaller
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2016-2021 Joomlashack.com. All rights reserved
 * @license   https://www.gnu.org/licenses/gpl.html GNU/GPL
 *
 * This file is part of ShackInstaller.
 *
 * ShackInstaller is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * ShackInstaller is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ShackInstaller.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Alledia\Installer;

defined('_JEXEC') or die();

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
use Joomla\Component\Plugins\Administrator\Model\PluginModel;
use Joomla\Registry\Registry;
use SimpleXMLElement;
use Throwable;

require_once 'include.php';

abstract class AbstractScript
{
    public const VERSION = '2.0.8b1';

    /**
     * @var bool
     */
    protected $outputAllowed = true;

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
     * @deprecated v2.0.0
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
     * @var bool
     */
    protected $debug = false;

    /**
     * @param InstallerAdapter $parent
     *
     * @return void
     * @throws \Exception
     */
    public function __construct($parent)
    {
        $this->sendDebugMessage('ShackInstaller v' . static::VERSION);
        $this->sendDebugMessage(__METHOD__);

        $this->initProperties($parent);
    }

    /**
     * @param InstallerAdapter $parent
     *
     * @return void
     * @throws \Exception
     */
    public function initProperties($parent)
    {
        $this->sendDebugMessage(__METHOD__);

        $this->app = Factory::getApplication();

        $this->outputAllowed = JPATH_BASE == JPATH_ADMINISTRATOR;

        try {
            $this->dbo       = Factory::getDbo();
            $this->installer = $parent->getParent();
            $this->manifest  = $this->installer->getManifest();

            if ($media = $this->manifest->media) {
                $this->mediaFolder = JPATH_SITE . '/' . $media['folder'] . '/' . $media['destination'];
            }

            $attributes  = $this->manifest->attributes();
            $this->type  = (string)$attributes['type'];
            $this->group = (string)$attributes['group'];

            // Get the previous manifest for use in upgrades
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
                $basePath = $this->getExtensionPath(
                    $this->type,
                    (string)$this->manifest->alledia->element,
                    $this->group
                );
            }

            // All the files we want to load
            $languageFiles = [
                'lib_shackinstaller.sys',
                $this->getFullElement()
            ];

            // Load from localized or core language folder
            foreach ($languageFiles as $languageFile) {
                $language->load($languageFile, $basePath) || $language->load($languageFile, JPATH_ADMINISTRATOR);
            }

        } catch (Throwable $error) {
            $this->cancelInstallation = true;
            $this->sendErrorMessage($error);
        }
    }

    /**
     * @param InstallerAdapter $parent
     *
     * @return bool
     */
    public function install($parent)
    {
        return true;
    }

    /**
     * @param InstallerAdapter $parent
     *
     * @return bool
     */
    public function discover_install($parent)
    {
        return $this->install($parent);
    }

    /**
     * @param InstallerAdapter $parent
     *
     * @return void
     * @throws \Exception
     */
    public function uninstall($parent)
    {
        $this->sendDebugMessage(__METHOD__);

        try {
            $this->uninstallRelated();

        } catch (Throwable $error) {
            $this->sendErrorMessage($error);
        }
    }

    /**
     * @param InstallerAdapter $parent
     *
     * @return bool
     */
    public function update($parent)
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
    public function preFlight($type, $parent)
    {
        if ($this->cancelInstallation) {
            $this->sendDebugMessage('CANCEL: ' . __METHOD__);

            return false;
        }

        try {
            $this->sendDebugMessage(__METHOD__);
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

                        $msg = Text::sprintf('LIB_SHACKINSTALLER_WRONG_PLATFORM', $this->getName(), $targetPlatform);

                        $this->sendMessage($msg, 'warning');
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

                            $msg = Text::sprintf('LIB_SHACKINSTALLER_WRONG_MYSQL', $this->getName(), $minimumMySQL);
                            $this->sendMessage($msg, 'warning');
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

                        $msg = Text::sprintf('LIB_SHACKINSTALLER_WRONG_PHP', $this->getName(), $minimumPhp);
                        $this->sendMessage($msg, 'warning');
                        $success = false;
                    }
                }

                // Check for minimum previous version
                $targetVersion = (string)$this->manifest->alledia->previousminimum;
                if ($type == 'update' && $targetVersion) {
                    if (!$this->validatePreviousVersion($targetVersion)) {
                        // Previous minimum is not installed
                        $minimumVersion = str_replace('*', 'x', $targetVersion);

                        $msg = Text::sprintf('LIB_SHACKINSTALLER_WRONG_PREVIOUS', $this->getName(), $minimumVersion);
                        $this->sendMessage($msg, 'warning');
                        $success = false;
                    }
                }
            }

            $this->cancelInstallation = !$success;

            if ($type === 'update' && $success) {
                $this->preserveFavicon();
            }

            return $success;

        } catch (Throwable $error) {
            $this->sendErrorMessage($error);
        }

        return false;
    }

    /**
     * @param string           $type
     * @param InstallerAdapter $parent
     *
     * @return void
     * @throws \Exception
     */
    public function postFlight($type, $parent)
    {
        $this->sendDebugMessage(__METHOD__);

        /*
         * Joomla 4 now calls postFlight on uninstalls. Which is kinda cool actually.
         * But this code is problematic in that scenario
         */
        if ($type == 'uninstall') {
            return;
        }

        try {
            if ($this->cancelInstallation) {
                $this->sendMessage('LIB_SHACKINSTALLER_INSTALL_CANCELLED', 'warning');

                return;
            }

            $this->clearObsolete();
            $this->installRelated($parent);
            $this->addAllediaAuthorshipToExtension();

            $this->element = (string)$this->manifest->alledia->element;

            // Check and publish/reorder the plugin, if required
            if (strpos($type, 'install') !== false && $this->type === 'plugin') {
                $this->publishThisPlugin();
                $this->reorderThisPlugin();
            }

            // If Free, remove any Pro library
            $license = $this->getLicense();
            if (!$license->isPro()) {
                $proLibraryPath = $license->getProLibraryPath();
                if (is_dir($proLibraryPath)) {
                    Folder::delete($proLibraryPath);
                }
            }

            if ($type === 'update') {
                $this->preserveFavicon();
            }

            $this->displayWelcome($type);

        } catch (Throwable $error) {
            $this->sendErrorMessage($error);
        }
    }

    /**
     * @param InstallerAdapter $parent
     *
     * @return void
     */
    protected function installRelated($parent)
    {
        $this->sendDebugMessage(__METHOD__);

        if ($this->manifest->alledia->relatedExtensions) {
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
                    $key     = md5(join(':', [$type, $element, $group]));

                    $current = $this->findExtension($type, $element, $group);
                    $isNew   = empty($current);

                    $typeName = ucfirst(trim($group . ' ' . $type));

                    // Get data from the manifest
                    $tmpInstaller = new Installer();
                    $tmpInstaller->setPath('source', $path);
                    $tmpInstaller->setPath('parent', $this->installer->getPath('source'));

                    $newManifest = $tmpInstaller->getManifest();
                    $newVersion  = (string)$newManifest->version;

                    $this->storeFeedbackForRelatedExtension($key, 'name', (string)$newManifest->name);

                    $downgrade = $this->getXmlValue($extension['downgrade'], 'bool', $defaultDowngrade);
                    if (!$isNew && !$downgrade) {
                        $currentManifestPath = $this->getManifestPath($type, $element, $group);
                        $currentManifest     = $this->getInfoFromManifest($currentManifestPath);

                        // Avoid to update for an outdated version
                        $currentVersion = $currentManifest->get('version');

                        if (version_compare($currentVersion, $newVersion, '>')) {
                            // Store the state of the install/update
                            $this->storeFeedbackForRelatedExtension(
                                $key,
                                'message',
                                Text::sprintf(
                                    'LIB_SHACKINSTALLER_RELATED_UPDATE_STATE_SKIPED',
                                    $newVersion,
                                    $currentVersion
                                )
                            );

                            // Skip the install for this extension
                            continue;
                        }
                    }

                    $text = 'LIB_SHACKINSTALLER_RELATED_' . ($isNew ? 'INSTALL' : 'UPDATE');
                    if ($tmpInstaller->install($path)) {
                        $this->sendMessage(Text::sprintf($text, $typeName, $element));
                        if ($isNew) {
                            $current = $this->findExtension($type, $element, $group);

                            if (is_object($current)) {
                                if ($type === 'plugin') {
                                    if ($this->getXmlValue($extension['publish'], 'bool', $defaultPublish)) {
                                        $current->publish();

                                        $this->storeFeedbackForRelatedExtension($key, 'publish', true);
                                    }

                                    if ($ordering = $this->getXmlValue($extension['ordering'])) {
                                        $this->setPluginOrder($current, $ordering);

                                        $this->storeFeedbackForRelatedExtension($key, 'ordering', $ordering);
                                    }
                                }
                            }
                        }

                        $this->storeFeedbackForRelatedExtension(
                            $key,
                            'message',
                            Text::sprintf('LIB_SHACKINSTALLER_RELATED_UPDATE_STATE_INSTALLED', $newVersion)
                        );

                    } else {
                        $this->sendMessage(Text::sprintf($text . '_FAIL', $typeName, $element), 'error');

                        $this->storeFeedbackForRelatedExtension(
                            $key,
                            'message',
                            Text::sprintf(
                                'LIB_SHACKINSTALLER_RELATED_UPDATE_STATE_FAILED',
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
                        $msg     = 'LIB_SHACKINSTALLER_RELATED_UNINSTALL';
                        $msgType = 'message';
                        if (!$installer->uninstall($current->get('type'), $current->get('extension_id'))) {
                            $msg     .= '_FAIL';
                            $msgType = 'error';
                        }
                        $this->sendMessage(
                            Text::sprintf($msg, ucfirst($type), $element),
                            $msgType
                        );
                    }
                } elseif ($this->app->get('debug', 0)) {
                    $this->sendMessage(
                        Text::sprintf(
                            'LIB_SHACKINSTALLER_RELATED_NOT_UNINSTALLED',
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
    protected function findExtension($type, $element, $group = null)
    {
        // @TODO: Why do we need to use JTable?
        /** @var Extension $row */
        $row = \JTable::getInstance('extension');

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
        if ($type == 'template') {
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
    protected function setPluginOrder($extension, $order)
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

                    // @TODO: Model class is (\PluginsModelPlugin) in J3 but this works either way
                    /** @var PluginModel $model */
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
     * Add a message to the message list
     *
     * @param string $msg
     * @param string $type
     *
     * @return void
     * @deprecated v2.0.0
     */
    protected function setMessage($msg, $type = 'message')
    {
        $this->sendMessage($msg, $type);
    }

    /**
     * Display queued messages
     *
     * @return void
     * @deprecated v2.0.0
     */
    protected function showMessages()
    {
        if ($this->messages) {
            foreach ($this->messages as $msg) {
                $text = $msg[0] ?? null;
                $type = $msg[1] ?? null;

                if ($text) {
                    $this->sendMessage($text, $type);
                }
            }

            $this->messages = [];
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
                            $this->sendMessage(
                                Text::sprintf(
                                    'LIB_SHACKINSTALLER_OBSOLETE_UNINSTALLED_SUCCESS',
                                    strtolower($typeName),
                                    $element
                                )
                            );
                        } else {
                            $this->sendMessage(
                                Text::sprintf(
                                    'LIB_SHACKINSTALLER_OBSOLETE_UNINSTALLED_FAIL',
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
                    if (is_file($path)) {
                        File::delete($path);
                    }
                }
            }

            // Folders
            if ($obsolete->folder) {
                foreach ($obsolete->folder as $folder) {
                    $path = JPATH_ROOT . '/' . trim((string)$folder, '/');
                    if (is_dir($path)) {
                        Folder::delete($path);
                    }
                }
            }
        }

        $oldLanguageFiles = Folder::files(JPATH_ADMINISTRATOR . '/language', '\.lib_allediainstaller\.', true, true);
        foreach ($oldLanguageFiles as $oldLanguageFile) {
            File::delete($oldLanguageFile);
        }
    }

    /**
     * Finds the extension row for the main extension
     *
     * @return ?Extension
     */
    protected function findThisExtension()
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
        if ($extension = $this->findThisExtension()) {
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
    protected function getFullElement($type = null, $element = null, $group = null)
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
    protected function getLicense()
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
    protected function getInfoFromManifest($manifestPath)
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
            $this->sendMessage(
                Text::sprintf('LIB_SHACKINSTALLER_MANIFEST_NOT_FOUND', $relativePath),
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
    protected function getExtensionPath($type, $element, $group = '')
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
    protected function getExtensionId($type, $element, $group = '')
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
     * @param string $key
     * @param string $property
     * @param string $value
     *
     * @return void
     */
    protected function storeFeedbackForRelatedExtension(string $key, string $property, string $value)
    {
        $this->sendDebugMessage(sprintf(
            '%s<br>**** %s-%s-%s<br><br>',
            __METHOD__,
            $key,
            $property,
            $value
        ));

        if (empty($this->relatedExtensionFeedback[$key])) {
            $this->relatedExtensionFeedback[$key] = [];
        }

        $this->relatedExtensionFeedback[$key][$property] = $value;
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
    protected function getColumnsFromTable($table)
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
    protected function getIndexesFromTable($table)
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
    protected function addColumnsIfNotExists($table, $columns)
    {
        $db = $this->dbo;

        $existentColumns = $this->getColumnsFromTable($table);

        foreach ($columns as $column => $specification) {
            if (!in_array($column, $existentColumns)) {
                $db->setQuery(
                    "ALTER TABLE {$db->quoteName($table)} ADD COLUMN {$db->quoteName($column)} {$specification}"
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
    protected function addIndexesIfNotExists($table, $indexes)
    {
        $db = $this->dbo;

        $existentIndexes = $this->getIndexesFromTable($table);

        foreach ($indexes as $index => $specification) {
            if (!in_array($index, $existentIndexes)) {
                $db->setQuery(
                    "ALTER TABLE {$db->quoteName($table)} CREATE INDEX {$specification} ON {$index}"
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
    protected function dropColumnsIfExists($table, $columns)
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
    protected function tableExists(string $name)
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
    protected function getTables(?bool $force = false)
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
     * @param string $expression
     *
     * @return bool
     */
    protected function parseConditionalExpression($expression)
    {
        $expression = strtolower($expression);
        $terms      = explode('=', $expression);
        $firstTerm  = array_shift($terms);

        if (count($terms) == 0) {
            return $firstTerm == 'true' || $firstTerm == '1';

        } elseif (preg_match('/^(com_|plg_|mod_|lib_|tpl_|cli_)/', $firstTerm)) {
            // The first term is the name of an extension

            $info = $this->getExtensionInfoFromElement($firstTerm);

            $extension = $this->findExtension($info['type'], $firstTerm, $info['group']);

            // @TODO: compare the version, if specified, or different than *
            // @TODO: Check if the extension is enabled, not just installed

            if (!empty($extension)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get extension's info from element string, or extension name
     *
     * @param string $element The extension name, as element
     *
     * @return string[] An associative array with information about the extension
     */
    protected function getExtensionInfoFromElement($element)
    {
        $result = array_fill_keys(
            ['type', 'name', 'group', 'prefix', 'namespace'],
            null
        );

        $types = [
            'com' => 'component',
            'plg' => 'plugin',
            'mod' => 'module',
            'lib' => 'library',
            'tpl' => 'template',
            'cli' => 'cli'
        ];

        $element = explode('_', $element, 3);

        $prefix = $result['prefix'] = array_shift($element);
        $name   = array_pop($element);
        $group  = array_pop($element);

        if (array_key_exists($prefix, $types)) {
            $result = array_merge(
                $result,
                [
                    'type'  => $types[$prefix],
                    'group' => $group,
                    'name'  => $name
                ]
            );
        }

        $result['namespace'] = preg_replace_callback(
            '/^(os[a-z])(.*)/i',
            function ($matches) {
                return strtoupper($matches[1]) . $matches[2];
            },
            $name
        );

        return $result;
    }

    /**
     * Check if the actual version is at least the minimum target version.
     *
     * @param string  $actualVersion
     * @param string  $targetVersion
     * @param ?string $compare
     *
     * @return bool True, if the target version is greater than or equal to actual version
     */
    protected function validateTargetVersion($actualVersion, $targetVersion, $compare = null)
    {
        if ($targetVersion === '.*') {
            // Any version is valid
            return true;
        }

        $targetVersion = str_replace('*', '0', $targetVersion);

        return version_compare($actualVersion, $targetVersion, $compare ?: 'ge');
    }

    /**
     * @param string  $targetVersion
     * @param ?string $compare
     *
     * @return bool
     */
    protected function validatePreviousVersion($targetVersion, $compare = null)
    {
        if ($this->previousManifest) {
            $lastVersion = (string)$this->previousManifest->version;

            return $this->validateTargetVersion($lastVersion, $targetVersion, $compare);
        }

        return true;
    }

    /**
     * Get the extension name. If no custom name is set, uses the namespace
     *
     * @return string
     */
    protected function getName()
    {
        return (string)($this->manifest->alledia->name ?? $this->manifest->alledia->namespace);
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
     * @param ?string                 $type
     * @param mixed                   $default
     *
     * @return bool|string
     */
    protected function getXmlValue($element, $type = 'string', $default = null)
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

    /**
     * @param string $text
     * @param string $type
     *
     * @return void
     */
    protected function sendMessage(string $text, string $type = 'message')
    {
        if ($this->outputAllowed) {
            try {
                $this->app = $this->app ?: Factory::getApplication();
                $this->app->enqueueMessage($text, $type);

            } catch (Throwable $error) {
                // Give up trying to send a message normally
            }
        }
    }

    /**
     * @param Throwable $error
     * @param bool      $cancel
     *
     * @return void
     */
    protected function sendErrorMessage(Throwable $error, bool $cancel = true)
    {
        if ($cancel) {
            $this->cancelInstallation = true;
        }

        if ($this->outputAllowed) {
            $trace = $error->getTrace();
            $trace = array_shift($trace);

            if (empty($trace['class'])) {
                $caller = basename($trace['file']);

            } else {
                $className = explode('\\', $trace['class']);
                $caller    = array_pop($className);
            }
            $line     = $trace['line'];
            $function = $trace['function'] ?? null;
            $file     = $trace['file'];

            if ($function) {
                $message = sprintf('%s: %s<br>%s::%s() - %s', $line, $file, $caller, $function, $error->getMessage());
            } else {
                $message = sprintf('%s:%s (%s) - %s', $line, $caller, $file, $error->getMessage());
            }

            $this->sendMessage($message, 'error');
        }
    }

    /**
     * @param string $text
     *
     * @return void
     */
    protected function sendDebugMessage(string $text)
    {
        if ($this->debug) {
            $this->sendMessage($text, 'Debug-' . get_class($this));
        }
    }

    /**
     * @param string $type
     *
     * @return void
     */
    final protected function displayWelcome(string $type)
    {
        if ($this->installer->getPath('parent') || !$this->outputAllowed) {
            // Either a related extension or installing from frontend
            return;
        }

        $license = $this->getLicense();
        $name    = $this->getName() . ($license->isPro() ? ' Pro' : '');

        // Get the footer content
        $this->footer = '';

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

        if (!empty($footerElement)) {
            if (!class_exists('\\JFormFieldCustomFooter')) {
                // Custom footer field is not (and should not be) automatically loaded
                $customFooterPath = $license->getExtensionPath() . '/form/fields/customfooter.php';

                if (is_file($customFooterPath)) {
                    include_once $customFooterPath;
                }
            }

            if (class_exists('\\JFormFieldCustomFooter')) {
                $field                = new JFormFieldCustomFooter();
                $field->fromInstaller = true;
                $this->footer         = $field->getInputUsingCustomElement($footerElement[0]);

                unset($field, $footerElement);
            }
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
            if ($licensesManagerExtension = new Licensed('osmylicensesmanager', 'plugin', 'system')) {
                if (isset($licensesManagerExtension->params)) {
                    $this->licenseKey = $licensesManagerExtension->params->get('license-keys', '');
                } else {
                    $this->licenseKey = '';
                }

                $this->isLicensesManagerInstalled = true;
            }
        }

        // Welcome message
        if ($type === 'install') {
            $string = 'LIB_SHACKINSTALLER_THANKS_INSTALL';
        } else {
            $string = 'LIB_SHACKINSTALLER_THANKS_UPDATE';
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
    }
}

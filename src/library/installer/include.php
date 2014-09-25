<?php
/**
 * @package   AllediaInstaller
 * @contact   www.ostraining.com, support@ostraining.com
 * @copyright 2013-2014 Open Source Training, LLC. All rights reserved
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die();

if (!defined('ALLEDIA_INSTALLER_LOADED')) {
    define('ALLEDIA_INSTALLER_LOADED', 1);

    define('ALLEDIA_INSTALLER_LIBRARY_PATH', __DIR__);
    define('ALLEDIA_INSTALLER_EXTENSION_PATH', realpath(__DIR__ . '/../../'));

    require_once 'InstallerAbstract.php';
}

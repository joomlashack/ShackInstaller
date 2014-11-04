<?php
/**
 * @package   AllediaInstaller
 * @contact   www.alledia.com, hello@alledia.com
 * @copyright 2014 Alledia.com, All rights reserved
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die();

// Setup autoloaded libraries
if (! class_exists('AllediaInstallerPsr4AutoLoader')) {
    require_once __DIR__ . '/AllediaInstallerPsr4AutoLoader.php';
}

$loader = new AllediaInstallerPsr4AutoLoader();
$loader->register();
$loader->addNamespace('Alledia\Installer', __DIR__);

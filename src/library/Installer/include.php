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

use Alledia\Installer\AutoLoader;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die();

$autoLoaderClass = '\\Alledia\\Installer\\AutoLoader';
if (class_exists($autoLoaderClass)) {
    // If the installer autoloader is already loaded, this is a problem

    Factory::getLanguage()->load('lib_shackinstaller.sys', realpath(__DIR__ . '/../..'));

    // Extract the most relevant parts of the the source path
    $autoloader = new \ReflectionClass($autoLoaderClass);

    $path = str_replace(JPATH_ROOT . '/', '', $autoloader->getFileName());

    $pathParts = explode('/library/', $path);
    $source    = array_shift($pathParts);

    Factory::getApplication()->enqueueMessage(Text::sprintf('LIB_SHACKINSTALLER_ABORT_INTERFERENCE', $source), 'error');

    return false;
}

require_once __DIR__ . '/AutoLoader.php';

AutoLoader::register('Alledia\\Installer', __DIR__);

return true;

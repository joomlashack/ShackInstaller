<?php
/**
 * @package   OSEmbed
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2020-2021 Joomlashack.com. All rights reserved
 * @license   https://www.gnu.org/licenses/gpl.html GNU/GPL
 *
 * This file is part of OSEmbed.
 *
 * OSEmbed is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * OSEmbed is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OSEmbed.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Alledia\Installer;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die();

trait TraitInstallerCheck
{
    /**
     * @var bool
     */
    protected $cancelInstallation = false;

    /**
     * @param InstallerAdapter $parent
     *
     * @return bool
     * @throws \Exception
     */
    protected function checkInheritance(InstallerAdapter $parent): bool
    {
        Factory::getLanguage()->load('lib_shackinstaller.sys', realpath(__DIR__ . '/../..'));

        $parentClasses   = class_parents($this);
        $scriptClassName = array_pop($parentClasses);
        $scriptClass     = new \ReflectionClass($scriptClassName);

        $sourcePath    = dirname($scriptClass->getFileName());
        $sourceBase    = strpos($sourcePath, JPATH_PLUGINS) === 0 ? 3 : 2;
        $sourceVersion = AbstractScript::VERSION ?? '0.0.0';

        $sourcePath = $this->cleanPath($sourcePath);
        $targetPath = $this->cleanPath(SHACK_INSTALLER_BASE);

        if ($sourcePath != $targetPath && version_compare($sourceVersion, SHACK_INSTALLER_VERSION, 'lt')) {
            $source = join('/', array_slice(explode('/', $sourcePath), 0, $sourceBase));

            $errorMessage = 'LIB_SHACKINSTALLER_ABORT_'
                . ($parent->getRoute() == 'uninstall' ? 'UNINSTALL' : 'INSTALL');

            Factory::getApplication()->enqueueMessage(Text::sprintf($errorMessage, $source), 'error');

            $this->cancelInstallation = true;

            return false;
        }

        return true;
    }

    /**
     * @param string $path
     *
     * @return string
     */
    protected function cleanPath(string $path): string
    {
        return str_replace(DIRECTORY_SEPARATOR, '/', str_replace(JPATH_ROOT . '/', '', $path));
    }
}

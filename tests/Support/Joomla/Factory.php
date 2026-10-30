<?php

/**
 * @package   ShackInstaller
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2016-2026 Joomlashack.com. All rights reserved
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

namespace Joomla\CMS;

use Joomla\CMS\Application\CMSApplicationInterface;

/**
 * Stand-in for the Joomla class, for unit tests only. Tests set the application.
 */
class Factory
{
    /**
     * @var ?CMSApplicationInterface
     */
    public static $application = null;

    /**
     * @return CMSApplicationInterface
     * @throws \Exception
     */
    public static function getApplication()
    {
        if (static::$application === null) {
            throw new \Exception('Application Instantiation Error');
        }

        return static::$application;
    }
}

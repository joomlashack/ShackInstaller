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

// phpcs:disable PSR1.Files.SideEffects

// Library files exit unless loaded from within Joomla
define('_JEXEC', 1);

// The fixture site in Support/Data/site stands in for a Joomla install
define('JPATH_ROOT', __DIR__ . '/Support/Data/site');
define('JPATH_SITE', JPATH_ROOT);
define('JPATH_ADMINISTRATOR', JPATH_ROOT . '/administrator');
define('JPATH_BASE', JPATH_ADMINISTRATOR);
define('JPATH_PLUGINS', JPATH_ROOT . '/plugins');
define('JPATH_MANIFESTS', JPATH_ADMINISTRATOR . '/manifests');
define('JVERSION', '6.2.0');

// Leave PHP errors to Codeception instead of the installer's error handler
$reportErrors = 0;

require_once dirname(__DIR__) . '/src/library/Installer/include.php';

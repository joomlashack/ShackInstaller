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

/*
 * Constants Joomla defines at runtime. The values don't matter to PHPStan,
 * it only needs to know the constants exist.
 */
$constants = [
    '_JEXEC',
    'JPATH_ADMINISTRATOR',
    'JPATH_BASE',
    'JPATH_MANIFESTS',
    'JPATH_PLUGINS',
    'JPATH_ROOT',
    'JPATH_SITE',
    'JVERSION',
];

foreach ($constants as $constant) {
    if (defined($constant) == false) {
        define($constant, '');
    }
}

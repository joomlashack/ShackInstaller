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

use Tests\Support\Paths;

require_once __DIR__ . '/../Support/Paths.php';

/*
 * The Joomla checkout the source needs for class discovery, found the same
 * way as by the build. Use the newest supported Joomla.
 */
$joomla = Paths::joomla();

$folders = [
    $joomla . '/libraries/src',
    $joomla . '/libraries/vendor',
    $joomla . '/administrator/components/com_plugins/src',
];

return [
    'parameters' => [
        // Joomla 3 has no namespaced component folders
        'scanDirectories' => array_values(array_filter($folders, 'is_dir')),
    ],
];

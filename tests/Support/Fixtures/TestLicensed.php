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

namespace Tests\Support\Fixtures;

use Alledia\Installer\Extension\Licensed;

/**
 * A Licensed extension that reads manifests from the fixture site and
 * skips the database lookup
 */
class TestLicensed extends Licensed
{
    /**
     * @param string  $namespace
     * @param string  $type
     * @param ?string $folder
     */
    public function __construct(string $namespace, string $type, ?string $folder = '')
    {
        parent::__construct($namespace, $type, $folder, JPATH_SITE);
    }

    /**
     * @inheritDoc
     */
    protected function getDataFromDatabase()
    {
        $this->params = new \Joomla\Registry\Registry();
    }
}

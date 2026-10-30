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

namespace Joomla\CMS\Table;

/**
 * Stand-in for the Joomla class, for unit tests only. Rows come from
 * Extension::$installed, which tests set to the extensions the site has.
 */
class Extension extends Table
{
    /**
     * @var array[] Keyed by extension_id
     */
    public static array $installed = [];

    /**
     * @var array
     */
    protected array $row = [];

    /**
     * @param array $rows Rows of #__extensions, without extension_id
     *
     * @return void
     */
    public static function install(array $rows): void
    {
        static::$installed = [];

        foreach ($rows as $index => $row) {
            $id = $index + 1;

            static::$installed[$id] = $row + [
                'extension_id' => $id,
                'folder'       => '',
                'enabled'      => 0,
            ];
        }
    }

    /**
     * @param array $options
     *
     * @return int|false
     */
    public function find($options = [])
    {
        foreach (static::$installed as $id => $row) {
            foreach ($options as $column => $value) {
                if ((string)($row[$column] ?? '') !== (string)$value) {
                    continue 2;
                }
            }

            return $id;
        }

        return false;
    }

    /**
     * @param int $keys
     *
     * @return bool
     */
    public function load($keys = null)
    {
        $this->row = static::$installed[$keys] ?? [];

        return $this->row !== [];
    }

    /**
     * @param string $property
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get($property, $default = null)
    {
        return $this->row[$property] ?? $default;
    }

    /**
     * @return bool
     */
    public function publish()
    {
        static::$installed[$this->row['extension_id']]['enabled'] = 1;

        return true;
    }

    /**
     * @return string
     */
    public function getError()
    {
        return 'Extension not loaded';
    }
}

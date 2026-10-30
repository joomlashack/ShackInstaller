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

use Alledia\Installer\AbstractScript;
use Joomla\CMS\Installer\InstallerAdapter;
use ReflectionClass;

/**
 * An install script that skips the Joomla-bound constructor, records which
 * custom hooks ran, and exposes protected members to the tests
 */
class TestScript extends AbstractScript
{
    /**
     * @var string[]
     */
    public array $calls = [];

    /**
     * Hook that throws instead of returning
     *
     * @var ?string
     */
    public ?string $throwIn = null;

    /**
     * @param array $properties Protected properties to preset
     *
     * @return static
     */
    public static function create(array $properties = []): self
    {
        $script = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();

        foreach ($properties as $name => $value) {
            $script->{$name} = $value;
        }

        return $script;
    }

    /**
     * @param string $method
     * @param mixed  ...$arguments
     *
     * @return mixed
     */
    public function call(string $method, ...$arguments)
    {
        return $this->{$method}(...$arguments);
    }

    /**
     * @param string $property
     *
     * @return mixed
     */
    public function get(string $property)
    {
        return $this->{$property};
    }

    /**
     * @inheritDoc
     */
    protected function customPreFlight(string $type, InstallerAdapter $parent): bool
    {
        return $this->record('preflight');
    }

    /**
     * @inheritDoc
     */
    protected function customInstall(InstallerAdapter $parent): bool
    {
        return $this->record('install');
    }

    /**
     * @inheritDoc
     */
    protected function customDiscoverInstall(InstallerAdapter $parent): bool
    {
        return $this->record('discover');
    }

    /**
     * @param string $hook
     *
     * @return bool
     * @throws \Exception
     */
    protected function record(string $hook): bool
    {
        $this->calls[] = $hook;

        if ($this->throwIn === $hook) {
            throw new \Exception('Failed in ' . $hook);
        }

        return true;
    }
}

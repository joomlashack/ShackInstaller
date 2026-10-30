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

namespace Tests\Unit;

use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use Tests\Support\Fixtures\TestScript;

class VersionTest extends Unit
{
    public static function targetVersionProvider(): array
    {
        return [
            'any version'       => ['1.0.0', '.*', true],
            'wildcard major'    => ['4.4.1', '4.*', true],
            'below wildcard'    => ['3.10.12', '4.*', false],
            'equal'             => ['8.1.0', '8.1.0', true],
            'higher'            => ['8.5.0', '8.1', true],
            'lower'             => ['7.4.33', '8.1', false],
            'wildcard 3.10 min' => ['3.10.12', '3.10.*', true],
        ];
    }

    #[DataProvider('targetVersionProvider')]
    public function testValidateTargetVersion(string $actual, string $target, bool $expected)
    {
        $script = TestScript::create();

        $this->assertSame($expected, $script->call('validateTargetVersion', $actual, $target));
    }

    public function testValidateTargetVersionTakesComparisonOperator()
    {
        $script = TestScript::create();

        $this->assertTrue($script->call('validateTargetVersion', '1.0.0', '2.0', 'lt'));
        $this->assertFalse($script->call('validateTargetVersion', '3.0.0', '2.0', 'lt'));
    }

    public function testPreviousVersionPassesOnNewInstall()
    {
        $script = TestScript::create();

        $this->assertTrue($script->call('validatePreviousVersion', '9.0'));
    }

    public function testPreviousVersionUsesInstalledManifest()
    {
        $script = TestScript::create([
            'previousManifest' => simplexml_load_string('<extension><version>1.2.0</version></extension>'),
        ]);

        $this->assertTrue($script->call('validatePreviousVersion', '1.1'));
        $this->assertFalse($script->call('validatePreviousVersion', '1.3'));
    }
}

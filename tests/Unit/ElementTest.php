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

class ElementTest extends Unit
{
    protected function createScript(): TestScript
    {
        return TestScript::create([
            'manifest' => simplexml_load_file(
                JPATH_ADMINISTRATOR . '/components/com_shacktest/shacktest.xml'
            ),
            'type'     => 'component',
            'group'    => '',
        ]);
    }

    public function testFullElementDefaultsToThisExtension()
    {
        $this->assertSame('com_shacktest', $this->createScript()->call('getFullElement'));
    }

    public static function fullElementProvider(): array
    {
        return [
            'plugin'  => ['plugin', 'foo', 'system', 'plg_system_foo'],
            'module'  => ['module', 'foo', null, 'mod_foo'],
            'library' => ['library', 'foo', null, 'lib_foo'],
            'file'    => ['file', 'foo', null, 'file_foo'],
            'package' => ['package', 'foo', null, 'pkg_foo'],
        ];
    }

    #[DataProvider('fullElementProvider')]
    public function testFullElement(string $type, string $element, ?string $group, string $expected)
    {
        $this->assertSame($expected, $this->createScript()->call('getFullElement', $type, $element, $group));
    }

    public static function extensionInfoProvider(): array
    {
        return [
            'plugin'          => ['plg_system_foo', 'plugin', 'system', 'foo', 'plg'],
            'component'       => ['com_foo', 'component', null, 'foo', 'com'],
            'module'          => ['mod_foo', 'module', null, 'foo', 'mod'],
            'unknown prefix'  => ['xyz_foo', null, null, null, 'xyz'],
            'component with underscore' => ['com_my_comp', 'component', null, 'my_comp', 'com'],
            'plugin with underscore'    => ['plg_content_my_plugin', 'plugin', 'content', 'my_plugin', 'plg'],
        ];
    }

    #[DataProvider('extensionInfoProvider')]
    public function testExtensionInfoFromElement(
        string $element,
        ?string $type,
        ?string $group,
        ?string $name,
        string $prefix
    ) {
        $info = $this->createScript()->call('getExtensionInfoFromElement', $element);

        $this->assertSame($type, $info['type']);
        $this->assertSame($group, $info['group']);
        $this->assertSame($name, $info['name']);
        $this->assertSame($prefix, $info['prefix']);
    }

    public function testExtensionInfoCapitalisesOsNamespaces()
    {
        $info = $this->createScript()->call('getExtensionInfoFromElement', 'com_osdownloads');

        $this->assertSame('OSDownloads', $info['namespace']);
    }

    public static function extensionPathProvider(): array
    {
        return [
            'component'          => ['component', 'shacktest', null, '/administrator/components/com_shacktest'],
            'prefixed component' => ['component', 'com_shacktest', null, '/administrator/components/com_shacktest'],
            'plugin'             => ['plugin', 'shackfree', 'system', '/plugins/system/shackfree'],
            'module'             => ['module', 'foo', null, '/modules/mod_foo'],
            'prefixed module'    => ['module', 'mod_foo', null, '/modules/mod_foo'],
            'template'           => ['template', 'tpl_cassiopeia', null, '/templates/cassiopeia'],
            'library'            => ['library', 'foo', null, '/libraries/foo'],
            'file'               => ['file', 'foo', null, '/administrator/manifests/files/'],
        ];
    }

    #[DataProvider('extensionPathProvider')]
    public function testExtensionPath(string $type, string $element, ?string $group, string $expected)
    {
        $this->assertSame(
            JPATH_SITE . $expected,
            $this->createScript()->call('getExtensionPath', $type, $element, $group)
        );
    }

    public function testConditionalExpressionLiterals()
    {
        $script = $this->createScript();

        $this->assertTrue($script->call('parseConditionalExpression', 'true'));
        $this->assertTrue($script->call('parseConditionalExpression', '1'));
        $this->assertFalse($script->call('parseConditionalExpression', 'false'));
        $this->assertFalse($script->call('parseConditionalExpression', 'anything=else'));
    }

    public function testNameFallsBackToNamespace()
    {
        $this->assertSame('Shack Test', $this->createScript()->call('getName'));

        $script = TestScript::create([
            'manifest' => simplexml_load_string('<extension><alledia><namespace>OnlyNs</namespace></alledia></extension>'),
        ]);
        $this->assertSame('OnlyNs', $script->call('getName'));
    }
}

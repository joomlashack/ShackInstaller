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
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Extension;
use Tests\Support\Fixtures\MessageQueue;
use Tests\Support\Fixtures\TestScript;

class ConditionalPublishTest extends Unit
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::$application = new MessageQueue();

        Extension::install([
            [
                'type'           => 'component',
                'element'        => 'com_virtuemart',
                'manifest_cache' => json_encode(['version' => '4.2.18']),
            ],
            ['type' => 'component', 'element' => 'com_my_shop'],
            ['type' => 'module', 'element' => 'mod_menu'],
            ['type' => 'plugin', 'element' => 'shackfree', 'folder' => 'system'],
            ['type' => 'plugin', 'element' => 'my_plugin', 'folder' => 'content'],
            ['type' => 'library', 'element' => 'allediaframework'],
            ['type' => 'template', 'element' => 'cassiopeia'],
        ]);
    }

    protected function tearDown(): void
    {
        Extension::$installed = [];
        Factory::$application = null;

        parent::tearDown();
    }

    public static function expressionProvider(): array
    {
        return [
            'true'                        => ['true', true],
            'TRUE'                        => ['TRUE', true],
            'one'                         => ['1', true],
            'false'                       => ['false', false],
            'zero'                        => ['0', false],
            'empty'                       => ['', false],
            'installed, colon'            => ['com_virtuemart:*', true],
            'installed, equals'           => ['com_virtuemart=*', true],
            'installed, no version'       => ['com_virtuemart', true],
            'installed, padded'           => ['  com_virtuemart:*  ', true],
            'not installed'               => ['com_k2:*', false],
            'minimum version met'         => ['com_virtuemart:4.2', true],
            'minimum version wildcard'    => ['com_virtuemart:4.*', true],
            'minimum version not met'     => ['com_virtuemart:5.*', false],
            'no version in manifest'      => ['com_my_shop:1.0', false],
            'component with underscore'   => ['com_my_shop:*', true],
            'module'                      => ['mod_menu:*', true],
            'plugin'                      => ['plg_system_shackfree:*', true],
            'plugin with underscore'      => ['plg_content_my_plugin:*', true],
            'plugin in other group'       => ['plg_content_shackfree:*', false],
            'library'                     => ['lib_allediaframework:*', true],
            'template'                    => ['tpl_cassiopeia:*', true],
            'unknown prefix'              => ['xyz_virtuemart:*', false],
            'not an extension'            => ['virtuemart:*', false],
        ];
    }

    #[DataProvider('expressionProvider')]
    public function testParseConditionalExpression(string $expression, bool $expected)
    {
        $this->assertSame($expected, TestScript::create()->call('parseConditionalExpression', $expression));
    }

    /**
     * The relatedExtensions block from OSMap Pro's manifest, trimmed to what matters here
     */
    protected function getRelated(string $defaultPublish = ''): \SimpleXMLElement
    {
        return simplexml_load_string(
            '<relatedExtensions' . ($defaultPublish ? ' publish="' . $defaultPublish . '"' : '') . '>'
            . '<extension type="plugin" group="osmap" publish="com_virtuemart:*" element="com_virtuemart"/>'
            . '<extension type="plugin" group="osmap" publish="com_k2:*" element="com_k2"/>'
            . '<extension type="plugin" group="osmap" publish="true" element="joomla"/>'
            . '<extension type="plugin" group="osmap" publish="0" element="weblinks"/>'
            . '<extension type="plugin" group="osmap" element="contact"/>'
            . '</relatedExtensions>'
        );
    }

    public function testRelatedExtensionsPublishOnlyWhenConditionIsMet()
    {
        $script  = TestScript::create();
        $related = $this->getRelated();
        $default = $script->call('getPublishState', $related['publish']);

        $published = [];
        foreach ($related->extension as $extension) {
            $published[(string)$extension['element']] = $script->call(
                'getPublishState',
                $extension['publish'],
                $default
            );
        }

        $this->assertSame(
            [
                'com_virtuemart' => true,
                'com_k2'         => false,
                'joomla'         => true,
                'weblinks'       => false,
                'contact'        => false,
            ],
            $published
        );
    }

    public function testDefaultPublishAppliesOnlyWithoutAttribute()
    {
        $script  = TestScript::create();
        $related = $this->getRelated('true');
        $default = $script->call('getPublishState', $related['publish']);

        $this->assertTrue($default);

        $extensions = $related->extension;
        $this->assertTrue($script->call('getPublishState', $extensions[4]['publish'], $default), 'No attribute');
        $this->assertFalse($script->call('getPublishState', $extensions[3]['publish'], $default), 'publish="0"');
        $this->assertFalse($script->call('getPublishState', $extensions[1]['publish'], $default), 'Not installed');
    }

    public static function thisPluginProvider(): array
    {
        return [
            'installed'     => ['com_virtuemart:*', 1],
            'not installed' => ['com_k2:*', 0],
            'true'          => ['true', 1],
        ];
    }

    #[DataProvider('thisPluginProvider')]
    public function testPublishThisPlugin(string $publish, int $enabled)
    {
        $script = TestScript::create([
            'manifest' => simplexml_load_string(
                '<extension type="plugin" group="system"><alledia>'
                . '<element publish="' . $publish . '">shackfree</element>'
                . '</alledia></extension>'
            ),
        ]);

        $script->call('publishThisPlugin');

        $this->assertSame($enabled, Extension::$installed[4]['enabled']);
    }
}

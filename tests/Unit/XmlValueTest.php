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

use Codeception\Test\Unit;
use SimpleXMLElement;
use Tests\Support\Fixtures\TestScript;

class XmlValueTest extends Unit
{
    protected TestScript $script;

    protected SimpleXMLElement $xml;

    protected function setUp(): void
    {
        parent::setUp();

        $this->script = TestScript::create();
        $this->xml    = simplexml_load_string('<item yes="true" one="1" no="0" text="  padded  ">  body  </item>');
    }

    public function testBooleansAcceptTrueAndOne()
    {
        $this->assertTrue($this->script->call('getXmlValue', $this->xml['yes'], 'bool'));
        $this->assertTrue($this->script->call('getXmlValue', $this->xml['one'], 'boolean'));
        $this->assertFalse($this->script->call('getXmlValue', $this->xml['no'], 'bool'));
    }

    public function testMissingBooleanUsesDefault()
    {
        $this->assertFalse($this->script->call('getXmlValue', $this->xml['missing'], 'bool'));
        $this->assertTrue($this->script->call('getXmlValue', $this->xml['missing'], 'bool', true));
    }

    public function testStringsAreTrimmed()
    {
        $this->assertSame('padded', $this->script->call('getXmlValue', $this->xml['text']));
        $this->assertSame('body', $this->script->call('getXmlValue', $this->xml));
    }

    public function testMissingStringUsesDefault()
    {
        $this->assertNull($this->script->call('getXmlValue', $this->xml['missing']));
        $this->assertSame('fallback', $this->script->call('getXmlValue', $this->xml['missing'], 'string', 'fallback'));
    }
}

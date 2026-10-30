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

use Alledia\Installer\AutoLoader;
use Codeception\Test\Unit;
use Tests\AutoLoad\Gadgets\Gear;
use Tests\AutoLoad\Gadgets\Widget;

class AutoLoaderTest extends Unit
{
    public function testRegisterLoadsPsr4Classes()
    {
        AutoLoader::register('Tests\\AutoLoad', codecept_data_dir('autoload/first'));

        $this->assertTrue(class_exists(Widget::class));
        $this->assertSame('first', Widget::SOURCE);
    }

    public function testPrependedFolderIsSearchedFirst()
    {
        AutoLoader::register('Tests\\AutoLoad', codecept_data_dir('autoload/first'));
        AutoLoader::register('Tests\\AutoLoad', codecept_data_dir('autoload/second'), true);

        $this->assertSame('second', Gear::SOURCE);
    }

    public function testUnknownClassIsNotLoaded()
    {
        AutoLoader::register('Tests\\AutoLoad', codecept_data_dir('autoload/first'));

        $this->assertFalse(class_exists('Tests\\AutoLoad\\Gadgets\\Missing'));
    }

    public function testCamelCaseClassesMapToLowercaseFolders()
    {
        AutoLoader::registerCamelBase('ShackCamel', codecept_data_dir('autoload/camel'));

        $this->assertTrue(class_exists('ShackCamelWidgetThing'));
    }

    public function testCamelBaseMustExist()
    {
        $this->expectException(\Exception::class);

        AutoLoader::registerCamelBase('ShackMissing', codecept_data_dir('autoload/missing'));
    }
}

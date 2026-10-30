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
use Tests\Support\Fixtures\TestLicensed;

class ExtensionTest extends Unit
{
    public function testProComponent()
    {
        $extension = new TestLicensed('shacktest', 'component');
        $path      = JPATH_SITE . '/administrator/components/com_shacktest';

        $this->assertTrue($extension->isPro());
        $this->assertFalse($extension->isFree());
        $this->assertSame($path, $extension->getExtensionPath());
        $this->assertSame($path . '/library', $extension->getLibraryPath());
        $this->assertSame($path . '/library/Pro', $extension->getProLibraryPath());
        $this->assertSame('com_shacktest', $extension->getFullElement());
        $this->assertSame('com_shacktest', $extension->getElementToDb());
        $this->assertSame('1.2.3', (string)$extension->getManifest()->version);
        $this->assertFalse($extension->getConfig());
    }

    public function testFreePlugin()
    {
        $extension = new TestLicensed('shackfree', 'plugin', 'system');

        $this->assertTrue($extension->isFree());
        $this->assertSame(JPATH_SITE . '/plugins/system/shackfree', $extension->getExtensionPath());
        $this->assertSame('plg_system_shackfree', $extension->getFullElement());
        $this->assertSame('shackfree', $extension->getElementToDb());
    }

    public function testLibraryManifestComesFromManifestsFolder()
    {
        $extension = new TestLicensed('shacklib', 'library');

        $this->assertSame(JPATH_MANIFESTS . '/libraries/shacklib.xml', $extension->getManifestPath());
        $this->assertSame('3.0.0', (string)$extension->getManifest()->version);
        $this->assertTrue($extension->isFree(), 'No <alledia> tag means free');
    }

    public function testMissingExtension()
    {
        $extension = new TestLicensed('nothere', 'component');

        $this->assertNull($extension->getManifest());
        $this->assertTrue($extension->isFree());
        $this->assertSame('', $extension->getName());
        $this->assertFalse($extension->loadLibrary());
    }
}

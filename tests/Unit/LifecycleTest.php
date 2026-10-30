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
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Tests\Support\Fixtures\MessageQueue;
use Tests\Support\Fixtures\TestScript;

class LifecycleTest extends Unit
{
    protected MessageQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::$application = $this->queue = new MessageQueue();
    }

    protected function tearDown(): void
    {
        Factory::$application = null;

        parent::tearDown();
    }

    /**
     * @param string $alledia Contents of the <alledia> tag
     * @param array  $properties
     *
     * @return TestScript
     */
    protected function createScript(string $alledia = '', array $properties = []): TestScript
    {
        return TestScript::create($properties + [
            'app'      => $this->queue,
            'manifest' => simplexml_load_string(
                '<extension type="component"><alledia><namespace>ShackTest</namespace>'
                . $alledia
                . '</alledia></extension>'
            ),
        ]);
    }

    public function testInstallRunsCustomInstall()
    {
        $script = $this->createScript();

        $this->assertTrue($script->install(new InstallerAdapter('install')));
        $this->assertSame(['install'], $script->calls);
    }

    public function testDiscoverRouteAlsoRunsCustomDiscoverInstall()
    {
        $script = $this->createScript();

        $this->assertTrue($script->install(new InstallerAdapter('discover_install')));
        $this->assertSame(['install', 'discover'], $script->calls);
    }

    public function testDiscoverInstallRunsEachHookOnce()
    {
        $script = $this->createScript();

        $this->assertTrue($script->discover_install(new InstallerAdapter('discover_install')));
        $this->assertSame(['install', 'discover'], $script->calls);
    }

    public function testExceptionInHookCancelsInstall()
    {
        $script = $this->createScript('', ['throwIn' => 'install']);

        $this->assertFalse($script->install(new InstallerAdapter('discover_install')));
        $this->assertSame(['install'], $script->calls);
        $this->assertTrue($script->get('cancelInstallation'));

        $errors = $this->queue->getMessages('error');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Failed in install', $errors[0]);
    }

    public function testPreFlightStopsWhenAlreadyCancelled()
    {
        $script = $this->createScript('', ['cancelInstallation' => true]);

        $this->assertFalse($script->preFlight('install', new InstallerAdapter()));
        $this->assertSame([], $script->calls);
    }

    public function testPreFlightRejectsUnsupportedPhp()
    {
        $script = $this->createScript('<phpminimum>99.0</phpminimum>');

        $this->assertFalse($script->preFlight('install', new InstallerAdapter()));
        $this->assertSame([], $script->calls);
        $this->assertTrue($script->get('cancelInstallation'));
        $this->assertSame(['LIB_SHACKINSTALLER_WRONG_PHP|ShackTest|99.0'], $this->queue->getMessages('warning'));
    }

    public function testPreFlightRejectsUnsupportedJoomla()
    {
        $script = $this->createScript('<targetplatform>7.*</targetplatform>');

        $this->assertFalse($script->preFlight('install', new InstallerAdapter()));
        $this->assertSame(['LIB_SHACKINSTALLER_WRONG_PLATFORM|ShackTest|7.x'], $this->queue->getMessages('warning'));
    }

    public function testPreFlightAcceptsSupportedVersions()
    {
        $script = $this->createScript('<targetplatform>3.10.*</targetplatform><phpminimum>7.4</phpminimum>');

        $this->assertTrue($script->preFlight('install', new InstallerAdapter()));
        $this->assertSame(['preflight'], $script->calls);
        $this->assertFalse($script->get('cancelInstallation'));
    }

    public function testPreFlightSkipsVersionChecksForDiscoverInstall()
    {
        $script = $this->createScript('<phpminimum>99.0</phpminimum>');

        $this->assertTrue($script->preFlight('discover_install', new InstallerAdapter('discover_install')));
        $this->assertSame(['preflight'], $script->calls);
    }

    public function testPostFlightReportsCancelledInstall()
    {
        $script = $this->createScript('', ['cancelInstallation' => true]);

        $script->postFlight('install', new InstallerAdapter());

        $this->assertSame(['LIB_SHACKINSTALLER_INSTALL_CANCELLED'], $this->queue->getMessages('warning'));
    }
}

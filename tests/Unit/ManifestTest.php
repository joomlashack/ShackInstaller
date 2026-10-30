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
use Tests\Support\Fixtures\MessageQueue;
use Tests\Support\Fixtures\TestScript;

class ManifestTest extends Unit
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

    public function testInfoHasAttributesAndSimpleElements()
    {
        $info = TestScript::create()->call(
            'getInfoFromManifest',
            JPATH_ADMINISTRATOR . '/components/com_shacktest/shacktest.xml'
        );

        $this->assertSame('component', $info->get('type'));
        $this->assertSame('upgrade', $info->get('method'));
        $this->assertSame('1.2.3', $info->get('version'));
        $this->assertSame('Joomlashack', $info->get('author'));
        $this->assertNull($info->get('alledia'), 'Elements with children are skipped');
        $this->assertSame([], $this->queue->messages);
    }

    public function testMissingManifestIsReported()
    {
        $info = TestScript::create()->call('getInfoFromManifest', JPATH_SITE . '/missing.xml');

        $this->assertSame([], $info->toArray());
        $this->assertSame(
            ['LIB_SHACKINSTALLER_MANIFEST_NOT_FOUND|missing.xml'],
            $this->queue->getMessages('error')
        );
    }

    public function testUnparsableManifestIsReported()
    {
        // Keep libxml's parse warnings out of the test's error handling
        $previous = libxml_use_internal_errors(true);

        $info = TestScript::create()->call('getInfoFromManifest', codecept_data_dir('manifests/broken.xml'));

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->assertSame([], $info->toArray());
        $this->assertCount(1, $this->queue->getMessages('error'));
    }
}

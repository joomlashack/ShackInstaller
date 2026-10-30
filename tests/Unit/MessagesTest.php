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

class MessagesTest extends Unit
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

    public function testMessagesAreQueuedInAdministrator()
    {
        $script = TestScript::create();

        $script->call('sendMessage', 'Hello', 'notice');

        $this->assertSame([['notice', 'Hello']], $this->queue->messages);
    }

    public function testMessagesAreDroppedOutsideAdministrator()
    {
        $script = TestScript::create(['outputAllowed' => false]);

        $script->call('sendMessage', 'Hello');

        $this->assertSame([], $this->queue->messages);
    }

    public function testDebugMessagesNeedDebugMode()
    {
        $script = TestScript::create();
        $script->call('sendDebugMessage', 'Quiet');
        $this->assertSame([], $this->queue->messages);

        $script = TestScript::create(['debug' => true]);
        $script->call('sendDebugMessage', 'Loud');
        $this->assertSame([['debug', 'Loud']], $this->queue->messages);
    }

    public function testErrorMessageShowsWhereTheErrorWasThrown()
    {
        $script = TestScript::create();

        try {
            // Thrown from a callback, so the first trace frame has no file or line
            array_map(
                function () {
                    throw new \RuntimeException('Callback failed');
                },
                [1]
            );
        } catch (\RuntimeException $error) {
            $script->call('sendErrorMessage', $error);
        }

        $errors = $this->queue->getMessages('error');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Callback failed', $errors[0]);
        $this->assertStringContainsString(__FILE__, $errors[0]);
        $this->assertTrue($script->get('cancelInstallation'));
    }

    public function testErrorMessageCanLeaveInstallRunning()
    {
        $script = TestScript::create();

        $script->call('sendErrorMessage', new \Exception('Not fatal'), false);

        $this->assertFalse($script->get('cancelInstallation'));
        $this->assertCount(1, $this->queue->getMessages('error'));
    }
}

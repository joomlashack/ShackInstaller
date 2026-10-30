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

use Alledia\Installer\AbstractScript;
use Codeception\Attribute\DataProvider;
use Codeception\Test\Unit;
use Joomla\CMS\Factory;
use Tests\Support\Fixtures\MessageQueue;

class ErrorHandlerTest extends Unit
{
    protected MessageQueue $queue;

    protected int $errorReporting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->errorReporting = error_reporting();

        Factory::$application = $this->queue = new MessageQueue();
    }

    protected function tearDown(): void
    {
        error_reporting($this->errorReporting);

        Factory::$application = null;

        parent::tearDown();
    }

    public function testErrorsAreReportedAsWarnings()
    {
        error_reporting(E_ALL);

        $this->assertTrue(AbstractScript::errorHandler(E_WARNING, 'Something broke', '/path/file.php', 12));

        $messages = $this->queue->getMessages('warning');
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('E_WARNING: Something broke', $messages[0]);
        $this->assertStringContainsString('(12) /path/file.php', $messages[0]);
    }

    public static function suppressedProvider(): array
    {
        return [
            'php 7 @ operator' => [0],
            'php 8 @ operator' => [E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR | E_PARSE],
        ];
    }

    #[DataProvider('suppressedProvider')]
    public function testSuppressedErrorsAreLeftToPhp(int $level)
    {
        error_reporting($level);
        $handled = AbstractScript::errorHandler(E_WARNING, 'Silenced', '/path/file.php', 12);
        error_reporting($this->errorReporting);

        $this->assertFalse($handled);
        $this->assertSame([], $this->queue->messages);
    }

    public function testMissingApplicationIsIgnored()
    {
        error_reporting(E_ALL);
        Factory::$application = null;

        $this->assertTrue(AbstractScript::errorHandler(E_NOTICE, 'No app'));
    }
}

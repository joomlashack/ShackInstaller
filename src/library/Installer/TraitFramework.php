<?php

/**
 * @package   ShackInstaller
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2025 Joomlashack.com. All rights reserved
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

namespace Alledia\Installer;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die();
// phpcs:enable PSR1.Files.SideEffects

/**
 * A collection of methods that need to be available to the installer
 * when the framework is not installed.
 */
trait TraitFramework
{
    /**
     * @return \JDatabaseDriver|DatabaseDriver
     */
    final protected function getDatabase()
    {
        return is_callable([Factory::class, 'getContainer'])
            ? Factory::getContainer()->get(DatabaseInterface::class)
            : Factory::getDbo();

    }

    /**
     * @param string  $name
     * @param string  $prefix
     * @param string  $component
     * @param ?string $appName
     * @param ?array  $options
     *
     * @return mixed
     * @throws \Exception
     *
     * @see: \Alledia\Framework\Helper::getJoomlaModel();
     */
    final protected function getJoomlaModel(
        string $name,
        string $prefix,
        string $component,
        ?string $appName = null,
        ?array $options = []
    ) {
        $defaultApp = 'Site';
        $appNames   = [$defaultApp, 'Administrator'];

        $appName = ucfirst($appName ?: $defaultApp);
        $appName = in_array($appName, $appNames) ? $appName : $defaultApp;

        if (Version::MAJOR_VERSION < 4) {
            $basePath = $appName == 'Administrator' ? JPATH_ADMINISTRATOR : JPATH_SITE;

            $path = $basePath . '/components/' . $component;
            BaseDatabaseModel::addIncludePath($path . '/models');
            Table::addIncludePath($path . '/tables');

            $model = BaseDatabaseModel::getInstance($name, $prefix, $options);

        } else {
            $model = Factory::getApplication()->bootComponent($component)
                ->getMVCFactory()->createModel($name, $appName, $options);
        }

        return $model;
    }

    /**
     * @param string  $name
     * @param ?string $prefix
     * @param array   $config
     * @param ?string $component
     *
     * @return Table
     * @throws \Exception
     * @see: Alledia\Framework\Helper::getJoomlaTable()
     */
    final protected function getJoomlaTable(
        string $name,
        ?string $prefix = null,
        array $config = [],
        ?string $component = null
    ): Table {
        if (Version::MAJOR_VERSION < 4) {
            $table = Table::getInstance($name, $prefix ?: Table::class, $config);

        } elseif ($component) {
            $table = Factory::getApplication()->bootComponent($component)
                ->getMVCFactory()->createTable($name, $prefix ?: Table::class, $config);

        } else {
            $className = ($prefix ? '' : '\\Joomla\\CMS\\Table\\') . $name;
            $table     = class_exists($className) ? new $className($this->getDatabase()) : null;
        }

        if ($table) {
            return $table;
        }

        throw new \Exception('No Table: ' . ($className ?? ($prefix . $name)));
    }

    /**
     * @param string  $name
     * @param string  $source
     * @param array   $options
     * @param bool    $clear
     * @param ?string $xpath
     *
     * @return Form
     */
    final protected function getJoomlaForm(
        string $name,
        string $source = null,
        array $options = [],
        bool $clear = true,
        ?string $xpath = null
    ): Form {
        /** @var Form $form */
        if (Version::MAJOR_VERSION < 4) {
            $form = Form::getInstance($name, $source, $options, $clear, $xpath);

        } else {
            $form = Factory::getContainer()->get(FormFactoryInterface::class)->createForm($name);
            if ($source[0] == '<') {
                $form->load($source, $clear, $xpath);
            } else {
                $form->loadFile($source, $clear, $xpath);
            }
        }

        return $form;
    }
}

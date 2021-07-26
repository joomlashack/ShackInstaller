<?php
/**
 * @package   AllediaInstaller
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright Copyright (C) 2016 Open Sources Training, LLC, All rights reserved
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

use Alledia\Installer\Extension\Licensed;
use Joomla\CMS\HTML\HTMLHelper;

defined('_JEXEC') or die();

/**
 * @var Licensed $license
 */

?>
<div class="joomlashack-wrapper">
    <div class="joomlashack-content">
        <h2><?php echo $this->welcomeMessage; ?></h2>

        <?php

        if (file_exists(__DIR__ . '/default_custom.php')) {
            include __DIR__ . '/default_custom.php';
        }

        if ($license->isPro()) {
            include __DIR__ . "/default_license.php";
        }

        include __DIR__ . "/default_info.php";
        ?>

        <?php echo $this->footer; ?>
    </div>
</div>

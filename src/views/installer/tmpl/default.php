<?php
/**
 * @package   AllediaInstaller
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright Copyright (C) 2016 Open Sources Training, LLC, All rights reserved
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

use Alledia\Installer\AbstractScript;
use Alledia\Installer\Extension\Generic;
use Alledia\Installer\Extension\Licensed;

defined('_JEXEC') or die();

/**
 * @var AbstractScript $this
 * @var string         $type
 * @var Licensed       $license
 * @var string         $name
 * @var string         $configPath
 * @var string         $customFooterPath
 * @var string         $extensionPath
 * @var Generic        $licensesManagerExtension
 * @var string         $string
 * @var string         $path
 */

?>
<div class="joomlashack-wrapper">
    <div class="joomlashack-content">
        <h2><?php echo $this->welcomeMessage; ?></h2>

        <?php
        if (is_file(__DIR__ . '/default_custom.php')) :
            include __DIR__ . '/default_custom.php';
        endif;

        if ($license->isPro()) :
            include __DIR__ . '/default_license.php';
        endif;

        include __DIR__ . "/default_info.php";
        ?>

        <?php echo $this->footer; ?>
    </div>
</div>

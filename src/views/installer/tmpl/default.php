<?php
/**
 * @package   AllediaInstaller
 * @contact   www.alledia.com, hello@alledia.com
 * @copyright 2014 Alledia.com, All rights reserved
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die();
?>
<link rel="stylesheet" href="<?php echo $mediaURL . '/css/installer.css'; ?>">

<?php if (version_compare(JVERSION, '3.0', '<')) : ?>
    <script src="<?php echo $mediaURL . '/js/jquery.js'; ?>"></script>
<?php else : ?>
    <script>
        var jQueryAlledia = jQuery.noConflict();
    </script>
<?php endif; ?>

<div class="alledia-wrapper">

    <div class="alledia-content">
        <h1><?php echo $name; ?></h1>

        <h3>
            <?php if ($type === 'install') : ?>
                <?php echo JText::sprintf('LIB_ALLEDIAINSTALLER_THANKS_INSTALL', 'v' . $this->manifest->version); ?>
            <?php else : ?>
                <?php echo JText::sprintf('LIB_ALLEDIAINSTALLER_THANKS_UPDATE', 'v' . $this->manifest->version); ?>
            <?php endif; ?>
        </h3>

        <?php

        if (file_exists(__DIR__ . '/default_custom.php')) {
            include __DIR__ . '/default_custom.php';
        }

        if ($extension->isPro()) {
            include __DIR__ . "/default_license.php";
        }

        if ($this->manifest->alledia->relatedExtensions) {
            include __DIR__ . "/default_related.php";
        }

        ?>

        <div class="alledia-footer">
            Powered by
                <a href="https://www.alledia.com" target="_blank">
                    <img class="alledia-logo" src="<?php echo $mediaURL . "/images/logo-alledia.png"; ?>" />
                </a>
        </div>
    </div>

</div>

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

        ?>

        <?php if ($this->manifest->alledia->relatedExtensions) : ?>
            <div class="alledia-details-container">

                <a href="javascript:void(0);" id="alledia-installer-footer-toggler">
                    <?php echo JText::_('LIB_ALLEDIAINSTALLER_SHOW_DETAILS'); ?>
                </a>

                <div id="alledia-installer-footer" style="display: none;">
                    <h4><?php echo JText::_('LIB_ALLEDIAINSTALLER_RELATED_EXTENSIONS'); ?></h4>
                    <ul>
                        <?php foreach ($this->relatedExtensionFeedback as $element => $data) : ?>
                            <li>
                                <?php echo JText::_($data['name']) . ': ' . $data['message']; ?>
                                <?php if (isset($data['publish'])) : ?>
                                    <?php echo JText::_('LIB_ALLEDIAINSTALLER_PUBLISHED'); ?>: <?php echo JText::_($data['publish'] ? 'JYES' : 'JNO'); ?>
                                <?php endif; ?>

                                <?php if (isset($data['ordering'])) : ?>
                                    <?php echo JText::_('LIB_ALLEDIAINSTALLER_SORTED'); ?>: <?php echo JText::_($data['ordering'] ? 'JYES' : 'JNO'); ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

            </div>
        <?php endif; ?>

        <div class="alledia-footer">
            Powered by <img class="alledia-logo" src="<?php echo $mediaURL . "/images/logo-alledia.png"; ?>" />
        </div>
    </div>

</div>

<script>
(function($) {

    $(function() {
        // More info button
        $('#alledia-installer-footer-toggler').on('click', function(event) {
            $('#alledia-installer-footer').show();
            $(this).hide();
        });
    });

})(jQueryAlledia);
</script>

<?php
/**
 * @package   AllediaInstaller
 * @contact   www.alledia.com, hello@alledia.com
 * @copyright 2014 Alledia.com, All rights reserved
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die();
?>
<?php if ($this->manifest->alledia->relatedExtensions) : ?>
    <a href="#alledia-installer-footer" id="alledia-installer-footer-toggler">
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
<?php endif; ?>

<script type="text/javascript">
(function() {
    var toggler = document.getElementById('alledia-installer-footer-toggler'),
        footer  = document.getElementById('alledia-installer-footer');

    toggler.addEventListener('click', function(event) {
        footer.style.display = 'block';
        toggler.style.display = 'none';
    });
})();
</script>

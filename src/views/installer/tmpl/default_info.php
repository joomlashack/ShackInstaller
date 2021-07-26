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
use Joomla\CMS\Language\Text;

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
<div class="joomlashack-details-container">
    <a href="javascript:void(0);" id="joomlashack-installer-footer-toggler">
        <?php echo Text::_('LIB_ALLEDIAINSTALLER_SHOW_DETAILS'); ?>
    </a>

    <div id="joomlashack-installer-footer" style="display: none;">
        <div class="joomlashack-license">
            <?php echo Text::sprintf('LIB_ALLEDIAINSTALLER_RELEASE_V', (string)$this->manifest->version); ?>
        </div>
        <br>
        <?php if (!empty($this->manifest->alledia->relatedExtensions)) : ?>
            <table class="joomlashack-related-table">
                <thead>
                <tr>
                    <th colspan="2"><?php echo Text::_('LIB_ALLEDIAINSTALLER_RELATED_EXTENSIONS'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($this->relatedExtensionFeedback as $element => $data) : ?>
                    <tr>
                        <td><?php echo Text::_($data['name']); ?></td>
                        <td>
                            <?php
                            $messages = [$data['message']];

                            if (isset($data['publish']) && $data['publish']) {
                                $messages[] = Text::_('LIB_ALLEDIAINSTALLER_PUBLISHED');
                            }

                            if (isset($data['ordering'])) {
                                $messages[] = Text::sprintf('LIB_ALLEDIAINSTALLER_SORTED', $data['ordering']);
                            }

                            $messages = implode(', ', $messages);
                            echo $messages;
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="joomlashack-license">
            <?php
            echo Text::sprintf(
                'LIB_ALLEDIAINSTALLER_LICENSED_AS',
                $this->getName(),
                '<a href="http://www.gnu.org/licenses/gpl-3.0.html">GNU/GPL v3.0</a>'
            );
            ?>.
        </div>
    </div>

</div>

<script>
    (function() {
        let footer = document.getElementById('joomlashack-installer-footer'),
            toggle = document.getElementById('joomlashack-installer-footer-toggler');

        if (footer && toggle) {
            toggle.addEventListener('click', function(event) {
                event.preventDefault();

                footer.style.display = 'block';
                this.style.display   = 'none';
            });
        }
    })();
</script>

<div>Version: <?php echo $this->manifest->version; ?></div>

<?php if ($this->manifest->alledia->relatedExtensions) : ?>
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
<?php endif; ?>


<div>Version: <?php echo $this->manifest->version; ?></div>

<?php if ($this->manifest->relatedExtensions) : ?>
    <h4>Related Extensions</h4>
    <ul>
        <?php foreach ($this->relatedExtensionsState as $element => $data) : ?>
            <li>
                <?php echo $data['name'] . ': ' . $data['message']; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>


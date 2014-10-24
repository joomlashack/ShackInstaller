<?php include 'header_default.php'; ?>
<?php $name = explode(' - ', $name); ?>
<h3>Thanks for installing <?php echo $name[1]; ?>!</h3>
<div>Version: <?php echo $this->manifest->version; ?></div>

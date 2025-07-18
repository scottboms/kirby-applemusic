<?php
// snippets/blocks/applemusic.php

$embed = $block->content()->get('apple_music')->value();

?>
<?php if($embed): ?>
	<?= $embed ?>
<?php endif ?>

<?php
// snippets/blocks/applemusic.php

$content = $block->content();
$value = $content->get('apple_music')->value();
$format = $content->get('format')->value() ?? 'false';
?>
<?php if ($value): ?>
	<?php if($format === 'false'): ?>
		<?= $value ?>
	<?php else: ?>
		<iframe
			allow="autoplay *; encrypted-media *;"
			frameborder="0"
			height="150"
			style="width:100%;overflow:hidden;background:transparent;"
			sandbox="allow-forms allow-popups allow-same-origin allow-scripts allow-storage-access-by-user-activation allow-top-navigation-by-user-activation"
			src="<?= str_replace('https://music.apple.com', 'https://embed.music.apple.com', $value) ?>">
		</iframe>
	<?php endif ?>
<?php endif ?>




<?php

/**
	* snippet: applemusic
	* usage: <?php snippet('applemusic', ['field' => $page->FIELD_NAME()]) ?>
	*/

$field = $field ?? null;

if (!$field || !$field->isNotEmpty()) {
	return;
}

$value = $field->value();
$format = option('scottboms.applemusic.format', 'embed');

// if format is embed, we expect a full iframe
if ($format === 'embed') {
	echo $value;
	return;
}

// If format is link, we expect a regular Apple Music link to transform
if ($format === 'link') {
	// validate link format (optional)
	if (!Str::startsWith($value, 'https://music.apple.com/')) {
		echo '<!-- Invalid Apple Music link -->';
		return;
	}

	// convert link to embed.src
	$embedSrc = str_replace(
		'https://music.apple.com/',
		'https://embed.music.apple.com/',
		$value
	);

	// output iframe
	echo '<iframe allow="autoplay *; encrypted-media *;" frameborder="0" height="150" style="width:100%;overflow:hidden;background:transparent;" sandbox="allow-forms allow-popups allow-same-origin allow-scripts allow-storage-access-by-user-activation allow-top-navigation-by-user-activation" src="' . esc($embedSrc) . '"></iframe>';
}

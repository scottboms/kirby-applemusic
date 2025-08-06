<?php

//namespace scottboms\kirby-applemusic;

/**
 * Kirby Apple Music Embed
 *
 * @author Scott Boms <plugins@scottboms.com>
 * @link https://github.com/scottboms/kirby-applemusic
 * @license MIT
**/

use Kirby\Cms\App;

// shamelessly borrowed from distantnative/retour-for-kirby
if (
	version_compare(App::version() ?? '0.0.0', '4.0.1', '<') === true ||
	version_compare(App::version() ?? '0.0.0', '6.0.0', '>=') === true
) {
	throw new Exception('Apple Music Field requires Kirby v4 or v5');
}

Kirby::plugin(
	name: 'scottboms/applemusic-field',
	info: [
		'homepage' => 'https://github.com/scottboms/kirby-applemusic',
		'license'  => 'MIT'
	],
	version: '1.2.3',
	extends: [
		'options' => [
			'format' => 'link' // fallback if not defined in config.php
		],
		'fields' => [
			'applemusic' => [
				'computed' => [
					'format' => function () {
						return option('scottboms.applemusic.format', 'embed');
					}
				],
				'props' => [
					'label' => function( $label = 'Apple Music Embed') {
						return $label;
					},
					'help' => function( $help = null) {
						return $help;
					}
				]
			]
		],
		'blocks' => [
			'applemusic' => [
				'icon' => 'album',
				'snippet' => 'blocks/applemusic'
			]
		],
		'blueprints' => [
			'blocks/applemusic' => __DIR__ . '/blueprints/blocks/applemusic.yml'
		],
		'snippets' => [
			'applemusic' => __DIR__ . '/snippets/applemusic.php',
			'blocks/applemusic' => __DIR__ . '/snippets/blocks/applemusic.php'
		]
	]
);

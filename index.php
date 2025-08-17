<?php

//namespace scottboms\kirby-applemusic;

/**
 * Kirby Apple Music Embed
 *
 * @author Scott Boms <plugins@scottboms.com>
 * @link https://github.com/scottboms/kirby-applemusic
 * @license MIT
**/

@include_once __DIR__ . '/vendor/autoload.php';

use Kirby\Cms\App;
use Firebase\JWT\JWT;
use Kirby\Http\Remote;
use Kirby\Http\Response;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Scottboms\MusicKit\MusicKit;
use Scottboms\MusicKit\Auth;

// shamelessly borrowed from distantnative/retour-for-kirby
if (
	version_compare(App::version() ?? '0.0.0', '4.0.1', '<') === true ||
	version_compare(App::version() ?? '0.0.0', '6.0.0', '>=') === true
) {
	throw new Exception('Apple Music requires Kirby v4 or v5');
}

Kirby::plugin('scottboms/applemusic-field', [
	'options' => [
		'format' => 'link',
		'teamId' => null, // e.g. 'ABCDE12345'
		'keyId'  => null, // e.g. '1A2BC3DEFG'
		'privateKey' => null, // contents of .p8 key -- do not share publicly
		'storefront' => 'auto',
		'songPerPage' => 10,
		'songsCount' => 6,
		'cacheTtl' => 120, // seconds to cache recent tracks response
		'tokenTtl' => 3600,
		'tokenCacheTtlMinutes' => 30,
		'allowedOrigins' => [
			// e.g. 'https://example.com', 'http://localhost:3000'
		],
	],

	'routes' => require __DIR__.'/lib/routes.php',

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

	'areas' => [
		'musickit' => [
			'label' => 'Apple Music',
			'icon'  => 'album',
			'menu'  => true,
			'link'  => 'applemusic',
			'views' => [
				[
					'pattern' => 'applemusic',
					'action'  => function () {
						$plugin = kirby()->plugin('scottboms/applemusic-field');

						return [
							'component' => 'k-musickit-view',
							'props' => [
								'appName'    => 'KirbyMusicKit',
								'appBuild'   => $plugin->info()['version'] ?? 'dev',
								'hasToken'   => Auth::readToken(kirby()->user()?->id()) ? true : false,
								'storefront' => option('scottboms.applemusic.storefront', 'auto'),
							]
						];
					}
				]
			]
		]
	],

	'blueprints' => [
		'blocks/applemusic' => __DIR__ . '/blueprints/blocks/applemusic.yml'
	],

	'snippets' => [
		'applemusic'        => __DIR__ . '/snippets/applemusic.php',
		'blocks/applemusic' => __DIR__ . '/snippets/blocks/applemusic.php',
		'recently-played'   => __DIR__ . '/snippets/recently-played.php',
	],

	'info' => [
		'homepage' => 'https://github.com/scottboms/kirby-applemusic',
		'version'  => '2.0.0',
		'license'  => 'MIT',
		'authors'  => [[ 'name' => 'Scott Boms' ]],
	]
]);

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
require_once __DIR__ . '/fields/AppleMusicField.php';

use Kirby\Cms\App as Kirby;
use Firebase\JWT\JWT;
use Kirby\Http\Remote;
use Kirby\Http\Response;
use Kirby\Filesystem\Dir;
use Kirby\Filesystem\F;
use Scottboms\MusicKit\MusicKit;
use Scottboms\MusicKit\Auth;
use Scottboms\MusicKit\Utils;
use Scottboms\AppleMusic\AppleMusicField;

$version = Kirby::version() ?? '0.0.0';
if (
    version_compare($version, '6.0.0-beta.1', '<') ||
    version_compare($version, '7.0.0', '>=')
) {
    throw new Exception('Apple Music requires Kirby 6');
}

Kirby::plugin('scottboms/applemusic',
info: [
	'homepage' => 'https://github.com/scottboms/kirby-applemusic',
	'version'  => '6.0.0',
	'license'  => 'MIT',
	'authors'  => [[ 'name' => 'Scott Boms' ]],
],
extends: [
	'options' => [
		'format' => 'link',
		'teamId' => null, // e.g. 'ABCDE12345'
		'keyId'  => null, // e.g. '1A2BC3DEFG'
		'privateKey' => null, // contents of .p8 key -- do not share publicly
		'storefront' => 'auto',
		'songsLimit' => 10,
		'songsToShow' => 6,
		'cacheTtl' => 120, // 2 mins to cache recent tracks response
		'tokenTtl' => 3600, // 1 hr
		'tokenCacheTtlMinutes' => 43200, // 30 days (24 * 60 * 30)
		'allowedOrigins' => [
			// e.g. 'https://example.com', 'http://localhost:3000'
		],
	],

	'routes' => require __DIR__.'/lib/routes.php',

	'fields' => [
		'applemusic' => AppleMusicField::class
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
			'icon'  => 'album-filled',
			'breadcrumbLabel' => function () {
				return 'Apple Music';
			},
			'menu'  => true,
			'link'  => 'applemusic',
			'views' => [
				[
					'pattern' => 'applemusic',
					'action'  => function () {
						$pluginId = 'scottboms/applemusic';
						$plugin   = kirby()->plugin($pluginId);
						$appBuild = $plugin?->version() ?? ($plugin?->info()['version'] ?? 'dev');
						$status   = Auth::musickit_config_status();

						return [
							'component' => $status['ok'] ? 'k-musickit-history-view' : 'k-musickit-config-view',
							'props' => [
								'appName'    => 'KirbyMusicKit',
								'appBuild'   => $appBuild,
								'hasToken'   => Auth::readToken(kirby()->user()?->id()) ? true : false,
								'storefront' => option('scottboms.applemusic.storefront', 'auto'),
								'songsLimit' => option('scottboms.applemusic.songsLimit', 15),
								'status'     => $status['status'],
								'missing'    => $status['missing'],
								'errors'     => $status['errors'],
							]
						];
					}
				],

				[
					'pattern'   => 'applemusic/song/(:any)',
					'action'  => function ($songId) {
						return [
							'component' => 'k-musickit-song-view',
							'breadcrumb' => function () {
								return [
									[ 'label' => 'Song' ]
								];
							},
							'props'     => [
								'songId'   => $songId,
								'language' => option('panel.language', 'en-US')
							]
						];
					}
				],

				[
					'pattern'   => 'applemusic/album/(:any)',
					'action'  => function ($albumId) {
						return [
							'component' => 'k-musickit-album-view',
							'breadcrumb' => function () {
								return [
									[ 'label' => 'Album' ]
								];
							},
							'props'     => [
								'albumId'   => $albumId,
								'language' => option('panel.language', 'en-US')
							]
						];
					}
				],

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
]);

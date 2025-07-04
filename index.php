<?php

//namespace scottboms\kirby-applemusic;

/**
 * Kirby Apple Music Field
 *
 * @author Scott Boms <plugins@scottboms.com>
 * @link https://github.com/scottboms/kirby-applemusic
 * @license MIT
**/

use Composer\Semver\Semver;
use Kirby\Cms\App as Kirby;

// validate Kirby version
if (Semver::satisfies(Kirby::version() ?? '0.0.0', '~4.0 || ~5.0') === false) {
	throw new Exception('Apple Music Field requires Kirby 4 or 5');
}

Kirby::plugin(
  name: 'scottboms/applemusic-field', 
  info: [
    'homepage' => 'https://github.com/scottboms/kirby-applemusic'
  ],
  version: '1.0.4',
  license: 'MIT',
  extends: [
    'fields' => [
      'applemusic' => [
        'props' => [
          'label' => function( $label = 'Apple Music Embed') {
            return $label;
          },
          'help' => function( $help = null) {
            return $help;
          }
        ]
      ]
    ]
  ]
);
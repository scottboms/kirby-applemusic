# Apple Music for Kirby

![Plugin Preview](src/assets/apple-music-plugin.jpg)

Adds Apple Music Embed field and block types for Kirby including a live preview of the embed in the panel for each. Version 2.0.0 introduces an optional Panel Area that integrates with the [Apple Music API](https://developer.apple.com/documentation/applemusicapi/) (requires a paid [Apple Developer account](https://developer.apple.com)) to display recently played songs using an authenticated Apple Music account, as well as providing quick access to get a song's URL or embed code. Recently played songs can also be exposed to templates using a provided snippet.


## Installation

### [Kirby CLI](https://github.com/getkirby/cli)
    
```bash
kirby plugin:install scottboms/kirby-applemusic
```

### Git submodule

```bash
git submodule add https://github.com/scottboms/kirby-applemusic.git site/plugins/kirby-applemusic
```

### Copy and Paste

1. [Download](https://github.com/scottboms/kirby-applemusic/archive/master.zip) the contents of this repository as Zip file.
2. Rename the extracted folder to `kirby-applemusic` and copy it into the `site/plugins/` directory in your Kirby project.


## Configuration Options

An optional configuration setting to override the default format for the field.

| Property                      | Default | Req? | Description                                    |
|-------------------------------|---------|------|------------------------------------------------|
| scottboms.applemusic.format   | `link`  | No   | Sets the field type format - `link` or `embed` |

**Example Use (in config/config.php):**

```php
return [
  scottboms.applemusic.format => 'embed'
]
```

If using the [Apple Music API](https://developer.apple.com/documentation/applemusicapi/), you will first need to generate the necessary keys and generate a token. Those values can then be added to your site config which will allow access from the Panel and also to your templates.

**Required:**

These private properties should be stored securely and ignored by version control systems. This can be done by creating an `env.php` file in the site/config folder and adding that to your gitignore rules.

| Property        | Default | Req?  | Description                                                  |
|-----------------|---------|-------|--------------------------------------------------------------|
| teamId          | `null`  | Yes   | Available from Apple Developer account profile               |
| keyId           | `null`  | Yes   | A generated Apple Music Media ID Key                         |
| privateKey      | `null`  | Yes   | The raw contents of the generated .p8 token                  |
| allowedOrigins  | `null`  | No   | An array of domains to allow for handling CORS responses     |

**Example Use (in config/env.php):**

```php
return [
  // private plugin config
  'scottboms.applemusic' => [
    'teamId' => 'ABCDE12345',
    'keyId' => '1A2BC3DEFG',
    'privateKey' => '-----BEGIN PRIVATE KEY-----///-----END PRIVATE KEY-----',
    'allowedOrigins' => [
      'https://example.com', 'https://yoursite.com'
    ],
  ],
]
```

**Optional Config Overrides:**

| Property        | Default | Req?  | Description                                                  |
|-----------------|---------|-------|--------------------------------------------------------------|
| tokenTtl        | `3600`  | No    | Length of time for the token to persist in seconds (5 min)   |
| songsLimit      | `15`    | No    | The number of songs to show in the Panel Area                |
| songsToShow     | `12`    | No    | The number of songs to show via the included snippet         |   

**Example Use (in config/config.php):**

```php
return [
  // applemusic plugin config
  'scottboms.applemusic' => [
    'tokenTtl' => 4800,
    'songsLimit' => 10,
    'songsToShow' => 6
  ],
]
```


## Usage

### Blueprints

In a Page blueprint, add a new field with the type `applemusic`. Standard field attributes such as `label`, `required`, `help`, etc. can also be used to override the defaults. Use `emptyText` to change the default text displayed when the field is in an empty state.

```yml
  music:
    label: Apple Music Embed
    type: applemusic
    emptyText: 'Click to paste Apple Music embed code'

  blocks:
    type: blocks
    fieldsets:
      - heading
      - text
      - image
      - applemusic
```

### Templates and Snippets

If using the defaults, and based on the example Blueprint above, to render the field in your template you can use `<?= $page->music() ?>`. Note that any additional helper functions applied may break the embed. You do not need to use `->kt()` or `->kti()` for example.

If using the `link` format, a custom snippet is included to handle formatting the link into a properly formatted `<iframe>` embed. For example:

```php
<?php snippet('applemusic', ['field' => $page->music()]) ?>
```

If using the API, a snippet is included to handle displaying a customizable grid of recent songs.

```php
<?= snippet('recently-played', ['limit' => 6]) ?>
```


## Compatibility

* Kirby 4.x
* Kirby 5.x


## Disclaimer

This plugin is provided "as is" with no guarantee. Use it at your own risk and always test before using it in a production environment. If you identify an issue, typo, etc, please [create a new issue](/issues/new) so I can investigate.


## License

[MIT](https://opensource.org/licenses/MIT)

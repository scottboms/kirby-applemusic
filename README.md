# Apple Music Field Plugin for Kirby

This plugin adds an Apple Music Embed field type for Kirby including a live preview of the embed in the panel.

## Installation

### [Kirby CLI](https://github.com/getkirby/cli)
    
    kirby plugin:install scottboms/kirby-applemusic

### Git submodule

    git submodule add https://github.com/scottboms/kirby-applemusic.git site/plugins/kirby-applemusic

### Copy and Paste

1. [Download](https://github.com/scottboms/kirby-applemusic/archive/master.zip) the contents of this repository as Zip file.
2. Rename the extracted folder to `kirby-applemusic` and copy it into the `site/plugins/` directory in your Kirby project.

## Usage

In a Page blueprint, add a new field with the type `applemusic`. Standard field attributes such as `label`, `required`, `help`, etc. can also be used to override the defaults.

    music:
      label: Apple Music Embed
      type: applemusic

## Compatibility

* Kirby 4.x
* Kirby 5.x

## Disclaimer

This plugin is provided "as is" with no guarantee. Use it at your own risk and always test before using it in a production environment. If you identify an issue, typo, etc, please [create a new issue](/issues/new) so I can investigate.

## License

[MIT](https://opensource.org/licenses/MIT)

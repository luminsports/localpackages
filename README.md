# LocalPackages

Automatically symlink composer packages post-install/update for easier local development

## Installation

LocalPackages can be installed globally or per project, with Composer:

Globally (recommended): `composer global require luminsports/localpackages`
Per project: `composer require --dev luminsports/localpackages`

## Usage

1. Create a `composer.localpackages.json` file in your project root directory. This file will contain your mapping of paths to locally developed packages
2. Populate it with paths, in the following JSON format:
```
{
    "paths": [
        "~/path/to/your/package",
        "~/path/to/your/other-package",
    ]
}
```
3. Since the configuration you will provide is unique to your environment, it would be best practice to add `composer.localpackages.json` to your `.gitignore` file.
4. `composer install` or `composer update`. LocalPackages will scan the directories specified in your `composer.localpackages.json` file for packages. If, for example, you used the configuration above and `~/path/to/your/package` contains a `composer.json` file for `your/package`, your project will symlink any `your/package` dependency to `~/path/to/your/package` automatically.

## Known Issues

If anything weird happens and you get stuck, try `rm -r vendor/ composer.lock && composer install`. If that doesn't work, blame me.

- I don't believe this works when using `composer require your/package`. Just run `composer update your/package` afterwards, and everything should symlink

## License

This code is published under the [MIT License](http://opensource.org/licenses/MIT).
This means you can do almost anything with it, as long as the copyright notice and the accompanying license file is left intact.

## Thanks

This package is a super-simplified version of [franzl/studio](https://github.com/franzliedke/studio).

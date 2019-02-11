# LocalPackages

Automatically symlink composer packages post-install/update for easier local development

## Installation

LocalPackages can be installed globally or per project, with Composer:

Globally (recommended): `composer global require ryzr/localpackages`
Per project: `composer require --dev ryzr/localpackages`

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

## Contributing

I wish I had more time for open-source contributions, but unfortunately I don't. Currently, this package serves my needs, and thus won't receive many/any feature updates.

However, if you run into any bugs, feel free to open an issue to bring my attention to it, or even better, submit a pull-request.

## Thanks

This package is a super-simplified version of [franzl/studio](https://github.com/franzliedke/studio).

The only difference is a couple of fixes for my needs, and a bunch of stuff removed that I didn't need. Check it out, as it may suit you better.
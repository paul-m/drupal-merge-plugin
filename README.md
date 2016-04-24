drupal-merge-plugin
===

Note: This project is in development and should not be used in production at this time.

What?
--

`drupal-merge-plugin` is a Composer plugin that allows Drupal modules to specify their own Composer-based dependencies without extra infrastructure.

This plugin is an evolution of the `plugin_manager` module concept, using Drupal extension discovery to merge Composer-based dependencies.

It builds on the Wikimedia project's `composer-merge-plugin`, and inherits some of that project's behaviors.

You can read the documentation for Wikimedia's `composer-merge-plugin` here: https://github.com/wikimedia/composer-merge-plugin

Why?
----

Adding `packagist.drupal-composer.org` to your `composer.json` file is enough to help it find Drupal modules, and install them along with their dependencies.

However, if you need to update, or re-install without a lock file, that won't be enough to manage those dependencies.

With this plugin, that's possible.

Once you're using this plugin, it will search for `composer.json` files within the extensions present in your Drupal project's file system, and then try to satisfy them. If they can't be satisfied (due to version constraints, etc.) then Composer will tell you.

How?
--

At the command line, type this:

	$ composer require mile23/drupal-merge-plugin

This adds the plugin to your Drupal project.

In order to get Drupal extensions using Composer, you must then add the special Drupal Packagist clone to your `repositories` section:


	"repositories": {
        "type": "composer",
        "url": "https://packagist.drupal-composer.org"
    },

Then you can add Drupal modules:

	$ composer require drupal/your-module-here

You can add command-line scripts to your `composer.json` like this:

    "scripts": {
        "list-extensions": "Mile23\\DrupalMerge\\Script::listExtensions",
        "list-managed-extensions": "Mile23\\DrupalMerge\\Script::listManagedExtensions",
        "list-unmanaged-extensions": "Mile23\\DrupalMerge\\Script::listUnmanagedExtensions",
    },

Once you've done that, you can list available modules by their Composer status.

- `composer list-extensions` gives you all discoverable Drupal extensions which have a `composer.json` file.
- `composer list-managed-extensions` gives you the Drupal extensions which are in the `requires` section of the current Composer package. Restated: If the extension is in your `composer.json` file, it appears in this list.
- `composer list-unmanaged-extensions` gives you a list of modules which have `composer.json` files, but which are not listed as dependencies in the current project. These would be extensions downloaded as tarballs, for instance.


What Should My Contrib Module's `composer.json` File Look Like?
--

Drupal modules SHOULD NOT specify that they depend on this plugin.

This plugin's behavior might also conflict with other Composer solutions built by the Drupal community.

You can supply a `composer.json` file per module, at any folder depth supported by normal Drupal module discovery.

Your `composer.json` file must be in the same directory as the module's `.info.yml` file.

Your `comopser.json` file should pass the test of running `composer validate` from the command line.

If your `composer.json` file does not specify `requires` and/or `requires-dev`, then this plugin probably won't be terribly useful to you.

`drupal-merge-plugin` will use the same merging rules as `wikimedia/composer-merge-plugin`. That is, `requires` and `requires-dev` will be merged, along with other sections of `composer.json`. Consult the `composer-merge-plugin` documentation for more information.

You should NOT use the `autoload` feature of Composer for your module. Drupal will autoload the module as needed.

`drupal-merge-plugin` uses the same project type naming convention as `composer-installers`. This means your module's `type` field should be `drupal-module`. This allows the `composer-installer` plugin to place your module in the proper directory, and allows `drupal-merge-plugin` to discover it.

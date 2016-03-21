drupal-merge-plugin
===

Note: This project is in development and should not be used in production at this time.

What?
--

`drupal-merge-plugin` is a Composer plugin that allows Drupal modules to specify their own Composer-based dependencies without extra infrastructure.

Note that this plugin is fully compatible with Drupal multisite. It will discover and reconcile all dependencies for all modules present in the Drupal installation, across the different sites. They will all share the same `vendor/` directory where other Drupal dependencies reside.

It builds on the Wikimedia project's `composer-merge-plugin`, and inherits some of that project's behaviors.

You can read the documentation for Wikimedia's `composer-merge-plugin` here: https://github.com/wikimedia/composer-merge-plugin


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

Why?
----

Adding packagist.drupal-composer.org to your composer.json file is enough to help it find Drupal modules, and install them along with their dependencies.

However, if you need to update or re-install (because, for instance, your `vendor/` directory is missing), that won't be enough to manage those dependencies.

With this plugin, that's possible.

Once you're using this plugin, it will search for `composer.json` files within the dependencies of your Drupal project, and then try to satisfy them. If they can't be satisfied (due to version constraints, etc.) then Composer will tell you.

Drupal modules SHOULD NOT specify that they depend on this plugin. In the rest of the Composer world, this would be a reasonable thing to do, but in the context of the many Drupalisms, this would be an anti-pattern.

This plugin's behavior might also conflict with other Composer solutions built by the Drupal community.

What Should My Contrib Module's `composer.json` File Look Like?
--

You can supply a `composer.json` file per module, at any folder depth supported by normal Drupal module discovery.

Your `composer.json` file must be in the same directory as the module's `.info.yml` file.

Your `comopser.json` file should pass the test of running `composer validate` from the command line.

If your `composer.json` file does not specify `requires` and/or `requires-dev`, then this plugin probably won't be terribly useful to you.

`drupal-merge-plugin` will use the same merging rules as `wikimedia/composer-merge-plugin`. That is, `requires` and `requires-dev` will be merged, along with other sections of `composer.json`. Consult the `composer-merge-plugin` documentation for more information.

You should NOT use the `autoload` feature of Composer for your module. Drupal will autoload the module as needed.

`drupal-merge-plugin` uses the same project type naming convention as `composer-installers`. This means your module's `type` field should be `drupal-module`. This allows the `composer-installer` plugin to place your module in the proper directory, and allows `drupal-merge-plugin` to discover it.

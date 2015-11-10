drupal-merge-plugin
===

What?
--

`drupal-merge-plugin` is a Composer plugin that allows Drupal modules to specify their own Composer-based dependencies.

`drupal-merge-plugin` is highly opinionated. It is designed to be useful as part of a low-dependency build phase for Drupal sites which aren't expected to have multiple vendor directories, or different dependency requirements per multisite site.

The fact that the previous sentence is almost 90% Drupalism illustrates why this tool is opinionated.

Basically: If you are using Drupal in multisite mode, and you have many conflicting dependencies you believe shouldn't conflict, then don't use this plugin.

It sits atop the Wikimedia project's composer-merge-plugin, and inherits that project's behaviors. This means your root Composer package can specify external `composer.json` files to load, using wildcard paths. The top-level Drupal project's `composer.json` file does this.

You can read the documentation for Wikimedia's composer-merge-plugin here: https://github.com/wikimedia/composer-merge-plugin


How?
--

At the command line, type this:

	$ composer require mile23/drupal-merge-plugin
	$ composer require drupal/your-module-here

The first line causes your Drupal project to use `drupal-merge-plugin`.

The second life is where you specify your dependencies as needed.

Once you're using this plugin, it will search for `composer.json` files within the dependencies of your Drupal project, and then try to satisfy them. If they can't be satisfied (due to version constraints, etc.) then Composer will tell you.

Drupal modules should not specify that they depend on this plugin. In the rest of the Composer world, this would be a reasonable thing to do, but in the context of the many Drupalisms, this would be an anti-pattern. This plugin's behavior very likely conflicts with other Composer solutions built by the Drupal community.

What Should My Contrib Module's `composer.json` File Look Like?
--

You can supply a `composer.json` file per module, at any folder depth supported by normal Drupal module discovery.

Your `composer.json` file must be in the same directory as the module's `.info.yml` file.

Your `comopser.json` file should pass the test of running `composer validate` from the command line.

If your `composer.json` file does not specify `requires` and/or `requires-dev`, then this plugin probably won't be terribly useful to you.

`drupal-merge-plugin` will use the same merging rules as `wikimedia/composer-merge-plugin`. That is, `requires` and `requires-dev` will be merged, along with other sections of `composer.json`. Consult the `composer-merge-plugin` documentation for more information.

You should NOT use the `autoload` feature of Composer for your module. Drupal will autoload the module as needed.

Current Drupal best practice is that your module's `type` field be `drupal-module`. This allows the `composer-installer` plugin to place your module in the proper directory. This is unrelated to `drupal-merge-plugin`, but it's a good idea.

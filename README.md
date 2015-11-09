drupal-merge-plugin
===

What?
--

drupal-merge-plugin is a Composer plugin that allows Drupal modules to specify their own Composer-based dependencies.

drupal-merge-plugin is highly opinionated. It is designed to be useful for a low-dependency build phase of non-multisite Drupal sites.

It sits atop the Wikimedia project's composer-merge-plugin, and inherits that project's behaviors. This means your Composer package can specify external `composer.json` files to load, using wildcard paths. The top-level Drupal project's `composer.json` file does this.

How?
--

At the command line, type this:

	$ composer require mile23/drupal-merge-plugin
	$ composer require drupal/your-module-here

This pattern will search for composer.json files within the dependencies of your Drupal project, and then try to satisfy them. If they can't be satisfied (due to version constraints, etc.) then Composer will tell you.

Drupal modules could specify that they depend on this plugin. However, in the context of Drupalisms, this would be an anti-pattern, since this plugin's behavior very likely conflicts with other Composer solutions built by the Drupal community.


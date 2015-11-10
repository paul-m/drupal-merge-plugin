Testing `drupal-merge-plugin`
===

Clone the repo to its own project directory. That is, you can't run the tests while it's in the Composer-managed `vendor/` directory.

Use Composer to install the dependencies:

	composer install --dev

Then run PHPUnit:

	./vendor/bin/phpunit

Note that you can also use:

	composer test

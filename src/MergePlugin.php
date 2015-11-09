<?php

namespace Drupal\Composer;

use Composer\Factory;
use Drupal\Core\Extension\ExtensionDiscovery;
use Wikimedia\Composer\MergePlugin as WikimediaMergePlugin;

class MergePlugin extends WikimediaMergePlugin {

  /**
   * {@inheritdoc}
   */
  protected function mergeIncludes(array $include_patterns) {
    error_log('drupalmergeplugin merging includes.');
    // Let the parent do its job first.
    parent::mergeIncludes($include_patterns);

    // Assume stuff about the location of the root directory.
    $root_dir = realpath(dirname(Factory::getComposerFile()));

      /*  $installed_file = $root_dir . '/vendor/composer/installed.json';
        $composer_managed_modules = [];
        if (file_exists($installed_file)) {
          $installed = json_decode(file_get_contents($installed_file));
          //error_log(print_r($installed, TRUE));

          foreach ($installed as $installed_dependency) {
            if ($installed_dependency->type == 'drupal-module') {
              $composer_managed_modules[] = $installed_dependency;
            }
          }

        }*/
        //error_log('composer mods: ' . print_r($composer_managed_modules, TRUE));



    // Change this for a 'real' Drupal installation. Also, fix this as a
    // non-autoloaded dependency. We only need it because it's a dependency of
    // ExtensionDiscovery.
    require_once $root_dir . '/vendor/drupal/core/includes/bootstrap.inc';

    $discovery = new ExtensionDiscovery($root_dir, FALSE);

    // @todo: Use -dev to figure out if we should include tests.
    $modules = $discovery->scan('module', FALSE);

    $root = $this->state->getRootPackage();

    foreach($modules as $module_name => $module) {
      $local_composer_file = dirname($module->getPathname()) . '/composer.json';
      $composer_file = $root_dir . '/' . $local_composer_file;
      if (file_exists($composer_file)) {
        error_log('Merging: ' . $local_composer_file);
        $this->mergeFile($root, $local_composer_file);
      }
    }
  }

}

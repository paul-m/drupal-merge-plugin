<?php

namespace Mile23\DrupalMerge;

use Mile23\DrupalMerge\Extension\ExtensionDiscovery;

class ComposerFinder {

  /**
   *
   * @param string $root_dir
   * @param \Mile23\DrupalMerge\RootPackageInterface $root_package
   *
   * @return \Mile23\DrupalMerge\Extension\Extension[]
   *   Extensions that have composer.json files, keyed by module name.
   */
  public function getComposerModules($root_dir, RootPackageInterface $root_package) {
    // We're using our own fork of Drupal's ExtensionDiscovery.
    $discovery = new ExtensionDiscovery($root_dir, FALSE);

    // Scan for modules.
    // @todo: Determine whether we should scan for profiles, themes.
    $modules = $discovery->scan('module', $root_package->isDev());

    $extensions = [];
    foreach ($modules as $module_name => $module) {
      if (!empty($module->getComposerJson())) {
        $extensions[$module_name] = $module;
      }
    }
    return $extensions;
  }

}

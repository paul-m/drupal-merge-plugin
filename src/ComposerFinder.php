<?php

namespace Mile23\DrupalMerge;

use Composer\Composer;
use Composer\Package\RootPackageInterface;
use Composer\Package\PackageInterface;
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

  /**
   * Get a list of all modules currently managed by the root package.
   *
   * Currently unused.
   *
   * @return Composer\Package\PackageInterface[]
   *   Composer packages representing Drupal modules managed in the root
   *   package.
   */
  public static function getComposerManagedModules(Composer $composer) {
    $local_repository = $composer->getRepositoryManager()->getLocalRepository();
    $packages = $local_repository->getPackages();

    $composer_managed_modules = [];
    foreach ($packages as $installed_dependency) {
      if ($installed_dependency->getType() == 'drupal-module') {
        $composer_managed_modules[] = $installed_dependency;
      }
    }
    return $composer_managed_modules;
  }

}

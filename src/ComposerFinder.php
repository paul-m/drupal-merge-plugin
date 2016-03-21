<?php

namespace Mile23\DrupalMerge;

use Composer\Composer;
use Composer\Package\RootPackageInterface;
use Mile23\DrupalMerge\Extension\ExtensionDiscovery;

class ComposerFinder {

  /**
   *
   * @param string $root_dir
   * @param \Composer\Package\RootPackageInterface $root_package
   *
   * @return \Mile23\DrupalMerge\Extension\Extension[]
   *   Extensions that have composer.json files, keyed by module name.
   */
  public function getComposerExtensions($root_dir, RootPackageInterface $root_package) {
    // We're using our own fork of Drupal's ExtensionDiscovery.
    $discovery = new ExtensionDiscovery($root_dir, FALSE);

    // Scan for extensions.
    $types = ['module', 'theme', 'profile'];
    $extensions = [];
    foreach($types as $type) {
      $extensions = array_merge(
        $extensions,
        $discovery->scan($type, $root_package->isDev())
      );
    }

    $composer_extensions = [];
    foreach ($extensions as $extension_name => $extension) {
      $json_file = $extension->getComposerJsonFile();
      if($json_file->exists()) {
        $composer_extensions[$extension_name] = $extension;
      }
    }
    return $composer_extensions;
  }

  /**
   * Get a list of all extensions currently managed by the root package.
   *
   * @return Composer\Package\PackageInterface[]
   *   Composer packages representing Drupal modules managed in the root
   *   package, keyed by package type.
   */
  public function getComposerManagedExtensions(Composer $composer) {
    $types = ['drupal-module', 'drupal-theme', 'drupal-profile'];
    $local_repository = $composer->getRepositoryManager()->getLocalRepository();
    $packages = $local_repository->getPackages();

    $composer_managed_extensions = [];
    foreach ($packages as $package) {
      $package_type = $package->getType();
      if (in_array($package_type, $types)) {
        $composer_managed_extensions[$package_type] = $package;
      }
    }
    return $composer_managed_extensions;
  }

  /**
   * Returns a list of extensions with Composer dependencies in the file system
   * which aren't present in the project composer.json file.
   *
   * @param string $root_dir
   * @param \Composer\Composer $composer
   *
   * @return \Mile23\DrupalMerge\Extension\Extension[]
   *   Extensions that have composer.json files which are not present in the
   *   project composer.json file, keyed by composer package name.
   */
  public function getUnmanagedComposerExtensions($root_dir, Composer $composer) {
    $root_package = $composer->getPackage();

    $composer_extensions = $this->getComposerExtensions($root_dir, $root_package);

    $composer_managed_extensions = $this->getComposerManagedExtensions($composer);
    $managed_extensions = [];
    foreach($composer_managed_extensions as $managed_extension) {
      $managed_extensions[$managed_extension->getName()] = $managed_extension;
    }
    $composer_managed_extensions = [];

    $unmanaged_extensions = [];
    foreach($composer_extensions as $composer_extension) {
      $json_file = $composer_extension->getComposerJsonFile();
      if ($json_file->exists()) {
        $package_json = $json_file->read();
        $package_name = isset($package_json['name']) ? $package_json['name'] : '';
        if(!array_key_exists($package_name, $managed_extensions)) {
          $unmanaged_extensions[$package_name] = $composer_extension;
        }
      }
    }
    return $unmanaged_extensions;
  }

}

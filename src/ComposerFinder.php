<?php

namespace Mile23\DrupalMerge;

use Composer\Composer;
use Composer\Package\RootPackageInterface;
use Composer\Repository\WritableRepositoryInterface;
use Mile23\DrupalMerge\Extension\ExtensionDiscovery;

/**
 * Utility class for doing Composer discovery in Drupal installations.
 */
class ComposerFinder {

  /**
   * Finds extensions in the file system with composer.json files.
   *
   * @param string $root_dir
   *   Root directory of the Drupal installation.
   * @param bool $include_tests
   *   (optional) Whether to scan Drupal test modules. Defaults to FALSE.
   *
   * @return \Mile23\DrupalMerge\Extension\Extension[]
   *   Array of extensions that have composer.json files, keyed by extension
   *   name.
   */
  public function getComposerExtensions($root_dir, $include_tests = FALSE) {
    // We're using our own fork of Drupal's ExtensionDiscovery.
    $discovery = new ExtensionDiscovery($root_dir, FALSE);

    // Scan for extensions.
    $types = ['module', 'theme', 'profile'];
    $extensions = [];
    foreach ($types as $type) {
      $extensions = array_merge(
        $extensions, $discovery->scan($type, $include_tests)
      );
    }

    $composer_extensions = [];
    foreach ($extensions as $extension_name => $extension) {
      $json_file = $extension->getComposerJsonFile();
      if ($json_file->exists()) {
        $composer_extensions[$extension_name] = $extension;
      }
    }
    return $composer_extensions;
  }

  /**
   * Get a list of all Drupal extensions currently managed by the root package.
   *
   * This finds Drupal extensions in the root package of the current project. It
   * does not reconcile them against the file system.
   *
   * @param \Composer\Composer $composer
   *   The Composer object.
   *
   * @return Composer\Package\PackageInterface[]
   *   Composer packages representing Drupal modules managed in the root
   *   package, keyed by package type.
   */
  public function getComposerManagedExtensions(WritableRepositoryInterface $local_repository) {
    $types = ['drupal-module', 'drupal-theme', 'drupal-profile'];

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
   * Finds unmanaged extensions.
   *
   * Returns a list of extensions with Composer dependencies in the file system
   * which aren't present in the root project composer.json file.
   *
   * @param string $root_dir
   *   Root directory of the Drupal installation.
   * @param \Composer\Composer $composer
   *   The Composer object.
   *
   * @return \Mile23\DrupalMerge\Extension\Extension[]
   *   Extensions that have composer.json files which are not present in the
   *   project composer.json file, keyed by composer package name.
   */
  public function getUnmanagedComposerExtensions($root_dir, Composer $composer) {
    $root_package = $composer->getPackage();

    $composer_extensions = $this->getComposerExtensions($root_dir, $root_package->isDev());

    $composer_managed_extensions = $this->getComposerManagedExtensions(
      $composer->getRepositoryManager()->getLocalRepository()
    );
    $managed_extensions = [];
    foreach ($composer_managed_extensions as $managed_extension) {
      $managed_extensions[$managed_extension->getName()] = $managed_extension;
    }
    $composer_managed_extensions = [];

    $unmanaged_extensions = [];
    foreach ($composer_extensions as $composer_extension) {
      $json_file = $composer_extension->getComposerJsonFile();
      if ($json_file->exists()) {
        $package_json = $json_file->read();
        $package_name = isset($package_json['name']) ? $package_json['name'] : '';
        if (!array_key_exists($package_name, $managed_extensions)) {
          $unmanaged_extensions[$package_name] = $composer_extension;
        }
      }
    }
    return $unmanaged_extensions;
  }

}

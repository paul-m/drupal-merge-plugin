<?php

/**
 * @file
 * Contains Mile23\DrupalMerge\MergePlugin.
 */

namespace Mile23\DrupalMerge;

use Composer\Factory;
use Composer\Package\RootPackageInterface;
use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Config\BootstrapConfigStorageFactory;
use Drupal\Core\Config\NullStorage;
use Wikimedia\Composer\MergePlugin as WikimediaMergePlugin;

/**
 * Extension of Wikimedia\Composer\MergePlugin for Drupal-specific use-cases.
 */
class MergePlugin extends WikimediaMergePlugin {

  /**
   * Determine whether the package being installed/updated is a Drupal project.
   *
   * @return bool
   *   TRUE if the package is named drupal/drupal and of type 'project', FALSE
   *   otherwise.
   */
  protected function isDrupalProject() {
    $package = $this->composer->getPackage();
    return $package->getName() == 'drupal/drupal' && $package->getType() == 'project';
  }

  /**
   * Helper method for pulling in Drupal's dependencies.
   *
   * Since bootstrap.inc is a dependency of ExtensionDiscovery, we have to
   * require it. Drupal core is likely in it's normal place, but it could also
   * be under vendor/.
   *
   * @param string $root_dir
   *   Path to DRUPAL_ROOT.
   */
  protected function bootstrapDrupal($root_dir) {
    $bootstrap_inc = $root_dir . '/core/includes/bootstrap.inc';
    if (!file_exists($bootstrap_inc)) {
      $bootstrap_inc = $root_dir . '/vendor/drupal/core/includes/bootstrap.inc';
    }
    require_once $bootstrap_inc;
  }

  /**
   * {@inheritdoc}
   */
  protected function mergeIncludes(array $include_patterns) {
    // Let the parent do its job first.
    parent::mergeIncludes($include_patterns);

    // If we're not a Drupal project, then there's nothing left to do.
    if (!$this->isDrupalProject()) {
      return;
    }
    // Make assumptions about the location of the root directory. This should be
    // DRUPAL_ROOT.
    $root_dir = realpath(dirname(Factory::getComposerFile()));

    // Merge modules' dependencies.
    $this->mergeModuleDependenciesForRoot($root_dir, $this->composer->getPackage());
  }

  /**
   * Perform the merge for Drupal extensions.
   *
   * @param string $root_dir
   *   Root directory of the Drupal installation. Usually this is the same as
   *   the directory where the composer.json file lives, but it might not be.
   * @param Package\RootPackageInterface $root_package
   *   The package into which to merge the discovered composer.json files.
   */
  protected function mergeModuleDependenciesForRoot($root_dir, RootPackageInterface $root_package) {
    // Because Drupal is bad at isolation, we have to minimally 'bootstrap' it.
    $this->bootstrapDrupal($root_dir);

    // Create our ExtensionDiscovery. We set $use_file_cache to FALSE so as to
    // avoid any dependencies involved in the caching system. Note that as of
    // this writing there is still a bug in the static file caching done by
    // ExtensionDiscovery: https://www.drupal.org/node/2605654
    $discovery = new ExtensionDiscovery($root_dir, FALSE);

    // Scan for modules.
    // @todo: Determine whether we should scan for profiles, themes.
    $modules = $discovery->scan('module', $root_package->isDev());

    foreach ($modules as $module_name => $module) {
      $local_composer_file = dirname($module->getPathname()) . '/composer.json';
      if (file_exists($root_dir . '/' . $local_composer_file)) {
        // Have the plugin merge the composer.json file.
        $this->mergeFile($root_package, $local_composer_file);
      }
    }
  }

  /**
   * Attempt to gather the list of enabled modules.
   *
   * @param string $root_dir
   *
   * @return \Drupal\Core\Extension\Extension[]
   *   Associative array of Drupal extension objects, keyed by module name, or
   *   empty array if no modules could be gleaned.
   */
  protected function gleanEnabledModules($autoloader) {
    try {
      $kernel = new DrupalKernel('install', $autoloader);
      $kernel->setSitePath('sites/default');
      $kernel->boot();
      return $kernel->getContainer()->get('module_handler')->getModuleList();
    }
    catch (\Exception $e) {
      return [];
    }
  }

  /**
   * Get a list of all modules currently managed by the root package.
   *
   * @return Composer\Package\PackageInterface[]
   *   Composer packages representing Drupal modules managed in the root
   *   package.
   */
  protected function getComposerManagedModules() {
    $local_repository = $this->composer->getRepositoryManager()->getLocalRepository();
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

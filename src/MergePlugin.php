<?php

/**
 * @file
 * Contains Mile23\DrupalMerge\MergePlugin.
 */

namespace Mile23\DrupalMerge;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\Installer;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Package\RootPackage;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\Core\DrupalKernel;
use Wikimedia\Composer\Logger;
use Wikimedia\Composer\MergePlugin as WikimediaMergePlugin;

/**
 * Extension of Wikimedia\Composer\MergePlugin for Drupal-specific use-cases.
 */
class MergePlugin extends WikimediaMergePlugin {

  /**
   * Offical package name
   */
  const PACKAGE_NAME = 'mile23/drupal-merge-plugin';

  /**
   * {@inheritdoc}
   */
/*  public function activate(Composer $composer, IOInterface $io) {
    parent::activate($composer, $io);
    $this->logger = new Logger('drupal-merge-plugin', $io);
  }*/

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return array_merge(
      parent::getSubscribedEvents(), [
      // We add package pre- events because Composer doesn't store our
      // dependencies. Therefore we have to rebuild every time we do anything.
      PackageEvents::PRE_PACKAGE_INSTALL => 'onDrupalPackageEvent',
      PackageEvents::PRE_PACKAGE_UNINSTALL => 'onDrupalPackageEvent',
      PackageEvents::PRE_PACKAGE_UPDATE => 'onDrupalPackageEvent',
      // We also want to be able to brag.
      PluginEvents::COMMAND => 'onCommand',
      ]
    );
  }

  /**
   * Helper method to do the Drupal dependency merging.
   *
   * @param RootPackageInterface $package
   */
  protected function mergeForDrupalRootProject(RootPackageInterface $package) {
    // Determine whether the package is a Drupal project.
    if ($package->getName() == 'drupal/drupal' && $package->getType() == 'project') {
      // @todo: Yes, there has to be a better way to get the composer.json file.
      $root_dir = realpath(dirname(Factory::getComposerFile()));
      // Perform the merge.
      $this->mergeModuleDependenciesForRoot($root_dir, $package);
    }
  }

  /**
   * Handle events dealing only with packages.
   *
   * @param PackageEvent $e
   */
  public function onDrupalPackageEvent(PackageEvent $e) {
    $this->mergeForDrupalRootProject($this->composer->getPackage());
  }

  /**
   * Tell everyone we're here.
   *
   * @param CommandEvent $e
   */
  public function onCommand(CommandEvent $e) {
    $output = $e->getOutput();
    $output->writeln('Using ' . self::PACKAGE_NAME);
  }

  /**
   * {@inheritdoc}
   */
  public function onInstallUpdateOrDump(Event $event) {
    // Give Wikimedia a chance to do it's thing.
    parent::onInstallUpdateOrDump($event);

    // Merge module dependencies.
    $this->mergeForDrupalRootProject($this->composer->getPackage());
  }

  /**
   * {@inheritdoc}
   */
  public function onPostPackageInstall(PackageEvent $event) {
    $op = $event->getOperation();
    if ($op instanceof InstallOperation) {
      $package = $op->getPackage()->getName();
      if ($package === self::PACKAGE_NAME) {
        // We've duplicated this method so that we can output our own name.
        $this->logger->info(self::PACKAGE_NAME . ' installed');
        $this->state->setFirstInstall(true);
        $this->state->setLocked(
          $event->getComposer()->getLocker()->isLocked()
        );
      }
    }
  }

  /**
   * Helper method for pulling in dependencies.
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
   * @param $autoloader
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
    } catch (\Exception $e) {
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

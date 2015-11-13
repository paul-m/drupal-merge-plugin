<?php

/**
 * @file
 * Contains Mile23\DrupalMerge\MergePlugin.
 */

namespace Mile23\DrupalMerge;

use Mile23\DrupalMerge\Extension\ExtensionDiscovery;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
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
    // Ideally we'd be able to give our logger a nice name, but autoloading
    // doesn't always work the way we'd hope.
    parent::activate($composer, $io);
    $this->logger = new Logger('drupal-merge-plugin', $io);
    } */

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return array_merge(
      parent::getSubscribedEvents(), [
      // We add package pre- events because Composer doesn't store our
      // dependencies. Therefore we have to rebuild every time we do anything.
      PackageEvents::PRE_PACKAGE_UNINSTALL => 'onDrupalPackageEvent',
      PackageEvents::PRE_PACKAGE_UPDATE => 'onDrupalPackageEvent',
      // We also want to be able to brag.
      PluginEvents::COMMAND => 'onCommand',
      ]
    );
  }

  /**
   * Handle events dealing only with packages.
   *
   * @param PackageEvent $e
   */
  public function onDrupalPackageEvent(PackageEvent $e) {
    error_log('>> ' . __METHOD__);
    $this->mergeForDrupalRootPackage($this->composer->getPackage());
  }

  /**
   * Tell everyone we're here.
   *
   * @param CommandEvent $e
   */
  public function onCommand(CommandEvent $e) {
    error_log('>> ' . __METHOD__);
    $output = $e->getOutput();
    $output->writeln('. Using ' . self::PACKAGE_NAME);
  }

  /**
   * {@inheritdoc}
   */
  public function onInstallUpdateOrDump(Event $event) {
    error_log('>> ' . __METHOD__);
    // Wikimedia is also registered as a plugin, so it will have a chance to
    // merge it's dependencies. Here we override and add our module
    // dependencies.
    $this->mergeForDrupalRootPackage($this->composer->getPackage());
  }

  /**
   * {@inheritdoc}
   */
  public function onPostPackageInstall(PackageEvent $event) {
    error_log('>> ' . __METHOD__);
    // This is a duplicate of Wikimedia's onPostPackageInstall.
    // @todo: Figure out how to make Logger available.
    $op = $event->getOperation();
    if ($op instanceof InstallOperation) {
      $package = $op->getPackage()->getName();
      if ($package === self::PACKAGE_NAME) {
        // $this->logger->info(self::PACKAGE_NAME . ' installed');
        $this->state->setFirstInstall(true);
        $this->state->setLocked(
          $event->getComposer()->getLocker()->isLocked()
        );
      }
    }
  }

  /**
   * Helper method to do the Drupal dependency merging.
   *
   * @param RootPackageInterface $package
   */
  protected function mergeForDrupalRootPackage(RootPackageInterface $package) {
    if ($this->state->isFirstInstall()) {
      return;
    }
    // Determine whether the package is a Drupal project.
    if ($package->getName() == 'drupal/drupal' && $package->getType() == 'project') {
      // @todo: There has to be a better way to get the root directory.
      $root_dir = realpath(dirname(Factory::getComposerFile()));
      // Perform the merge.
      $this->mergeModuleDependenciesForRoot($root_dir, $package);
      return;
    }
    $this->logger->debug('merge rejected for: ' . $package->getName());
  }

  /**
   * Perform the merge for Drupal extensions.
   *
   * Given a root directory for the Drupal installation and a Composer package
   * representing the root installation, merge composer.json files from
   * discovered extensions/modules.
   *
   * @param string $root_dir
   *   Root directory of the Drupal installation. Usually this is the same as
   *   the directory where the composer.json file lives, but it might not be.
   * @param Package\RootPackageInterface $root_package
   *   The package into which to merge the discovered composer.json files.
   */
  protected function mergeModuleDependenciesForRoot($root_dir, RootPackageInterface $root_package) {
    // We're using our own fork of Drupal's ExtensionDiscovery.
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
   * Get a list of all modules currently managed by the root package.
   *
   * Currently unused.
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

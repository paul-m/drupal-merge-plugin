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
use Wikimedia\Composer\Logger;
use Wikimedia\Composer\MergePlugin as WikimediaMergePlugin;

/**
 * Extension of Wikimedia\Composer\MergePlugin for Drupal-specific use-cases.
 */
class MergePlugin extends WikimediaMergePlugin {

  /**
   * Offical package name.
   */
  const PACKAGE_NAME = 'mile23/drupal-merge-plugin';

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io) {
    parent::activate($composer, $io);
    // Replace the wikimedia logger with ours.
    $this->logger = new Logger('drupal-merge-plugin', $io);
  }

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
   *   The event object.
   */
  public function onDrupalPackageEvent(PackageEvent $e) {
    error_log('>> ' . __METHOD__);
    $this->mergeForDrupalRootPackage($e->getComposer());
  }

  /**
   * Tell everyone we're here.
   *
   * @param CommandEvent $e
   *   The event object.
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
    $this->mergeForDrupalRootPackage($event->getComposer());
  }

  /**
   * {@inheritdoc}
   */
  public function onPostPackageInstall(PackageEvent $event) {
    // This is a duplicate of Wikimedia's onPostPackageInstall.
    $op = $event->getOperation();
    if ($op instanceof InstallOperation) {
      $package = $op->getPackage()->getName();
      if ($package === self::PACKAGE_NAME) {
        $this->logger->info(self::PACKAGE_NAME . ' installed');
        $this->state->setFirstInstall(TRUE);
        $this->state->setLocked(
          $event->getComposer()->getLocker()->isLocked()
        );
      }
    }
  }

  /**
   * Merge Drupal dependencies from extensions.
   *
   * @param \Composer\Composer $composer
   *   The Composer object.
   */
  protected function mergeForDrupalRootPackage(Composer $composer) {
    if ($this->state->isFirstInstall()) {
      return;
    }
    $packge = $composer->getPackage();
    // Determine whether the package is a Drupal project.
    if ($package->getName() == 'drupal/drupal' && $package->getType() == 'project') {
      // @todo: There has to be a better way to get the root directory.
      $root_dir = realpath(dirname(Factory::getComposerFile()));
      // Perform the merge.

      $finder = new ComposerFinder();
      $unmanaged_extensions = $finder->getUnmanagedComposerExtensions($root_dir, $composer);

      $extensions = [];
      foreach ($unmanaged_extensions as $unmanaged_extension) {
        $extensions[] = $unmanaged_extension->getName();
      }




      $this->mergeExtensionDependenciesForRoot($root_dir, $package);
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
   * @param \Composer\Composer $composer
   *   The Composer object.
   */
  protected function mergeExtensionDependenciesForRoot($root_dir, Composer $composer) {
    // Glean the root package.
    $root_package = $composer->getPackage();

    $finder = new ComposerFinder();
    $unmanaged_dependencies = $finder->getUnmanagedComposerExtensions($root_dir, $composer);
    foreach($unmanaged_dependencies as $dependency) {
        $this->mergeFile($root_package, $dependency->getComposerJsonFile()->getPath());
    }
  }

}

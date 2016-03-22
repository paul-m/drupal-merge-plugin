<?php

namespace Mile23\DrupalMerge;

use Composer\Factory;
use Composer\Script\Event;
use Mile23\DrupalMerge\ComposerFinder;

class Script {

  protected static function getRootDir() {
    // @todo: There has to be a better way to get the root directory.
    return realpath(dirname(Factory::getComposerFile()));
  }

  public static function listExtensions(Event $event) {

    $root_package = $event->getComposer()->getPackage();
    $root_dir = static::getRootDir();

    $finder = new ComposerFinder();
    $extensions = $finder->getComposerExtensions($root_dir, $root_package->isDev());

    $event->getIO()->write(' Extensions with composer.json files: <info>' . implode(', ', array_keys($extensions)) . '</info>');
  }

  public static function listManagedExtensions(Event $event) {
    $finder = new ComposerFinder();
    $packages = $finder->getComposerManagedExtensions($event->getComposer());

    $extensions = [];
    foreach ($packages as $package) {
      $extensions[] = $package->getName();
    }

    $event->getIO()->write(' Extensions managed by this project: <info>' . implode(', ', $extensions) . '</info>');
  }

  /**
   * Displays a list of all extensions not present in the project composer.json.
   *
   * Did you add a module without using composer, and it has a composer.json
   * file? It will show up in this list.
   *
   * @param Event $event
   */
  public static function listUnmanagedExtensions(Event $event) {

    $root_dir = static::getRootDir();

    $finder = new ComposerFinder();
    $unmanaged_extensions = $finder->getUnmanagedComposerExtensions($root_dir, $event->getComposer());

    $extensions = [];
    foreach ($unmanaged_extensions as $unmanaged_extension) {
      $extensions[] = $unmanaged_extension->getName();
    }

    $event->getIO()->write(' Unmanaged extensions with composer.json files: <info>' . implode(', ', $extensions) . '</info>');
  }

}

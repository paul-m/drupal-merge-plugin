<?php

namespace Mile23\DrupalMerge;

use Composer\Factory;
use Composer\Script\Event;
use Mile23\DrupalMerge\ComposerFinder;

/**
 * Support composer scripts.
 *
 * This class exists so that you can add useful scripts to your composer.json
 * file. You can add them like this:
 *
 * @code
 *   "scripts": {
 *     "list-extensions": "Mile23\\DrupalMerge\\Script::listExtensions",
 *     "list-managed-extensions": "Mile23\\DrupalMerge\\Script::listManagedExtensions",
 *     "list-unmanaged-extensions": "Mile23\\DrupalMerge\\Script::listUnmanagedExtensions",
 *   },
 * @endcode
 */
class Script {

  /**
   * Gets the path to the root of the composer project.
   *
   * @return string
   *   The path to the root of the composer project.
   */
  protected static function getRootDir() {
    // @todo: There has to be a better way to get the root directory.
    return realpath(dirname(Factory::getComposerFile()));
  }

  /**
   * Shows a list of Drupal extensions that have composer.json files.
   *
   * @param Event $event
   *   Script event provided by Composer.
   */
  public static function listExtensions(Event $event) {

    $root_package = $event->getComposer()->getPackage();
    $root_dir = static::getRootDir();

    $finder = new ComposerFinder();
    $extensions = $finder->getComposerExtensions($root_dir, $root_package->isDev());

    $event->getIO()->write(' Extensions with composer.json files: <info>' . implode(', ', array_keys($extensions)) . '</info>');
  }

  /**
   * Shows a list of Drupal extensions in the root project composer.json file.
   *
   * Lists extensions which are explicit dependencies of the root project.
   *
   * @param Event $event
   *   Script event provided by Composer.
   */
  public static function listManagedExtensions(Event $event) {
    $finder = new ComposerFinder();
    $packages = $finder->getComposerManagedExtensions(
      $event->getComposer()->getRepositoryManager()->getLocalRepository()
    );

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
   *   Script event provided by Composer.
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

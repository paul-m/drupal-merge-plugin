<?php

namespace Mile23\DrupalMerge;

use Composer\Factory;
use Composer\Script\Event;
use Mile23\DrupalMerge\ComposerFinder;

class Script {

  public static function listExtensions(Event $event) {

    $root_package = $event->getComposer()->getPackage();

    // @todo: There has to be a better way to get the root directory.
    $root_dir = realpath(dirname(Factory::getComposerFile()));

    $finder = new ComposerFinder();
    $extensions = $finder->getComposerModules($root_dir, $root_package);

    $event->getIO()->write(' Extensions with composer.json files: ' . implode(', ', array_keys($extensions)));
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

    $root_package = $event->getComposer()->getPackage();

    // @todo: There has to be a better way to get the root directory.
    $root_dir = realpath(dirname(Factory::getComposerFile()));

    $finder = new ComposerFinder();
    $extensions = array_keys($finder->getComposerModules($root_dir, $root_package));

    $managed_extensions = ComposerFinder::getComposerManagedModules($event->getComposer());

    $unmanaged_extensions = [];
    foreach($managed_extensions as $managed) {
      if ($managed->getType() == 'drupal-module') {
        if (!in_array($managed->getName(), $extensions)) {
          $unmanaged_extensions[] = 'foo';
        }
      }
    }

    $event->getIO()->write(' Unmanaged extensions with composer.json files: ' . implode(', ', array_keys($extensions)));
  }

}

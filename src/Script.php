<?php

namespace Mile23\DrupalMerge;

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

}

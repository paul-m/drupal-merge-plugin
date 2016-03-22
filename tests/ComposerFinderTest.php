<?php

namespace Mile23\DrupalMerge\Test;

use Mile23\DrupalMerge\ComposerFinder;
use org\bovigo\vfs\vfsStream;

class ComposerFinderTest extends \PHPUnit_Framework_TestCase {

  /**
   * @return array
   *   - Expected number of composer.json files found.
   *   - Filesystem array. Suitable for use with vfsStream.
   */
  public function provideModuleFilesystem() {
    // Prevent typos.
    $cj = 'composer.json';
    $composer_count = 0;
    $dirs = [
      'modules' => [
        'deps' => [],
        'no_deps' => [],
      ],
      'sites' => [
        'all' => [
          'modules' => [
            'site_deps' => [],
          ],
        ],
      ],
    ];
    $dirs['modules']['no_deps']['no_deps.info.yml'] = "name: No Deps\ntype: module";
    $dirs['modules']['deps']['deps.info.yml'] = "name: Deps\ntype: module";
    $dirs['modules']['deps'][$cj] = "{\"name\": \"paul/test\",\"require\": {\"vendor/test\": \"version@dev\"}}";
    $composer_count++;
    $dirs['sites']['all']['modules']['site_deps']['site_deps.info.yml'] = "name: Sites All Deps\ntype: module";
    $dirs['sites']['all']['modules']['site_deps'][$cj] = "{\"name\": \"paul/test\",\"require\": {\"vendor/test\": \"version@dev\"}}";
    $composer_count++;

    return [
      [$composer_count, $dirs],
    ];
  }

  /**
   * @dataProvider provideModuleFilesystem
   */
  public function testGetComposerExtensions($expected_composer_count, $filesystem) {
    // Set up the filesystem.
    $root = vfsStream::setup('test', NULL, $filesystem);

    $finder = new ComposerFinder();
    $extensions = $finder->getComposerExtensions(vfsStream::url('test'), FALSE);

    // Make sure we got the right number of results.
    $this->assertEquals($expected_composer_count, count($extensions));
    // Check for expected modules.
    $modules = ['deps', 'site_deps'];
    foreach($modules as $module) {
      $this->assertArrayHasKey($module, $extensions);
    }
  }


  public function testGetComposerManagedExtensions() {
    $this->markTestIncomplete();
  }

  public function testGetUnmanagedComposerExtensions() {
    $this->markTestIncomplete();
  }

}

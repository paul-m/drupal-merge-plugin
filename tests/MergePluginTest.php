<?php

namespace Mile23\DrupalMerge\Test;

use Mile23\DrupalMerge\MergePlugin;
use org\bovigo\vfs\vfsStream;
use Wikimedia\Composer\Merge\PluginState;
use Composer\Composer;
use Composer\Package\RootPackageInterface;

/**
 * @coversDefaultClass Mile23\DrupalMerge\MergePlugin
 *
 * @group DrupalMerge
 */
class MergePluginTest extends \PHPUnit_Framework_TestCase {

  /**
   * @return array
   *   - Expected number of composer.json files found.
   *   - Filesystem array. Suitable for use with vfsStream.
   */
  public function provideModuleFilesystem() {
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
    $dirs['modules']['deps']['composer.json'] = "{\"name\": \"paul/test\",\"require\": {\"vendor/test\": \"version@dev\"}}";
    $composer_count++;
    $dirs['sites']['all']['modules']['site_deps']['site_deps.info.yml'] = "name: Sites All Deps\ntype: module";
    $dirs['sites']['all']['modules']['site_deps']['composer.json'] = "{\"name\": \"paul/test\",\"require\": {\"vendor/test\": \"version@dev\"}}";
    $composer_count++;
    return [
      [$composer_count, $dirs],
    ];
  }

  /**
   * @covers ::mergeModuleDependenciesForRoot
   * @dataProvider provideModuleFilesystem
   */
  public function testMergeModuleDependenciesForRoot($expected_composer_count, $filesystem) {
    // Gather our filesystem.
    vfsStream::setup('test', NULL, $filesystem);

    // Make a mock root package.
    $mock_root_package = $this->getMockBuilder(RootPackageInterface::class)
      ->disableOriginalConstructor()
      ->getMockForAbstractClass();

    // We have to require bootstrap.inc because neither our mock nor Drupal can.
    require_once __DIR__ . '/../vendor/drupal/core/includes/bootstrap.inc';

    // Mock the merge plugin. We have to stub bootstrapDrupal so that it won't
    // try to require bootstrap.inc from the vfsStream.
    $merge_plugin = $this->getMockBuilder(MergePlugin::class)
      ->disableOriginalConstructor()
      ->setMethods(['bootstrapDrupal', 'mergeFile'])
      ->getMock();
    $merge_plugin->expects($this->once())
      ->method('bootstrapDrupal')
      ->with(vfsStream::url('test'));
    $merge_plugin->expects($this->exactly($expected_composer_count))
      ->method('mergeFile')
      ->with($mock_root_package);

    $ref_merge = new \ReflectionMethod($merge_plugin, 'mergeModuleDependenciesForRoot');
    $ref_merge->setAccessible(TRUE);
    $ref_merge->invokeArgs($merge_plugin, [vfsStream::url('test'), $mock_root_package]);
  }

  /**
   * @covers ::isDrupalProject
   */
  public function testIsDrupalProject() {
    $this->markTestIncomplete();
  }

}

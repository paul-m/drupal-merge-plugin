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

  protected function getDrupalFilesystemStructure() {
    $dirs = [
      'modules' => [
        'deps' => [],
        'no_deps' => [],
      ]
    ];
    $dirs['modules']['no_deps']['no_deps.info.yml'] = "name: No Deps\ntype: module";
    $dirs['modules']['deps']['deps.info.yml'] = "name: Deps\ntype: module";
    $dirs['modules']['deps']['composer.json'] = "{\"name\": \"paul/test\",\"require\": {\"vendor/test\": \"version@dev\"}}";
    return $dirs;
  }

  /**
   * @covers ::mergeModuleDependenciesForRoot
   */
  public function testMergeModuleDependenciesForRoot() {
    // Gather our filesystem.
    vfsStream::setup('test', NULL, $this->getDrupalFilesystemStructure());

    // Make a mock root package.
    $mock_root_package = $this->getMockBuilder(RootPackageInterface::class)
      ->disableOriginalConstructor()
      ->getMockForAbstractClass();

    // We have to require bootstrap.inc because neither our mock nor Drupal can.
    require_once __DIR__ . '/../vendor/drupal/core/includes/bootstrap.inc';

    $merge_plugin = $this->getMockBuilder(MergePlugin::class)
      ->disableOriginalConstructor()
      ->setMethods(['bootstrapDrupal', 'mergeFile'])
      ->getMock();
    $merge_plugin->expects($this->once())
      ->method('bootstrapDrupal')
      ->with(vfsStream::url('test'));
    $merge_plugin->expects($this->once())
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

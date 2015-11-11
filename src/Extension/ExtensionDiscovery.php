<?php

/**
 * @file
 * Contains \Drupal\Core\Extension\ExtensionDiscovery.
 *
 * Yes, it really does. Modified with this bugfix:
 * https://www.drupal.org/node/2605654 And also modified to have fewer unneeded
 * dependencies.
 */

namespace Mile23\DrupalMerge\Extension;

use Mile23\DrupalMerge\Extension\Discovery\RecursiveExtensionFilterIterator;

/**
 * Discovers available extensions in the filesystem.
 */
class ExtensionDiscovery {

  /**
   * Origin directory weight: Core.
   */
  const ORIGIN_CORE = 0;

  /**
   * Origin directory weight: Installation profile.
   */
  const ORIGIN_PROFILE = 1;

  /**
   * Origin directory weight: sites/all.
   */
  const ORIGIN_SITES_ALL = 2;

  /**
   * Origin directory weight: Site-wide directory.
   */
  const ORIGIN_ROOT = 3;

  /**
   * Origin directory weight: Parent site directory of a test site environment.
   */
  const ORIGIN_PARENT_SITE = 4;

  /**
   * Origin directory weight: Site-specific directory.
   */
  const ORIGIN_SITE = 5;

  /**
   * Regular expression to match PHP function names.
   *
   * @see http://php.net/manual/functions.user-defined.php
   */
  const PHP_FUNCTION_PATTERN = '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/';

  /**
   * InfoParser instance for parsing .info.yml files.
   *
   * @var \Drupal\Core\Extension\InfoParser
   */
  protected $infoParser;

  /**
   * Static root path, useful for invalidating static::$files when needed.
   *
   * @var string
   */
  protected static $cacheRoot;

  /**
   * Previously discovered files keyed by origin directory and extension type.
   *
   * @var array
   */
  protected static $files = array();

  /**
   * List of installation profile directories to additionally scan.
   *
   * @var array
   */
  protected $profileDirectories;

  /**
   * The app root for the current operation.
   *
   * @var string
   */
  protected $root;

  /**
   * The file cache object.
   *
   * @var \Drupal\Component\FileCache\FileCacheInterface
   */
  protected $fileCache;

  /**
   * The site path.
   *
   * @var string
   */
  protected $sitePath;

  /**
   * Constructs a new ExtensionDiscovery object.
   *
   * @param string $root
   *   The app root.
   * @param bool $use_file_cache
   *   (optional) Whether file cache should be used. Defaults to TRUE.
   * @param string[]|null $profile_directories
   *   (optional) The available profile directories. Defaults to NULL, which
   *   will cause this object to search for profile directories based on
   *   settings.
   * @param string|null $site_path
   *   (optional) The site path within the Drupal installation. Defaults to
   *   NULL, which will cause this object to determine the site based on the
   *   request information.
   */
  public function __construct($root, $use_file_cache = TRUE, $profile_directories = NULL, $site_path = NULL) {
    $this->root = $root;
    $this->profileDirectories = $profile_directories;
    $this->sitePath = $site_path;
    // Invalidate the cache if we're using a new root directory. We must do this
    // since $files is static.
    if ($this->root != static::$cacheRoot) {
      static::$files = [];
      static::$cacheRoot = $this->root;
    }
  }

  /**
   * Discovers available extensions of a given type.
   *
   * Finds all extensions (modules, themes, etc) that exist on the site. It
   * searches in several locations. For instance, to discover all available
   * modules:
   * @code
   * $listing = new ExtensionDiscovery(\Drupal::root());
   * $modules = $listing->scan('module');
   * @endcode
   *
   * The following directories will be searched (in the order stated):
   * - the core directory; i.e., /core
   * - the installation profile directory; e.g., /core/profiles/standard
   * - the legacy site-wide directory; i.e., /sites/all
   * - the site-wide directory; i.e., /
   * - the site-specific directory; e.g., /sites/example.com
   *
   * The information is returned in an associative array, keyed by the extension
   * name (without .info.yml extension). Extensions found later in the search
   * will take precedence over extensions found earlier - unless they are not
   * compatible with the current version of Drupal core.
   *
   * @param string $type
   *   The extension type to search for. One of 'profile', 'module', 'theme', or
   *   'theme_engine'.
   * @param bool $include_tests
   *   (optional) Whether to explicitly include or exclude test extensions. By
   *   default, test extensions are only discovered when in a test environment.
   *
   * @return \Drupal\Core\Extension\Extension[]
   *   An associative array of Extension objects, keyed by extension name.
   */
  public function scan($type, $include_tests = NULL) {
    // Determine the installation profile directories to scan for extensions,
    // unless explicit profile directories have been set. Exclude profiles as we
    // cannot have profiles within profiles. If no profiles are specified in the
    // constructor, profileDirectories will be NULL.
    if (($this->profileDirectories === NULL) && $type != 'profile') {
//      $this->setProfileDirectoriesFromSettings();
    }

    // Search the core directory.
    $searchdirs[static::ORIGIN_CORE] = 'core';

    // Search the legacy sites/all directory.
    $searchdirs[static::ORIGIN_SITES_ALL] = 'sites/all';

    // Search for contributed and custom extensions in top-level directories.
    // The scan uses a whitelist to limit recursion to the expected extension
    // type specific directory names only.
    $searchdirs[static::ORIGIN_ROOT] = '';
      
    $searchdirs[static::ORIGIN_SITE] = $this->sitePath;

    $files = array();
    foreach ($searchdirs as $dir) {
      // Discover all extensions in the directory, unless we did already.
      if (!isset(static::$files[$dir][$include_tests])) {
        static::$files[$dir][$include_tests] = $this->scanDirectory($dir, $include_tests);
      }
      // Only return extensions of the requested type.
      if (isset(static::$files[$dir][$include_tests][$type])) {
        $files += static::$files[$dir][$include_tests][$type];
      }
    }

    // Process and return the list of extensions keyed by extension name.
    return $this->process($files);
  }

  /**
   * Gets the installation profile directories to be scanned.
   *
   * @return array
   *   A list of installation profile directory paths relative to the system
   *   root directory.
   */
  public function getProfileDirectories() {
    return $this->profileDirectories;
  }

  /**
   * Sets explicit profile directories to scan.
   *
   * @param array $paths
   *   A list of installation profile dir ectory paths relative to the system
   *   root directory (without trailing slash) to search for extensions.
   *
   * @return $this
   */
  public function setProfileDirectories(array $paths = NULL) {
    $this->profileDirectories = $paths;
    return $this;
  }

  /**
   * Sorts the discovered extensions.
   *
   * @param \Drupal\Core\Extension\Extension[] $all_files
   *   The list of all extensions.
   * @param array $weights
   *   An array of weights, keyed by originating directory.
   *
   * @return \Drupal\Core\Extension\Extension[]
   *   The sorted list of extensions.
   */
  protected function sort(array $all_files, array $weights) {
    $origins = array();
    $profiles = array();
    foreach ($all_files as $key => $file) {
      // If the extension does not belong to a profile, just apply the weight
      // of the originating directory.
      if (strpos($file->subpath, 'profiles') !== 0) {
        $origins[$key] = $weights[$file->origin];
        $profiles[$key] = NULL;
      }
      // If the extension belongs to a profile but no profile directories are
      // defined, then we are scanning for installation profiles themselves.
      // In this case, profiles are sorted by origin only.
      elseif (empty($this->profileDirectories)) {
        $origins[$key] = static::ORIGIN_PROFILE;
        $profiles[$key] = NULL;
      }
      else {
        // Apply the weight of the originating profile directory.
        foreach ($this->profileDirectories as $weight => $profile_path) {
          if (strpos($file->getPath(), $profile_path) === 0) {
            $origins[$key] = static::ORIGIN_PROFILE;
            $profiles[$key] = $weight;
            continue 2;
          }
        }
      }
    }
    // Now sort the extensions by origin and installation profile(s).
    // The result of this multisort can be depicted like the following matrix,
    // whereas the first integer is the weight of the originating directory and
    // the second is the weight of the originating installation profile:
    // 0   core/modules/node/node.module
    // 1 0 profiles/parent_profile/modules/parent_module/parent_module.module
    // 1 1 core/profiles/testing/modules/compatible_test/compatible_test.module
    // 2   sites/all/modules/common/common.module
    // 3   modules/devel/devel.module
    // 4   sites/default/modules/custom/custom.module
    array_multisort($origins, SORT_ASC, $profiles, SORT_ASC, $all_files);

    return $all_files;
  }

  /**
   * Processes the filtered and sorted list of extensions.
   *
   * Extensions discovered in later search paths override earlier, unless they
   * are not compatible with the current version of Drupal core.
   *
   * @param \Drupal\Core\Extension\Extension[] $all_files
   *   The sorted list of all extensions that were found.
   *
   * @return \Drupal\Core\Extension\Extension[]
   *   The filtered list of extensions, keyed by extension name.
   */
  protected function process(array $all_files) {
    $files = array();
    // Duplicate files found in later search directories take precedence over
    // earlier ones; they replace the extension in the existing $files array.
    foreach ($all_files as $file) {
      $files[$file->getName()] = $file;
    }
    return $files;
  }

  /**
   * Recursively scans a base directory for the requested extension type.
   *
   * @param string $dir
   *   A relative base directory path to scan, without trailing slash.
   * @param bool $include_tests
   *   Whether to include test extensions. If FALSE, all 'tests' directories are
   *   excluded in the search.
   *
   * @return array
   *   An associative array whose keys are extension type names and whose values
   *   are associative arrays of \Drupal\Core\Extension\Extension objects, keyed
   *   by absolute path name.
   *
   * @see \Drupal\Core\Extension\Discovery\RecursiveExtensionFilterIterator
   */
  protected function scanDirectory($dir, $include_tests) {
    $files = array();

    // In order to scan top-level directories, absolute directory paths have to
    // be used (which also improves performance, since any configured PHP
    // include_paths will not be consulted). Retain the relative originating
    // directory being scanned, so relative paths can be reconstructed below
    // (all paths are expected to be relative to $this->root).
    $dir_prefix = ($dir == '' ? '' : "$dir/");
    $absolute_dir = ($dir == '' ? $this->root : $this->root . "/$dir");

    if (!is_dir($absolute_dir)) {
      return $files;
    }
    // Use Unix paths regardless of platform, skip dot directories, follow
    // symlinks (to allow extensions to be linked from elsewhere), and return
    // the RecursiveDirectoryIterator instance to have access to getSubPath(),
    // since SplFileInfo does not support relative paths.
    $flags = \FilesystemIterator::UNIX_PATHS;
    $flags |= \FilesystemIterator::SKIP_DOTS;
    $flags |= \FilesystemIterator::FOLLOW_SYMLINKS;
    $flags |= \FilesystemIterator::CURRENT_AS_SELF;
    $directory_iterator = new \RecursiveDirectoryIterator($absolute_dir, $flags);

    // Filter the recursive scan to discover extensions only.
    // Important: Without a RecursiveFilterIterator, RecursiveDirectoryIterator
    // would recurse into the entire filesystem directory tree without any kind
    // of limitations.
    $filter = new RecursiveExtensionFilterIterator($directory_iterator);
    $filter->acceptTests($include_tests);

    // The actual recursive filesystem scan is only invoked by instantiating the
    // RecursiveIteratorIterator.
    $iterator = new \RecursiveIteratorIterator($filter,
      \RecursiveIteratorIterator::LEAVES_ONLY,
      // Suppress filesystem errors in case a directory cannot be accessed.
      \RecursiveIteratorIterator::CATCH_GET_CHILD
    );

    foreach ($iterator as $key => $fileinfo) {
      // All extension names in Drupal have to be valid PHP function names due
      // to the module hook architecture.
      if (!preg_match(static::PHP_FUNCTION_PATTERN, $fileinfo->getBasename('.info.yml'))) {
        continue;
      }

      if ($this->fileCache && $cached_extension = $this->fileCache->get($fileinfo->getPathName())) {
        $files[$cached_extension->getType()][$key] = $cached_extension;
        continue;
      }

      // Determine extension type from info file.
      $type = FALSE;
      $file = $fileinfo->openFile('r');
      while (!$type && !$file->eof()) {
        preg_match('@^type:\s*(\'|")?(\w+)\1?\s*$@', $file->fgets(), $matches);
        if (isset($matches[2])) {
          $type = $matches[2];
        }
      }
      if (empty($type)) {
        continue;
      }
      $name = $fileinfo->getBasename('.info.yml');
      $pathname = $dir_prefix . $fileinfo->getSubPathname();

      // Determine whether the extension has a main extension file.
      // For theme engines, the file extension is .engine.
      if ($type == 'theme_engine') {
        $filename = $name . '.engine';
      }
      // For profiles/modules/themes, it is the extension type.
      else {
        $filename = $name . '.' . $type;
      }
      if (!file_exists(dirname($pathname) . '/' . $filename)) {
        $filename = NULL;
      }

      $extension = new Extension($this->root, $type, $pathname, $filename);

      // Track the originating directory for sorting purposes.
      $extension->subpath = $fileinfo->getSubPath();
      $extension->origin = $dir;

      $files[$type][$key] = $extension;

      if ($this->fileCache) {
        $this->fileCache->set($fileinfo->getPathName(), $extension);
      }
    }
    return $files;
  }

  /**
   * Returns a parser for .info.yml files.
   *
   * @return \Drupal\Core\Extension\InfoParser
   *   The InfoParser instance.
   */
  protected function getInfoParser() {
    if (!isset($this->infoParser)) {
      $this->infoParser = new InfoParser();
    }
    return $this->infoParser;
  }

}

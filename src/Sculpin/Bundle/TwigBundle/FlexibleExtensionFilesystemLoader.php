<?php

/*
 * This file is a part of Sculpin.
 *
 * (c) Dragonfly Development Inc.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sculpin\Bundle\TwigBundle;

use Dflydev\Symfony\FinderFactory\FinderFactory;
use Dflydev\Symfony\FinderFactory\FinderFactoryInterface;
use Sculpin\Core\Event\SourceSetEvent;
use Sculpin\Core\Sculpin;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Flexible Extension Filesystem Loader.
 *
 * @author Beau Simensen <beau@dflydev.com>
 */
class FlexibleExtensionFilesystemLoader implements \Twig_LoaderInterface, EventSubscriberInterface
{
    /**
     * Filesystem loader
     *
     * @var FilesystemLoader
     */
    protected $filesystemLoader;

    protected $finderFactory;

    protected $sourceDir;
    protected $sourcePaths = array();
    protected $paths = array();
    protected $extensions= array();

    protected $cachedActualNames = array();
    protected $cachedNames = array();
    protected $cachedExceptions = array();

    /**
     * Constructor.
     *
     * @param array  $paths      Paths
     * @param array  $extensions Extensions
     */
    public function __construct($sourceDir, array $sourcePaths, array $paths, array $extensions, FinderFactoryInterface $finderFactory = null)
    {
        $this->sourceDir = $sourceDir;
        $this->sourcePaths = $sourcePaths;
        $this->paths = $paths;
        $this->extensions = array_map(function($ext) {
            return $ext?'.'.$ext:$ext;
        }, $extensions);
        $this->finderFactory = $finderFactory ?: new FinderFactory();
    }

    public function refreshCache()
    {
        $sourceDir = $this->sourceDir;

        $mappedSourcePaths = array_map(function ($path) use ($sourceDir) {
            return $sourceDir.'/'.$path;
        }, $this->sourcePaths);

        $allPaths = array_merge(
            array_filter($mappedSourcePaths, function($path) {
                return file_exists($path);
            }),
            array_filter($this->paths, function($path) {
                return file_exists($path);
            })
        );

        $this->cachedActualNames = array();

        foreach ($allPaths as $path) {
            $filesystemLoader = new FilesystemLoader(array($path));

            $files = $this
                ->finderFactory->createFinder()
                ->files()
                ->ignoreVCS(true)
                ->followLinks()
                ->in($path);

            foreach ($files as $file) {
                if (isset($this->cachedActualNames[$file->getRelativePathName()])) {
                    // We already know about this file.
                    continue;
                }

                $this->cachedActualNames[$file->getRelativePathName()] = $filesystemLoader;
            }
        }
    }

    protected function resolveName($name)
    {
        if (isset($this->cachedNames[$name])) {
            return $this->cachedNames[$name];
        }

        if (isset($this->cachedExceptions[$name])) {
            throw $this->cachedExceptions[$name];
        }

        foreach ($this->extensions as $extension) {
            $actualName = $name.$extension;
            if (isset($this->cachedActualNames[$actualName])) {
                return $this->cachedNames[$name] = array($actualName, $this->cachedActualNames[$actualName]);
            }
        }

        throw $this->cachedExceptions[$name] = new \Twig_Error_Loader(
            sprintf('The template named "%s" does not exist.', $name)
        );
    }


    /**
     * Gets the source code of a template, given its name.
     *
     * @param string $name The name of the template to load
     *
     * @return string The template source code
     */
    public function getSource($name)
    {
        list ($actualName, $filesystemLoader) = $this->resolveName($name);

        return $filesystemLoader->getSource($actualName);
    }

    /**
     * Gets the cache key to use for the cache for a given template name.
     *
     * @param string $name The name of the template to load
     *
     * @return string The cache key
     */
    public function getCacheKey($name)
    {
        list ($actualName, $filesystemLoader) = $this->resolveName($name);

        return $filesystemLoader->getCacheKey($actualName);
    }

    /**
     * Returns true if the template is still fresh.
     *
     * @param string    $name The template name
     * @param timestamp $time The last modification time of the cached template
     *
     * @return bool
     */
    public function isFresh($name, $time)
    {
        list ($actualName, $filesystemLoader) = $this->resolveName($name);

        return $filesystemLoader->isFresh($actualName.$extension, $time);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            Sculpin::EVENT_BEFORE_RUN => 'beforeRun',
        );
    }

    public function beforeRun(SourceSetEvent $sourceSetEvent)
    {
        if ($sourceSetEvent->sourceSet()->newSources()) {
            $this->refreshCache();
        }
    }
}

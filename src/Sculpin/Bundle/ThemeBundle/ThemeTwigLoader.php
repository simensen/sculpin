<?php

namespace Sculpin\Bundle\ThemeBundle;

use Sculpin\Bundle\TwigBundle\FlexibleExtensionFilesystemLoader;
use Sculpin\Core\Event\SourceSetEvent;
use Sculpin\Core\Sculpin;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ThemeTwigLoader implements \Twig_LoaderInterface, EventSubscriberInterface
{
    private $themeRegistry;
    private $extensions;
    private $flexibleExtensionFilesystemLoader;

    public function __construct(ThemeRegistry $themeRegistry, array $extensions)
    {
        $this->themeRegistry = $themeRegistry;
        $this->extensions = $extensions;
    }

    private function initThemeLoader()
    {
        $paths = array();

        $theme = $this->themeRegistry->findActiveTheme();
        if (null !== $theme) {
            $paths = $this->findPaths($theme);
            if (isset($theme['parent'])) {
                $paths = $this->findPaths($theme['parent'], $paths);
            }
        }
        $this->flexibleExtensionFilesystemLoader = new FlexibleExtensionFilesystemLoader(
            '',
            array(),
            $paths,
            $this->extensions
        );

        $this->flexibleExtensionFilesystemLoader->refreshCache();
    }

    private function findPaths($theme, array $paths = array())
    {
        foreach (array('_views', '_layouts', '_includes', '_partials') as $type) {
            if (is_dir($viewPath = $theme['path'].'/'.$type)) {
                $paths[] = $viewPath;
            }
        }

        return $paths;
    }

    /**
     * {@inheritdoc}
     */
    public function getSource($name)
    {
        return $this->flexibleExtensionFilesystemLoader->getSource($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheKey($name)
    {
        return $this->flexibleExtensionFilesystemLoader->getCacheKey($name);
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh($name, $time)
    {
        return $this->flexibleExtensionFilesystemLoader->isFresh($name, $time);
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
            $this->initThemeLoader();
        }
    }
}

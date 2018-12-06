<?php

namespace Limenius\ReactRenderer\Renderer;

use Psr\Cache\CacheItemPoolInterface;

class StaticReactRenderer extends AbstractReactRenderer
{
    private $cache;
    private $cacheKey;
    private $renderer;

    public function setCache(CacheItemPoolInterface $cache, $cacheKey)
    {
        $this->cache = $cache;
        $this->cacheKey = $cacheKey;
    }

    public function setRenderer(AbstractReactRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public function render($componentName, $propsString, $uuid, $registeredStores = array(), $trace)
    {
        if ($this->cache === null) {
            return $this->renderer->render($componentName, $propsString, $uuid, $registeredStores, $trace);
        }

        $cacheItem = $this->cache->getItem($componentName . '.rendered');
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $rendered = $this->renderer->render($componentName, $propsString, $uuid, $registeredStores, $trace);

        $cacheItem->set($rendered);
        $this->cache->save($cacheItem);
        return $rendered;
    }
}

<?php

namespace Limenius\ReactRenderer\Twig;

use Psr\Cache\CacheItemPoolInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Limenius\ReactRenderer\Renderer\AbstractReactRenderer;
use Limenius\ReactRenderer\Context\ContextProviderInterface;

class ReactRenderExtension extends AbstractExtension
{
    protected $renderServerSide = false;
    protected $renderClientSide = false;
    protected $registeredStores = array();
    protected $needsToSetRailsContext = true;

    private $renderer;
    private $staticRenderer;
    private $contextProvider;
    private $trace;
    private $buffer;
    private $cache;

    public function __construct(AbstractReactRenderer $renderer = null, ContextProviderInterface $contextProvider, string $defaultRendering, bool $trace = false)
    {
        $this->renderer = $renderer;
        $this->contextProvider = $contextProvider;
        $this->trace = $trace;
        $this->buffer = array();

        switch ($defaultRendering) {
            case 'server_side':
                $this->renderClientSide = false;
                $this->renderServerSide = true;
                break;
            case 'client_side':
                $this->renderClientSide = true;
                $this->renderServerSide = false;
                break;
            case 'both':
                $this->renderClientSide = true;
                $this->renderServerSide = true;
                break;
        }
    }

    public function setCache(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }

    public function getFunctions(): array
    {
        return array(
            new TwigFunction('react_component', array($this, 'reactRenderComponent'), array('is_safe' => array('html'))),
            new TwigFunction('react_component_array', array($this, 'reactRenderComponentArray'), array('is_safe' => array('html'))),
            new TwigFunction('redux_store', array($this, 'reactReduxStore'), array('is_safe' => array('html'))),
            new TwigFunction('react_flush_buffer', array($this, 'reactFlushBuffer'), array('is_safe' => array('html'))),
        );
    }

    public function reactRenderComponentArray(string $componentName, array $options = array()): array
    {
        $props = isset($options['props']) ? $options['props'] : array();
        $propsArray = is_array($props) ? $props : $this->jsonDecode($props);

        $str = '';
        $data = array(
            'component_name' => $componentName,
            'props' => $propsArray,
            'dom_id' => 'sfreact-'.uniqid('reactRenderer', true),
            'trace' => $this->shouldTrace($options),
        );

        if ($this->shouldRenderClientSide($options)) {
            $tmpData = $this->renderContext();
            $tmpData .= sprintf(
                '<script type="application/json" class="js-react-on-rails-component" data-component-name="%s" data-dom-id="%s">%s</script>',
                $data['component_name'],
                $data['dom_id'],
                $this->jsonEncode($data['props'])
            );
            if ($this->shouldBuffer($options) === true) {
                $this->buffer[] = $tmpData;
            } else {
                $str .= $tmpData;
            }
        }
        $str .= '<div id="'.$data['dom_id'].'">';

        if ($this->shouldRenderServerSide($options)) {
            $rendered = $this->serverSideRender($data, $options);
            if ($rendered['hasErrors']) {
                $str .= $rendered['evaluated'].$rendered['consoleReplay'];
            } else {
                $evaluated = $rendered['evaluated'];
                $str .= $evaluated['componentHtml'].$rendered['consoleReplay'];
            }
        }
        $str .= '</div>';

        $evaluated['componentHtml'] = $str;

        return $evaluated;
    }

    public function reactRenderComponentArrayStatic(string $componentName, array $options = array()): string
    {
        $renderer = $this->renderer;
        $this->renderer = $this->staticRenderer;

        $rendered = $this->reactRenderComponentArray($componentName, $options);
        $this->renderer = $renderer;

        return $rendered;
    }

    public function reactRenderComponent(string $componentName, array $options = array()): string
    {
        $props = isset($options['props']) ? $options['props'] : array();
        $propsArray = is_array($props) ? $props : $this->jsonDecode($props);

        $str = '';
        $data = array(
            'component_name' => $componentName,
            'props' => $propsArray,
            'dom_id' => 'sfreact-'.uniqid('reactRenderer', true),
            'trace' => $this->shouldTrace($options),
        );

        if ($this->shouldRenderClientSide($options)) {
            $tmpData = $this->renderContext();
            $tmpData .= sprintf(
                '<script type="application/json" class="js-react-on-rails-component" data-component-name="%s" data-dom-id="%s">%s</script>',
                $data['component_name'],
                $data['dom_id'],
                $this->jsonEncode($data['props'])
            );
            if ($this->shouldBuffer($options) === true) {
                $this->buffer[] = $tmpData;
            } else {
                $str .= $tmpData;
            }
        }
        $str .= '<div id="'.$data['dom_id'].'">';
        if ($this->shouldRenderServerSide($options)) {
            $rendered = $this->serverSideRender($data, $options);
            $evaluated = $rendered['evaluated'];
            $str .= $rendered['evaluated'].$rendered['consoleReplay'];
        }
        $str .= '</div>';

        return $str;
    }

    public function reactRenderComponentStatic(string $componentName, array $options = array()): string
    {
        $renderer = $this->renderer;
        $this->renderer = $this->staticRenderer;

        $rendered = $this->reactRenderComponent($componentName, $options);
        $this->renderer = $renderer;

        return $rendered;
    }

    public function reactReduxStore(string $storeName, $props): string
    {
        $propsString = is_array($props) ? $this->jsonEncode($props) : $props;
        $this->registeredStores[$storeName] = $propsString;

        $reduxStoreTag = sprintf(
            '<script type="application/json" data-js-react-on-rails-store="%s">%s</script>',
            $storeName,
            $propsString
        );

        return $this->renderContext().$reduxStoreTag;
    }

    public function reactFlushBuffer(): string
    {
        $str = '';

        foreach ($this->buffer as $item) {
            $str .= $item;
        }

        $this->buffer = array();

        return $str;
    }

    public function shouldRenderServerSide(array $options): bool
    {
        if (isset($options['rendering'])) {
            if (in_array($options['rendering'], ['server_side', 'both'], true)) {
                return true;
            } else {
                return false;
            }
        }

        return $this->renderServerSide;
    }

    public function shouldRenderClientSide(array $options): string
    {
        if (isset($options['rendering'])) {
            if (in_array($options['rendering'], ['client_side', 'both'], true)) {
                return true;
            } else {
                return false;
            }
        }

        return $this->renderClientSide;
    }

    public function getName(): string
    {
        return 'react_render_extension';
    }

    protected function shouldTrace(array $options): bool
    {
        return isset($options['trace']) ? $options['trace'] : $this->trace;
    }

    private function renderContext(): string
    {
        if ($this->needsToSetRailsContext) {
            $this->needsToSetRailsContext = false;

            return sprintf(
                '<script type="application/json" id="js-react-on-rails-context">%s</script>',
                $this->jsonEncode($this->contextProvider->getContext(false))
            );
        }

        return '';
    }

    private function jsonEncode($input): string
    {
        $json = json_encode($input);

        if (json_last_error() !== 0) {
            throw new \Limenius\ReactRenderer\Exception\PropsEncodeException(
                sprintf(
                    'JSON could not be encoded, Error Message was %s',
                    json_last_error_msg()
                )
            );
        }

        return $json;
    }

    private function jsonDecode($input): array
    {
        $json = json_decode($input, true);

        if (json_last_error() !== 0) {
            throw new \Limenius\ReactRenderer\Exception\PropsDecodeException(
                sprintf(
                    'JSON could not be decoded, Error Message was %s',
                    json_last_error_msg()
                )
            );
        }

        return $json;
    }

    private function serverSideRender(array $data, array $options): array
    {
        if ($this->shouldCache($options)) {
            return $this->renderCached($data, $options);
        } else {
            return $this->doServerSideRender($data);
        }
    }

    private function doServerSideRender($data): array
    {
        return $this->renderer->render(
            $data['component_name'],
            json_encode($data['props']),
            $data['dom_id'],
            $this->registeredStores,
            $data['trace']
        );
    }

    private function renderCached($data, $options): array
    {
        if ($this->cache === null) {
            return $this->doServerSideRender($data);
        }

        $cacheItem = $this->cache->getItem($data['component_name'].$this->getCacheKey($options, $data));
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $rendered = $this->doServerSideRender($data);

        $cacheItem->set($rendered);
        $this->cache->save($cacheItem);

        return $rendered;
    }

    private function getCacheKey($options, $data): string
    {
        return isset($options['cache_key']) && $options['cache_key'] ? $options['cache_key'] : $data['component_name'].'.rendered';
    }

    private function shouldCache($options): bool
    {
        return isset($options['cached']) && $options['cached'];
    }

    private function shouldBuffer($options): bool
    {
        return isset($options['buffered']) && $options['buffered'];
    }
}

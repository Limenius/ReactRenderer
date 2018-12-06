<?php

namespace Limenius\ReactRenderer\Twig;

use Limenius\ReactRenderer\Renderer\AbstractReactRenderer;
use Limenius\ReactRenderer\Context\ContextProviderInterface;
use Limenius\ReactRenderer\Renderer\StaticReactRenderer;

/**
 * Class ReactRenderExtension
 */
class ReactRenderExtension extends \Twig_Extension
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

    /**
     * Constructor
     *
     * @param AbstractReactRenderer    $renderer
     * @param ContextProviderInterface $contextProvider
     * @param string                   $defaultRendering
     * @param boolean                  $trace
     *
     * @return ReactRenderExtension
     */
    public function __construct(AbstractReactRenderer $renderer = null, StaticReactRenderer $staticRenderer, ContextProviderInterface $contextProvider, $defaultRendering, $trace = false)
    {
        $staticRenderer->setRenderer($renderer);
        $this->staticRenderer = $staticRenderer;
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

    /**
     * @return array
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('react_component', array($this, 'reactRenderComponent'), array('is_safe' => array('html'))),
            new \Twig_SimpleFunction('react_component_array', array($this, 'reactRenderComponentArray'), array('is_safe' => array('html'))),
            new \Twig_SimpleFunction('redux_store', array($this, 'reactReduxStore'), array('is_safe' => array('html'))),
            new \Twig_SimpleFunction('react_component_static', array($this, 'reactRenderComponentStatic'), array('is_safe' => array('html'))),
            new \Twig_SimpleFunction('react_component_array_static', array($this, 'reactRenderComponentArrayStatic'), array('is_safe' => array('html'))),
            new \Twig_SimpleFunction('react_flush_buffer', array($this, 'reactFlushBuffer'), array('is_safe' => array('html'))),

        );
    }

    /**
     * @param string $componentName
     * @param array  $options
     * @param bool   $bufferData
     *
     * @return string
     */
    public function reactRenderComponentArray($componentName, array $options = array(), $bufferData = false)
    {
        $props = isset($options['props']) ? $options['props'] : array();
        $propsArray = is_array($props) ? $props : json_decode($props);

        $str = '';
        $data = array(
            'component_name' => $componentName,
            'props' => $propsArray,
            'dom_id' => 'sfreact-'.uniqid('reactRenderer', true),
            'trace' => $this->shouldTrace($options),
        );


        if ($this->shouldRenderClientSide($options)) {
            $tmpData = $this->renderContext();
            $tmpData .=  sprintf(
                '<script type="application/json" class="js-react-on-rails-component" data-component-name="%s" data-dom-id="%s">%s</script>',
                $data['component_name'],
                $data['dom_id'],
                json_encode($data['props'])
            );
            if($bufferData === true) {
                $this->buffer[] = $tmpData;
            } else {
                $str .= $tmpData;
            }
        }
        $str .= '<div id="'.$data['dom_id'].'">';

        if ($this->shouldRenderServerSide($options)) {
            $rendered = $this->renderer->render(
                $data['component_name'],
                json_encode($data['props']),
                $data['dom_id'],
                $this->registeredStores,
                $data['trace']
            );
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

    /**
     * @param $componentName
     * @param array $options
     *
     * @return string
     */
    public function reactRenderComponentArrayStatic($componentName, array $options = array())
    {
        $renderer = $this->renderer;
        $this->renderer = $this->staticRenderer;

        $rendered = $this->reactRenderComponentArray($componentName, $options);
        $this->renderer = $renderer;

        return $rendered;
    }

    /**
     * @param string $componentName
     * @param array  $options
     * @param bool   $bufferData
     *
     * @return string
     */
    public function reactRenderComponent($componentName, array $options = array(), $bufferData = false)
    {
        $props = isset($options['props']) ? $options['props'] : array();
        $propsArray = is_array($props) ? $props : json_decode($props);

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
                json_encode($data['props'])
            );
            if($bufferData === true) {
                $this->buffer[] = $tmpData;
            } else {
                $str .= $tmpData;
            }
        }
        $str .= '<div id="'.$data['dom_id'].'">';
        if ($this->shouldRenderServerSide($options)) {
            $rendered = $this->renderer->render(
                $data['component_name'],
                json_encode($data['props']),
                $data['dom_id'],
                $this->registeredStores,
                $data['trace']
            );
            $str .= $rendered['evaluated'].$rendered['consoleReplay'];
        }
        $str .= '</div>';
        return $str;
    }

    /**
     * @param string $componentName
     * @param array  $options
     *
     * @return string
     */
    public function reactRenderComponentStatic($componentName, array $options = array())
    {
        $renderer = $this->renderer;
        $this->renderer = $this->staticRenderer;

        $rendered = $this->reactRenderComponent($componentName, $options);
        $this->renderer = $renderer;

        return $rendered;
    }

    /**
     * @param string $storeName
     * @param array  $props
     *
     * @return string
     */
    public function reactReduxStore($storeName, $props)
    {
        $propsString = is_array($props) ? json_encode($props) : $props;
        $this->registeredStores[$storeName] = $propsString;


        $reduxStoreTag = sprintf(
            '<script type="application/json" data-js-react-on-rails-store="%s">%s</script>',
            $storeName,
            $propsString
        );

        return $this->renderContext().$reduxStoreTag;
    }

    /**
     * @return string
     */
    public function reactFlushBuffer()
    {
        $str = '';

        foreach ($this->buffer as $item) {
            $str .= $item;
        }

        $this->buffer = array();

        return $str;
    }

    /**
     * @param array $options
     *
     * @return bool
     */
    public function shouldRenderServerSide($options)
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

    /**
     * @param array $options
     *
     * @return bool
     */
    public function shouldRenderClientSide($options)
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

    /**
     * @return string
     */
    public function getName()
    {
        return 'react_render_extension';
    }

    /**
     * @param array $options
     *
     * @return bool
     */
    protected function shouldTrace($options)
    {
        return (isset($options['trace']) ? $options['trace'] : $this->trace);
    }

    /**
     * renderContext
     *
     * @return string a html script tag with the context
     */
    protected function renderContext()
    {
        if ($this->needsToSetRailsContext) {
            $this->needsToSetRailsContext = false;

            return sprintf(
                '<script type="application/json" id="js-react-on-rails-context">%s</script>',
                json_encode($this->contextProvider->getContext(false))
            );
        }

        return '';
    }
}

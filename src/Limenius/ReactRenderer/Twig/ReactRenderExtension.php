<?php

namespace Limenius\ReactRenderer\Twig;

use Limenius\ReactRenderer\Renderer\AbstractReactRenderer;
use Limenius\ReactRenderer\Context\ContextProviderInterface;

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
    private $contextProvider;
    private $trace;

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
    public function __construct(AbstractReactRenderer $renderer = null, ContextProviderInterface $contextProvider, $defaultRendering, $trace = false)
    {
        $this->renderer = $renderer;
        $this->contextProvider = $contextProvider;
        $this->trace = $trace;

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
            new \Twig_SimpleFunction('redux_store', array($this, 'reactReduxStore'), array('is_safe' => array('html'))),
        );
    }

    /**
     * @param string $componentName
     * @param array  $options
     *
     * @return string
     */
    public function reactRenderComponent($componentName, array $options = array())
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
            $str .= $this->renderContext();
            $str .=  sprintf(
                '<script type="application/json" class="js-react-on-rails-component" data-component-name="%s" data-dom-id="%s">%s</script>',
                $data['component_name'],
                $data['dom_id'],
                json_encode($data['props'])
            );
        }
        $str .= '<div id="'.$data['dom_id'].'">';
        if ($this->shouldRenderServerSide($options)) {
            $serverSideStr = $this->renderer->render(
                $data['component_name'],
                json_encode($data['props']),
                $data['dom_id'],
                $this->registeredStores,
                $data['trace']
            );
            $str .= $serverSideStr;
        }
        $str .= '</div>';

        return $str;
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

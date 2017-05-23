<?php

namespace Limenius\ReactRenderer\Renderer;

use Nacmartin\PhpExecJs\PhpExecJs;
use Psr\Log\LoggerInterface;

/**
 * Class PhpExecJsReactRenderer
 */
class PhpExecJsReactRenderer extends AbstractReactRenderer
{
    /**
     * @var PhpExecJs
     */
    protected $phpExecJs;

    /**
     * @var string
     */
    protected $serverBundlePath;

    /**
     * @var bool
     */
    protected $needToSetContext = true;

    /**
     * @var bool
     */
    protected $failLoud;

    /**
     * PhpExecJsReactRenderer constructor.
     *
     * @param string          $serverBundlePath
     * @param bool            $failLoud
     * @param LoggerInterface $logger
     */
    public function __construct($serverBundlePath, $failLoud = false, LoggerInterface $logger = null)
    {
        $this->serverBundlePath = $serverBundlePath;
        $this->failLoud = $failLoud;
        $this->logger = $logger;
    }

    /**
     * @param PhpExecJs $phpExecJs
     */
    public function setPhpExecJs(PhpExecJs $phpExecJs)
    {
        $this->phpExecJs = $phpExecJs;
    }

    /**
     * @param string $serverBundlePath
     */
    public function setServerBundlePath($serverBundlePath)
    {
        $this->serverBundlePath = $serverBundlePath;
        $this->needToSetContext = true;
    }

    /**
     * @param string $componentName
     * @param string $propsString
     * @param string $uuid
     * @param array  $registeredStores
     * @param bool   $trace
     *
     * @return string
     */
    public function render($componentName, $propsString, $uuid, $registeredStores = array(), $trace)
    {
        $this->ensurePhpExecJsIsBuilt();
        if ($this->needToSetContext) {
            $this->phpExecJs->createContext($this->consolePolyfill()."\n".$this->timerPolyfills($trace)."\n".$this->loadServerBundle());
            $this->needToSetContext = false;
        }
        $result = json_decode($this->phpExecJs->evalJs($this->wrap($componentName, $propsString, $uuid, $registeredStores, $trace)), true);
        if ($result['hasErrors']) {
            $this->logErrors($result['consoleReplayScript']);
            if ($this->failLoud) {
                $this->throwError($result['consoleReplayScript'], $componentName);
            }
        }

        return $result['html'].$result['consoleReplayScript'];
    }

    protected function loadServerBundle()
    {
        if (!$serverBundle = @file_get_contents($this->serverBundlePath)) {
            throw new \RuntimeException('Server bundle not found in path: '.$this->serverBundlePath);
        }

        return $serverBundle;
    }

    protected function ensurePhpExecJsIsBuilt()
    {
        if (!$this->phpExecJs) {
            $this->phpExecJs = new PhpExecJs();
        }
    }

    /**
     * @param $trace
     * @return string
     */
    protected function timerPolyfills($trace)
    {
        $timerPolyfills = <<<JS
function getStackTrace () {
  var stack;
  try {
    throw new Error('');
  }
  catch (error) {
    stack = error.stack || '';
  }
  stack = stack.split('\\n').map(function (line) { return line.trim(); });
  return stack.splice(stack[0] == 'Error' ? 2 : 1);
}

function setInterval() {
  {$this->undefinedForExecJsLogging('setInterval', $trace)}
}

function setTimeout() {
  {$this->undefinedForExecJsLogging('setTimeout', $trace)}
}

function clearTimeout() {
  {$this->undefinedForExecJsLogging('clearTimeout', $trace)}
}
JS;
        return $timerPolyfills;
    }

    /**
     * @param $functionName
     * @param $trace
     * @return string
     */
    protected function undefinedForExecJsLogging($functionName, $trace)
    {
        $undefinedForExecJsLogging = !$trace ? '' : <<<JS
console.error(
  '"$functionName" is not defined for execJS. See https://github.com/sstephenson/execjs#faq. ' +
  'Note babel-polyfill may call this.'
);
console.error(getStackTrace().join('\\n'));
JS;
        return $undefinedForExecJsLogging;
    }
}

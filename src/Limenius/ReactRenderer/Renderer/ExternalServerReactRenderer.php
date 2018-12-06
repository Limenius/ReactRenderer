<?php

namespace Limenius\ReactRenderer\Renderer;

use Limenius\ReactRenderer\Context\ContextProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Class ExternalServerReactRenderer
 */
class ExternalServerReactRenderer extends AbstractReactRenderer
{
    /**
     * @var string
     */
    protected $serverSocketPath;

    /**
     * @var bool
     */
    protected $failLoud;

    /**
     * ExternalServerReactRenderer constructor.
     *
     * @param string                   $serverSocketPath
     * @param bool                     $failLoud
     * @param ContextProviderInterface $contextProvider
     * @param LoggerInterface          $logger
     */
    public function __construct($serverSocketPath, $failLoud = false, ContextProviderInterface $contextProvider, LoggerInterface $logger = null)
    {
        $this->serverSocketPath = $serverSocketPath;
        $this->failLoud = $failLoud;
        $this->logger = $logger;
        $this->contextProvider = $contextProvider;
    }

    /**
     * @param string $serverSocketPath
     */
    public function setServerSocketPath($serverSocketPath)
    {
        $this->serverSocketPath = $serverSocketPath;
    }

    /**
     * @param string $componentName
     * @param string $propsString
     * @param string $uuid
     * @param array  $registeredStores
     * @param bool   $trace
     *
     * @return array
     */
    public function render($componentName, $propsString, $uuid, $registeredStores = array(), $trace)
    {
        if (strpos($this->serverSocketPath, '://') === false) {
            $this->serverSocketPath = 'unix://'.$this->serverSocketPath;
        }

        if (!$sock = stream_socket_client($this->serverSocketPath, $errno, $errstr)) {
            throw new \RuntimeException($errstr);
        }
        stream_socket_sendto($sock, $this->wrap($componentName, $propsString, $uuid, $registeredStores, $trace)."\0");

        $contents = '';

        while (!feof($sock)) {
            $contents .= fread($sock, 8192);
        }
        fclose($sock);

        $result = json_decode($contents, true);
        if ($result['hasErrors']) {
            $this->logErrors($result['consoleReplayScript']);
            if ($this->failLoud) {
                $this->throwError($result['consoleReplayScript'], $componentName);
            }
        }

        return [
            'evaluated' => $result['html'],
            'consoleReplay' => $result['consoleReplayScript'],
            'hasErrors' => $result['hasErrors']
        ];
    }
}

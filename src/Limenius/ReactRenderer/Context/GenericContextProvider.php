<?php

namespace Limenius\ReactRenderer\Context;

/**
 * Class ContextProvider
 *
 * Extracts context information from a URI path.
 * https://en.wikipedia.org/wiki/Uniform_Resource_Identifier
 */
class GenericContextProvider implements ContextProviderInterface
{
    private $regex = '/(?<scheme>https?):\/\/(?<host>.*(\.[^\/(?:\d*)]*))(?::(?<port>\d*))(?<uri>(?<base>\/?[^\.]*\.[^\/\?]*)?(?<path>[^?\.*]*)\??(?<search>.*))/';
    private $uriParts;

    public function __construct(string $uri)
    {
        preg_match($this->regex, $uri, $this->uriParts, PREG_OFFSET_CAPTURE);
    }

    private function getRequestUri()
    {
        return $this->uriParts['uri'][0] ?: '/';
    }

    private function getScheme()
    {
        return $this->uriParts['scheme'][0];
    }

    private function getHost()
    {
        return $this->uriParts['host'][0];
    }

    private function getPort()
    {
        return $this->uriParts['port'][0];
    }

    private function getBase()
    {
        return $this->uriParts['base'][0];
    }

    private function getPathName()
    {
        return $this->uriParts['path'][0];
    }

    private function getSearch()
    {
        return $this->uriParts['search'][0];
    }

    /**
     * getContext
     *
     * @param boolean $serverSide whether is this a server side context
     * @return array the context information
     */
    public function getContext($serverSide)
    {
        return [
            'serverSide' => $serverSide,
            'href' => $this->uriParts[0][0],
            'location' => $this->getRequestUri(),
            'scheme' => $this->getScheme(),
            'host' => $this->getHost(),
            'port' => $this->getPort(),
            'base' => $this->getBase(),
            'pathname' => $this->getPathName(),
            'search' => $this->getSearch(),
        ];
    }
}

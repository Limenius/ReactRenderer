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
    private $uri;
    private $uriParts;

    public function __construct(string $uri)
    {
        $this->uri = $uri;
        $this->uriParts = parse_url($uri);
    }

    private function getRequestUri()
    {
        $uri = !empty($this->uriParts['path']) ? $this->uriParts['path'] : '/';

        if (!empty($this->uriParts['query'])) {
            $uri .= "?{$this->uriParts['query']}";
        }

        return $uri;
    }

    private function getScheme()
    {
        return $this->uriParts['scheme'];
    }

    private function getHost()
    {
        return $this->uriParts['host'];
    }

    private function getPort()
    {
        return !empty($this->uriParts['port']) ? $this->uriParts['port'] : '';
    }

    private function getBase()
    {
        if (empty($this->uriParts['path'])) {
            return '';
        }

        preg_match('/(?<base>\/[^\.]*\.\w*)/', $this->uriParts['path'], $matches);

        return !empty($matches['base']) ? $matches['base'] : '';
    }

    private function getPathName()
    {
        if (empty($this->uriParts['path'])) {
            return '';
        }

        preg_match('/(?:\/[^\.]*\.\w*)?(?<base>\/.*)/', $this->uriParts['path'], $matches);

        return !empty($matches['base']) ? $matches['base'] : '';
    }

    private function getSearch()
    {
        return !empty($this->uriParts['query']) ? $this->uriParts['query'] : '';
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
            'href' => $this->uri,
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

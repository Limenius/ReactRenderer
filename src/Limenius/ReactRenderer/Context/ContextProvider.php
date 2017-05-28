<?php

namespace Limenius\ReactRenderer\Context;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class ContextProvider
 *
 * Extracts context information from a Symfony Request
 */
class ContextProvider
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function getContext($serverSide)
    {
        $request = $this->requestStack->getCurrentRequest();
        return [
            'serverSide' => $serverSide,
            'href' => $request->getSchemeAndHttpHost().$request->getRequestUri(),
            'location' => $request->getRequestUri(),
            'scheme' => $request->getScheme(),
            'host' => $request->getHost(),
            'port' => $request->getPort(),
            'base' => $request->getBaseUrl(),
            'pathname' => $request->getPathInfo(),
            'search' => $request->getQueryString(),
            ];
    }

}

<?php

namespace Limenius\ReactRenderer\Context;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Interface ContextProviderInterface
 *
 * Provides context
 */
interface ContextProviderInterface
{
    /**
     * getContext
     *
     * @param boolean $serverSide whether is this a server side context
     */
    public function getContext($serverSide);
}

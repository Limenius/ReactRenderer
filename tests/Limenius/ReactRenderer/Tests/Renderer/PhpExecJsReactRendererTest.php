<?php

namespace Limenius\ReactRenderer\Tests\Renderer;

use Limenius\ReactRenderer\Renderer\PhpExecJsReactRenderer;
use Limenius\ReactRenderer\Context\ContextProviderInterface;
use Psr\Log\LoggerInterface;
use Nacmartin\PhpExecJs\PhpExecJs;
use PHPUnit\Framework\TestCase;

class PhpExecJsReactRendererTest extends TestCase
{
    /**
     * @var PhpExecJsReactRenderer
     */
    private $renderer;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var PhpExecJs
     */
    private $phpExecJs;

    public function setUp(): void
    {
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $this->phpExecJs = $this->getMockBuilder(PhpExecJs::class)
            ->getMock();
        $this->contextProvider = $this->getMockBuilder(ContextProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->phpExecJs->method('evalJs')
            ->willReturn('{ "html" : "go for it", "hasErrors" : false, "consoleReplayScript": " - my replay"}');
        $this->renderer = new PhpExecJsReactRenderer(__DIR__.'/Fixtures/server-bundle.js', false, $this->contextProvider, $this->logger);
        $this->renderer->setPhpExecJs($this->phpExecJs);
    }

    public function testServerBundleNotFound()
    {
        $this->expectException(\RuntimeException::class);
        $this->renderer = new PhpExecJsReactRenderer(__DIR__.'/Fixtures/i-dont-exist.js', $this->logger, $this->contextProvider);
        $this->renderer->render('MyApp', 'props', 1, null, false);
    }

    public function testPlus()
    {
        $this->assertEquals([
            'evaluated' => 'go for it',
            'consoleReplay' => ' - my replay',
            'hasErrors' => false,
        ],
        $this->renderer->render('MyApp', 'props', 1, null, false));
    }

    public function testWithStoreData()
    {
        $this->assertEquals([
            'evaluated' => 'go for it',
            'consoleReplay' => ' - my replay',
            'hasErrors' => false,
        ],
        $this->renderer->render('MyApp', 'props', 1, array('Store' => '{foo:"bar"'), false));
    }

    public function testReactOnRails()
    {
        /*
         * import React from 'react';
         * import ReactOnRails from 'react-on-rails';
         * ReactOnRails.register({ MyApp: props => <h1>{props.msg}</h1> });
         */
        $this->contextProvider->method('getContext')
            ->willReturn(['someContext' => 'provided']);
        $this->renderer = new PhpExecJsReactRenderer(__DIR__.'/Fixtures/server-bundle-react.js', false, $this->contextProvider);

        $expected = '<h1 data-reactroot="" data-reactid="1" data-react-checksum="-605941478">It Works!</h1>';
        $replay = "\n".'<script id="consoleReplayLog">'."\n";
        $replay .= 'console.log.apply(console, ["[SERVER] RENDERED MyApp to dom node with id: 1 with props, railsContext:","{\"msg\":\"It Works!\"}","{\"someContext\":\"provided\"}"]);'."\n";
        $replay .= '</script>';

        $this->assertEquals([
            'evaluated' => $expected,
            'consoleReplay' => $replay,
            'hasErrors' => false,
        ],
        $this->renderer->render('MyApp', '{msg:"It Works!"}', 1, null, true));
    }

    public function testFailLoud()
    {
        $this->expectException(\Limenius\ReactRenderer\Exception\EvalJsException::class);
        $phpExecJs = $this->getMockBuilder(PhpExecJs::class)
            ->getMock();
        $phpExecJs->method('evalJs')
            ->willReturn('{ "html" : "go for it", "hasErrors" : true, "consoleReplayScript": " - my replay"}');
        $this->renderer = new PhpExecJsReactRenderer(__DIR__.'/Fixtures/server-bundle.js', true, $this->contextProvider, $this->logger);
        $this->renderer->setPhpExecJs($phpExecJs);
        $this->renderer->render('MyApp', 'props', 1, null, true);
    }
}

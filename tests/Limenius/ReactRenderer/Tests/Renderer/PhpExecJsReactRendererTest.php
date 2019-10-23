<?php

namespace Limenius\ReactRenderer\Tests\Renderer;

use Limenius\ReactRenderer\Context\ContextProviderInterface;
use Limenius\ReactRenderer\Exception\EvalJsException;
use Limenius\ReactRenderer\Renderer\PhpExecJsReactRenderer;
use Nacmartin\PhpExecJs\PhpExecJs;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class PhpExecJsReactRendererTest
 */
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

    /**
     * {@inheritdoc}
     */
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

    /**
     * Test Plus
     */
    public function testPlus()
    {
        $this->assertEquals([
            'evaluated' => 'go for it',
            'consoleReplay' => ' - my replay',
            'hasErrors' => false,
        ],
        $this->renderer->render('MyApp', 'props', 1, null, false));
    }

    /**
     * Test with store data
     */
    public function testWithStoreData()
    {
        $this->assertEquals([
            'evaluated' => 'go for it',
            'consoleReplay' => ' - my replay',
            'hasErrors' => false,
        ],
        $this->renderer->render('MyApp', 'props', 1, array('Store' => '{foo:"bar"'), false));
    }

    /**
     * Test with React on Rails bundle
     */
    public function testReactOnRails()
    {
        /**
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
        $phpExecJs = $this->getMockBuilder(PhpExecJs::class)
            ->getMock();
        $phpExecJs->method('evalJs')
            ->willReturn('{ "html" : "go for it", "hasErrors" : true, "consoleReplayScript": " - my replay"}');
        $this->renderer = new PhpExecJsReactRenderer(__DIR__.'/Fixtures/server-bundle.js', true, $this->contextProvider, $this->logger);
        $this->renderer->setPhpExecJs($phpExecJs);
        $this->expectException(EvalJsException::class);
        $this->renderer->render('MyApp', 'props', 1, null, true);
    }

    /**
     * @testdox failLoud true bubbles thrown exceptions
     */
    public function testFailLoudBubblesThrownException()
    {
        $err = new Exception('test exception');
        $this->phpExecJs->method('createContext')->willThrowException($err);
        $this->renderer = new PhpExecJsReactRenderer(__DIR__.'/Fixtures/server-bundle.js', true, $this->contextProvider, $this->logger);
        $this->renderer->setPhpExecJs($this->phpExecJs);

        $this->expectExceptionObject($err);
        $this->renderer->render('MyApp', 'props', 1, null, true);
    }

    /**
     * @testdox failLoud false returns empty error result on exception
     */
    public function testFailQuietReturnsEmptyErrorResultOnException()
    {
        $this->phpExecJs->method('createContext')->willThrowException(new \Exception('test exception'));

        $this->assertEquals(
            [
                'evaluated'     => '',
                'consoleReplay' => '',
                'hasErrors'     => true,
            ],
            $this->renderer->render('MyApp', 'props', 1, null, true)
        );
    }

    /**
     * @testdox failLoud false logs thrown exceptions
     */
    public function testFailQuietLogsThrownExceptions()
    {
        $err = new Exception('test exception');
        $this->phpExecJs->method('createContext')->willThrowException($err);

        $this->logger
            ->expects($this->exactly(1))
            ->method('log')
            ->with(LogLevel::ERROR, 'test exception', ['exception' => $err]);

        $this->renderer->render('MyApp', 'props', 1, null, true);
    }
}

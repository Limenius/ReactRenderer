<?php

namespace Limenius\ReactRenderer\Tests\Renderer;

use Limenius\ReactRenderer\Renderer\PhpExecJsReactRenderer;
use Psr\Log\LoggerInterface;
use Nacmartin\PhpExecJs\PhpExecJs;
use PHPUnit\Framework\TestCase;

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
    public function setUp()
    {
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $this->phpExecJs = $this->getMockBuilder(PhpExecJs::class)
            ->getMock();
        $this->phpExecJs->method('evalJs')
             ->willReturn('{ "html" : "go for it", "hasErrors" : false, "consoleReplayScript": " - my replay"}');
        $this->renderer = new PhpExecJsReactRenderer(__DIR__.'/Fixtures/server-bundle.js', false, $this->logger);
        $this->renderer->setPhpExecJs($this->phpExecJs);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testServerBundleNotFound()
    {
        $this->renderer = new PhpExecJsReactRenderer(__DIR__.'/Fixtures/i-dont-exist.js', $this->logger);
        $this->renderer->render('MyApp', 'props', 1, null, false);
    }

    /**
     * Test Plus
     */
    public function testPlus()
    {
        $this->assertEquals('go for it - my replay', $this->renderer->render('MyApp', 'props', 1, null, false));
    }

    /**
     * Test with store data
     */
    public function testWithStoreData()
    {
        $this->assertEquals('go for it - my replay', $this->renderer->render('MyApp', 'props', 1, array('Store' => '{foo:"bar"'), false));
    }

    /**
     * @expectedException \Limenius\ReactRenderer\Exception\EvalJsException
     */
    public function testFailLoud()
    {
        $phpExecJs = $this->getMockBuilder(PhpExecJs::class)
            ->getMock();
        $phpExecJs->method('evalJs')
             ->willReturn('{ "html" : "go for it", "hasErrors" : true, "consoleReplayScript": " - my replay"}');
        $this->renderer = new PhpExecJsReactRenderer(__DIR__.'/Fixtures/server-bundle.js', true, $this->logger);
        $this->renderer->setPhpExecJs($phpExecJs);
        $this->renderer->render('MyApp', 'props', 1, null, true);
    }
}

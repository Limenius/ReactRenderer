<?php

namespace Limenius\ReactRenderer\Tests\Context;

use Limenius\ReactRenderer\Context\GenericContextProvider;
use PHPUnit\Framework\TestCase;

class GenericContextProviderTest extends TestCase
{
    public function testGetHref()
    {
        $provider = new GenericContextProvider('https://www.example.com:8089/index.php/part/sub?foo=bar');
        $context = $provider->getContext(false);
        $this->assertEquals('https://www.example.com:8089/index.php/part/sub?foo=bar', $context['href']);
    }

    public function testGetRequestUri1()
    {
        $provider = new GenericContextProvider('https://www.example.com:443');
        $context = $provider->getContext(false);
        $this->assertEquals('https://www.example.com:443', $context['href']);
        $this->assertEquals('/', $context['location']);
    }

    public function testGetRequestUri2()
    {
        $provider = new GenericContextProvider('https://example.com:443/');
        $context = $provider->getContext(false);
        $this->assertEquals('https://example.com:443/', $context['href']);
        $this->assertEquals('/', $context['location']);
    }

    public function testGetRequestUri3()
    {
        $provider = new GenericContextProvider('https://www.example.com:443/part');
        $context = $provider->getContext(false);
        $this->assertEquals('https://www.example.com:443/part', $context['href']);
        $this->assertEquals('/part', $context['location']);
    }

    public function testGetRequestUri4()
    {
        $provider = new GenericContextProvider('https://example.com:443/part?foo=bar');
        $context = $provider->getContext(false);
        $this->assertEquals('https://example.com:443/part?foo=bar', $context['href']);
        $this->assertEquals('/part?foo=bar', $context['location']);
    }

    public function testGetRequestUri5()
    {
        $provider = new GenericContextProvider('https://www.example.com:443/index.php/part?foo=bar');
        $context = $provider->getContext(false);
        $this->assertEquals('https://www.example.com:443/index.php/part?foo=bar', $context['href']);
        $this->assertEquals('/index.php/part?foo=bar', $context['location']);
    }

    public function testGetScheme1()
    {
        $provider = new GenericContextProvider('http://www.example.com:443/part?foo=bar');
        $context = $provider->getContext(false);
        $this->assertEquals('http://www.example.com:443/part?foo=bar', $context['href']);
        $this->assertEquals('http', $context['scheme']);
    }

    public function testGetScheme2()
    {
        $provider = new GenericContextProvider('https://example.com:443/part?foo=bar');
        $context = $provider->getContext(false);
        $this->assertEquals('https://example.com:443/part?foo=bar', $context['href']);
        $this->assertEquals('https', $context['scheme']);
    }

    public function testGetHost1()
    {
        $provider = new GenericContextProvider('https://example.com:443/part?foo=bar');
        $context = $provider->getContext(false);
        $this->assertEquals('https://example.com:443/part?foo=bar', $context['href']);
        $this->assertEquals('example.com', $context['host']);
    }

    public function testGetHost2()
    {
        $provider = new GenericContextProvider('https://www.example.com:443/part?foo=bar');
        $context = $provider->getContext(false);
        $this->assertEquals('https://www.example.com:443/part?foo=bar', $context['href']);
        $this->assertEquals('www.example.com', $context['host']);
    }

    public function testGetHost3()
    {
        $provider = new GenericContextProvider('https://sub.example.com:443/part?foo=bar');
        $context = $provider->getContext(false);
        $this->assertEquals('https://sub.example.com:443/part?foo=bar', $context['href']);
        $this->assertEquals('sub.example.com', $context['host']);
    }

    public function testGetHost4()
    {
        $provider = new GenericContextProvider('https://www.sub.example.com:443/part?foo=bar');
        $context = $provider->getContext(false);
        $this->assertEquals('https://www.sub.example.com:443/part?foo=bar', $context['href']);
        $this->assertEquals('www.sub.example.com', $context['host']);
    }

    public function testGetPort1()
    {
        $provider = new GenericContextProvider('https://www.example.com:8089/part?foo=bar');
        $context = $provider->getContext(false);
        $this->assertEquals('https://www.example.com:8089/part?foo=bar', $context['href']);
        $this->assertEquals('8089', $context['port']);
    }


    public function testGetPort2()
    {
        $provider = new GenericContextProvider('https://sub.example.com/part?foo=bar');
        $context = $provider->getContext(false);
        $this->assertEquals('https://sub.example.com/part?foo=bar', $context['href']);
        $this->assertEquals('', $context['port']);
    }

    public function testGetBaseUrl1()
    {
        $provider = new GenericContextProvider('https://www.example.com:8089/part?foo=bar');
        $context = $provider->getContext(false);
        $this->assertEquals('https://www.example.com:8089/part?foo=bar', $context['href']);
        $this->assertEquals('', $context['base']);
    }

    public function testGetBaseUrl2()
    {
        $provider = new GenericContextProvider('https://www.example.com:8089/index.php?foo=bar');
        $context = $provider->getContext(false);
        $this->assertEquals('https://www.example.com:8089/index.php?foo=bar', $context['href']);
        $this->assertEquals('/index.php', $context['base']);
    }

    public function testGetPathName1()
    {
        $provider = new GenericContextProvider('https://www.example.com:8089/?foo=bar');
        $context = $provider->getContext(false);
        $this->assertEquals('https://www.example.com:8089/?foo=bar', $context['href']);
        $this->assertEquals('/', $context['pathname']);
    }

    public function testGetPathName2()
    {
        $provider = new GenericContextProvider('https://www.example.com:8089/index.php/?foo=bar');
        $context = $provider->getContext(false);
        $this->assertEquals('https://www.example.com:8089/index.php/?foo=bar', $context['href']);
        $this->assertEquals('/', $context['pathname']);
    }

    public function testGetPathName3()
    {
        $provider = new GenericContextProvider('https://www.example.com:8089/index.php/part?foo=bar');
        $context = $provider->getContext(false);
        $this->assertEquals('https://www.example.com:8089/index.php/part?foo=bar', $context['href']);
        $this->assertEquals('/part', $context['pathname']);
    }

    public function testGetPathName4()
    {
        $provider = new GenericContextProvider('https://www.example.com:8089/index.php/part/sub?foo=bar');
        $context = $provider->getContext(false);
        $this->assertEquals('https://www.example.com:8089/index.php/part/sub?foo=bar', $context['href']);
        $this->assertEquals('/part/sub', $context['pathname']);
    }

    public function testGetSearch1()
    {
        $provider = new GenericContextProvider('https://www.example.com:8089/index.php/part/sub');
        $context = $provider->getContext(false);
        $this->assertEquals('https://www.example.com:8089/index.php/part/sub', $context['href']);
        $this->assertEquals('', $context['search']);
    }

    public function testGetSearch2()
    {
        $provider = new GenericContextProvider('https://www.example.com:8089/index.php/part/sub?');
        $context = $provider->getContext(false);
        $this->assertEquals('https://www.example.com:8089/index.php/part/sub?', $context['href']);
        $this->assertEquals('', $context['search']);
    }

    public function testGetSearch3()
    {
        $provider = new GenericContextProvider('https://www.example.com:8089/index.php/part/sub?foo=bar');
        $context = $provider->getContext(false);
        $this->assertEquals('https://www.example.com:8089/index.php/part/sub?foo=bar', $context['href']);
        $this->assertEquals('foo=bar', $context['search']);
    }

    public function testGetSearch4()
    {
        $provider = new GenericContextProvider('https://www.example.com:8089/index.php/part/sub?foo=bar&bar=baz');
        $context = $provider->getContext(false);
        $this->assertEquals('https://www.example.com:8089/index.php/part/sub?foo=bar&bar=baz', $context['href']);
        $this->assertEquals('foo=bar&bar=baz', $context['search']);
    }
}

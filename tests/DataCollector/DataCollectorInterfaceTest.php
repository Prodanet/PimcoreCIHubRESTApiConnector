<?php

namespace DataCollector;

use CIHub\Bundle\SimpleRESTAdapterBundle\DataCollector\DataCollectorInterface;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class DataCollectorInterfaceTest extends TestCase
{
    public function testCollectMethodExists()
    {
        $this->assertTrue(method_exists(DataCollectorInterface::class, 'collect'));
    }

    public function testCollectMethodSignature()
    {
        $collectMethod = new ReflectionMethod(DataCollectorInterface::class, 'collect');

        $this->assertTrue($collectMethod->isPublic());
        $this->assertTrue($collectMethod->hasReturnType());

        $returnType = $collectMethod->getReturnType();
        $this->assertEquals('array', $returnType->getName());

        $parameters = $collectMethod->getParameters();
        $this->assertCount(2, $parameters);

        $valueParameter = $parameters[0];
        $this->assertEquals('value', $valueParameter->getName());

        $readerParameter = $parameters[1];
        $this->assertEquals('reader', $readerParameter->getName());
        $this->assertTrue($readerParameter->hasType());
        $this->assertEquals(ConfigReader::class, $readerParameter->getType()->getName());
    }

    public function testSupportsMethodExists()
    {
        $this->assertTrue(method_exists(DataCollectorInterface::class, 'supports'));
    }

    public function testSupportsMethodSignature()
    {
        $supportsMethod = new ReflectionMethod(DataCollectorInterface::class, 'supports');

        $this->assertTrue($supportsMethod->isPublic());
        $this->assertTrue($supportsMethod->hasReturnType());

        $returnType = $supportsMethod->getReturnType();
        $this->assertFalse($returnType->allowsNull());
        $this->assertEquals('bool', $returnType->getName());

        $parameters = $supportsMethod->getParameters();
        $this->assertCount(1, $parameters);

        $valueParameter = $parameters[0];
        $this->assertEquals('value', $valueParameter->getName());
    }
}
<?php

namespace DataCollector;

use CIHub\Bundle\SimpleRESTAdapterBundle\DataCollector\CompositeDataCollector;
use CIHub\Bundle\SimpleRESTAdapterBundle\DataCollector\DataCollectorInterface;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use PHPUnit\Framework\TestCase;
use Pimcore\Model\DataObject\Concrete;
use stdClass;
use Webmozart\Assert\InvalidArgumentException;

class CompositeDataCollectorTest extends TestCase
{
    public function testCollectWithSupportedCollector()
    {
        // Create a mock for the DataCollectorInterface
        $collector = $this->createMock(DataCollectorInterface::class);

        // Mock the supports method to return true
        $collector->method('supports')->willReturn(true);

        // Mock the collect method to return a sample result
        $collector->method('collect')->willReturn(['sample' => 'result']);

        // Create an instance of CompositeDataCollector with the mock collector
        $compositeDataCollector = new CompositeDataCollector([$collector]);

        // Create a Concrete object for testing
        $concrete = $this->createMock(Concrete::class);

        // Mock the getValueForFieldName method to return a sample value
        $concrete->method('getValueForFieldName')->willReturn('sample value');
        // Create a ConfigReader object
        $reader = new ConfigReader([]);

        // Call the collect method and expect the sample result
        $result = $compositeDataCollector->collect($concrete, 'sampleField', $reader);

        // Assert that the result matches the expected result
        $this->assertEquals(['sample' => 'result'], $result);
    }

    public function testCollectWithUnsupportedCollector()
    {
        // Create a mock for the DataCollectorInterface
        $collector = $this->createMock(DataCollectorInterface::class);

        // Mock the supports method to return false
        $collector->method('supports')->willReturn(false);

        // Create an instance of CompositeDataCollector with the mock collector
        $compositeDataCollector = new CompositeDataCollector([$collector]);

        // Create a Concrete object for testing
        $concrete = $this->createMock(Concrete::class);

        // Mock the getValueForFieldName method to return a sample value
        $concrete->method('getValueForFieldName')->willReturn('sample value');

        // Create a ConfigReader object
        $reader = new ConfigReader([]);

        // Call the collect method and expect null since no collector supports the value
        $result = $compositeDataCollector->collect($concrete, 'sampleField', $reader);

        // Assert that the result is null
        $this->assertNull($result);
    }

    public function testCollectWithInvalidCollector()
    {
        // Create a mock for an invalid collector that does not implement DataCollectorInterface
        $invalidCollector = $this->createMock(stdClass::class);

        // Create an instance of CompositeDataCollector with the invalid collector
        $compositeDataCollector = new CompositeDataCollector([$invalidCollector]);

        // Create a Concrete object for testing
        $concrete = $this->createMock(Concrete::class);

        // Mock the getValueForFieldName method to return a sample value
        $concrete->method('getValueForFieldName')->willReturn('sample value');

        // Create a ConfigReader object
        $reader = new ConfigReader([]);

        // Expect an InvalidArgumentException when trying to use an invalid collector
        $this->expectException(InvalidArgumentException::class);

        // Call the collect method with an invalid collector
        $compositeDataCollector->collect($concrete, 'sampleField', $reader);
    }
}
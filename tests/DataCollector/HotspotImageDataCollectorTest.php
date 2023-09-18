<?php

namespace DataCollector;

use CIHub\Bundle\SimpleRESTAdapterBundle\DataCollector\HotspotImageDataCollector;
use CIHub\Bundle\SimpleRESTAdapterBundle\DataCollector\ImageDataCollector;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use PHPUnit\Framework\TestCase;
use Pimcore\Model\Asset\Image;
use Pimcore\Model\DataObject\Data\Hotspotimage;
use stdClass;

class HotspotImageDataCollectorTest extends TestCase
{
    /** @var HotspotImageDataCollector */
    private HotspotImageDataCollector $hotspotImageDataCollector;

    /** @var Image */
    private Image $imageMock;

    /** @var ConfigReader */
    private ConfigReader $configReaderMock;

    public function testCollectWithValidImage()
    {
        // Create a mock for Hotspotimage
        $hotspotImage = $this->getMockBuilder(Hotspotimage::class)
            ->getMock();

        // Create a mock for Image
        $image = $this->getMockBuilder(Image::class)
            ->getMock();

        // Mock the getImage method of Hotspotimage to return the Image mock
        $hotspotImage->expects($this->once())
            ->method('getImage')
            ->willReturn($image);

        // Mock the collect method of ImageDataCollector to return a sample result
        $this->imageDataCollectorMock->expects($this->once())
            ->method('collect')
            ->with($image, $this->isInstanceOf(ConfigReader::class))
            ->willReturn(['sample' => 'result']);

        // Call the collect method of HotspotImageDataCollector and expect the sample result
        $result = $this->hotspotImageDataCollector->collect($hotspotImage, $this->configReaderMock);

        // Assert that the result matches the expected result
        $this->assertEquals(['sample' => 'result'], $result);
    }

    public function testCollectWithInvalidImage()
    {
        // Create a mock for Hotspotimage
        $hotspotImage = $this->getMockBuilder(Hotspotimage::class)
            ->getMock();

        // Mock the getImage method of Hotspotimage to return null
        $hotspotImage->expects($this->once())
            ->method('getImage')
            ->willReturn(null);

        // Call the collect method of HotspotImageDataCollector and expect an empty array
        $result = $this->hotspotImageDataCollector->collect($hotspotImage, $this->configReaderMock);

        // Assert that the result is an empty array
        $this->assertEquals([], $result);
    }

    public function testSupports()
    {
        // Create a mock for Hotspotimage
        $hotspotImage = $this->getMockBuilder(Hotspotimage::class)
            ->getMock();

        // Call the supports method of HotspotImageDataCollector with the mock Hotspotimage
        $supports = $this->hotspotImageDataCollector->supports($hotspotImage);

        // Assert that the supports method correctly identifies Hotspotimage
        $this->assertTrue($supports);
    }

    public function testSupportsWithNonHotspotImage()
    {
        // Create a mock for a non-Hotspotimage object
        $nonHotspotImage = $this->getMockBuilder(stdClass::class)
            ->getMock();

        // Call the supports method of HotspotImageDataCollector with the mock non-Hotspotimage object
        $supports = $this->hotspotImageDataCollector->supports($nonHotspotImage);

        // Assert that the supports method correctly identifies non-Hotspotimage objects
        $this->assertFalse($supports);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock for the ImageDataCollector
        $this->imageDataCollectorMock = $this->getMockBuilder(ImageDataCollector::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Create an instance of HotspotImageDataCollector with the mock ImageDataCollector
        $this->hotspotImageDataCollector = new HotspotImageDataCollector($this->imageDataCollectorMock);

        $this->configReaderMock = $this->getMockBuilder(ConfigReader::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
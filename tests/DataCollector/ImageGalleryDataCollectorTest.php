<?php

namespace DataCollector;

use CIHub\Bundle\SimpleRESTAdapterBundle\DataCollector\HotspotImageDataCollector;
use CIHub\Bundle\SimpleRESTAdapterBundle\DataCollector\ImageGalleryDataCollector;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use PHPUnit\Framework\TestCase;
use Pimcore\Model\DataObject\Data\Hotspotimage;
use Pimcore\Model\DataObject\Data\ImageGallery;
use stdClass;

class ImageGalleryDataCollectorTest extends TestCase
{
    /** @var ImageGalleryDataCollector */
    private ImageGalleryDataCollector $imageGalleryDataCollector;

    /** @var HotspotImageDataCollector */
    private HotspotImageDataCollector $hotspotImageDataCollectorMock;

    public function testCollectWithValidItems()
    {
        // Create a mock for ImageGallery
        $imageGallery = $this->getMockBuilder(ImageGallery::class)
            ->getMock();

        // Create mock items
        $item1 = $this->getMockBuilder(Hotspotimage::class)
            ->getMock();
        $item2 = $this->getMockBuilder(Hotspotimage::class)
            ->getMock();

        // Mock the getItems method of ImageGallery to return an array of items
        $imageGallery->expects($this->once())
            ->method('getItems')
            ->willReturn([$item1, $item2]);

        // Mock the collect method of HotspotImageDataCollector to return sample results
        $this->hotspotImageDataCollectorMock->expects($this->exactly(2))
            ->method('collect')
            ->willReturnCallback(fn($personDTO, $personSecondDTO) => match ([$personDTO, $personSecondDTO]) {
                [$item1, $this->isInstanceOf(ConfigReader::class)] => $personDTO,
                [$item2, $this->isInstanceOf(ConfigReader::class)] => $personSecondDTO
            }
            )
            ->willReturn(['item1' => 'result1'], ['item2' => 'result2']);

        // Call the collect method of ImageGalleryDataCollector and expect an array of sample results
        $result = $this->imageGalleryDataCollector->collect($imageGallery, new ConfigReader([]));

        // Assert that the result matches the expected results
        $this->assertEquals([['item1' => 'result1'], ['item2' => 'result2']], $result);
    }

    public function testCollectWithNoItems()
    {
        // Create a mock for ImageGallery
        $imageGallery = $this->getMockBuilder(ImageGallery::class)
            ->getMock();

        // Mock the getItems method of ImageGallery to return an empty array
        $imageGallery->expects($this->once())
            ->method('getItems')
            ->willReturn([]);

        // Call the collect method of ImageGalleryDataCollector and expect an empty array
        $result = $this->imageGalleryDataCollector->collect($imageGallery, new ConfigReader([]));

        // Assert that the result is an empty array
        $this->assertEquals([], $result);
    }

    public function testSupports()
    {
        // Create a mock for ImageGallery
        $imageGallery = $this->getMockBuilder(ImageGallery::class)
            ->getMock();

        // Call the supports method of ImageGalleryDataCollector with the mock ImageGallery
        $supports = $this->imageGalleryDataCollector->supports($imageGallery);

        // Assert that the supports method correctly identifies ImageGallery
        $this->assertTrue($supports);
    }

    public function testSupportsWithNonImageGallery()
    {
        // Create a mock for a non-ImageGallery object
        $nonImageGallery = $this->getMockBuilder(stdClass::class)
            ->getMock();

        // Call the supports method of ImageGalleryDataCollector with the mock non-ImageGallery object
        $supports = $this->imageGalleryDataCollector->supports($nonImageGallery);

        // Assert that the supports method correctly identifies non-ImageGallery objects
        $this->assertFalse($supports);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock for the HotspotImageDataCollector
        $this->hotspotImageDataCollectorMock = $this->getMockBuilder(HotspotImageDataCollector::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Create an instance of ImageGalleryDataCollector with the mock HotspotImageDataCollector
        $this->imageGalleryDataCollector = new ImageGalleryDataCollector($this->hotspotImageDataCollectorMock);
    }
}
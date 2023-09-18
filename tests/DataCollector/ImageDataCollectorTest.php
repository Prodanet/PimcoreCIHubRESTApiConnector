<?php

namespace DataCollector;

use CIHub\Bundle\SimpleRESTAdapterBundle\DataCollector\ImageDataCollector;
use CIHub\Bundle\SimpleRESTAdapterBundle\Provider\AssetProvider;
use CIHub\Bundle\SimpleRESTAdapterBundle\Reader\ConfigReader;
use PHPUnit\Framework\TestCase;
use Pimcore\Model\Asset\Image;
use stdClass;
use Symfony\Component\Routing\RouterInterface;

class ImageDataCollectorTest extends TestCase
{
    /** @var ImageDataCollector */
    private ImageDataCollector $imageDataCollector;

    /** @var AssetProvider */
    private $assetProviderMock;

    public function testCollectWithValidImage()
    {
        // Create a mock for Image
        $image = $this->getMockBuilder(Image::class)
            ->getMock();

        // Mock the getId method of Image to return a sample ID
        $image->expects($this->once())
            ->method('getId')
            ->willReturn(123);

        // Create a mock for ConfigReader
        $configReader = $this->getMockBuilder(ConfigReader::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Mock the getAssetThumbnails method of ConfigReader to return sample thumbnails
        $configReader->expects($this->once())
            ->method('getAssetThumbnails')
            ->willReturn(['thumbnail1', 'thumbnail2']);

        // Mock the getBinaryDataValues method of AssetProvider to return sample binary data
        $this->assetProviderMock->expects($this->once())
            ->method('getBinaryDataValues')
            ->with($image, $configReader)
            ->willReturn(['data1', 'data2']);

        // Call the collect method of ImageDataCollector and expect the sample result
        $result = $this->imageDataCollector->collect($image, $configReader);

        // Assert that the result matches the expected data
        $this->assertEquals([
            'id' => 123,
            'type' => 'asset',
            'binaryData' => ['data1', 'data2'],
        ], $result);
    }

    public function testSupports()
    {
        // Create a mock for Image
        $image = $this->getMockBuilder(Image::class)
            ->getMock();

        // Call the supports method of ImageDataCollector with the mock Image
        $supports = $this->imageDataCollector->supports($image);

        // Assert that the supports method correctly identifies Image
        $this->assertTrue($supports);
    }

    public function testSupportsWithNonImage()
    {
        // Create a mock for a non-Image object
        $nonImage = $this->getMockBuilder(stdClass::class)
            ->getMock();

        // Call the supports method of ImageDataCollector with the mock non-Image object
        $supports = $this->imageDataCollector->supports($nonImage);

        // Assert that the supports method correctly identifies non-Image objects
        $this->assertFalse($supports);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock for RouterInterface
        $routerMock = $this->getMockBuilder(RouterInterface::class)
            ->getMock();

        // Create a mock for AssetProvider
        $this->assetProviderMock = $this->getMockBuilder(AssetProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Create an instance of ImageDataCollector with the mock RouterInterface and AssetProvider
        $this->imageDataCollector = new ImageDataCollector($routerMock, $this->assetProviderMock);
    }
}
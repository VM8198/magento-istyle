<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_ShopbyLite
 */


namespace Amasty\ShopbyLite\Test\Unit\Model\Layer\Filter;

use Amasty\ShopbyLite\Model\Layer\Filter\Item;
use Amasty\ShopbyLite\Test\Unit\Traits;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * Class ItemTest
 *
 * @see Item
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * phpcs:ignoreFile
 */
class ItemTest extends \PHPUnit\Framework\TestCase
{
    use Traits\ObjectManagerTrait;
    use Traits\ReflectionTrait;

    /**
     * @covers Item::getRemoveUrl
     */
    public function testGetRemoveUrl()
    {
        $model = $this->getObjectManager()->getObject(Item::class);
        $urlBuilderHelper = $this->createMock(\Amasty\ShopbyLite\Helper\UrlBuilder::class);
        $filter = $this->createMock(\Magento\Catalog\Model\Layer\Filter\AbstractFilter::class);

        $model->setValue(5);
        $model->setFilter($filter);
        $urlBuilderHelper->expects($this->exactly(2))->method('buildUrl')->willReturnArgument(1);

        $this->setProperty($model, 'urlBuilderHelper', $urlBuilderHelper);

        $this->assertEquals(5, $model->getRemoveUrl());
        $this->assertEquals(2, $model->getRemoveUrl(2));
    }
}

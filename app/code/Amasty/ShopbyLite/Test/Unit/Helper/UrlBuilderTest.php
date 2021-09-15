<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_ShopbyLite
 */


namespace Amasty\ShopbyLite\Test\Unit\Helper;

use Amasty\ShopbyLite\Helper\UrlBuilder;
use Amasty\ShopbyLite\Test\Unit\Traits;
use Magento\Framework\App\Helper\Context;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * Class UrlBuilderTest
 *
 * @see UrlBuilder
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * phpcs:ignoreFile
 */
class UrlBuilderTest extends \PHPUnit\Framework\TestCase
{
    use Traits\ObjectManagerTrait;
    use Traits\ReflectionTrait;

    /**
     * @covers UrlBuilder::buildUrl
     */
    public function testBuildUrl()
    {
        $context = $this->getObjectManager()->getObject(Context::class);
        $urlBuilder = $this->createMock(\Magento\Framework\UrlInterface::class);
        $this->setProperty($context, '_urlBuilder', $urlBuilder, Context::class);
        $helper = $this->getObjectManager()->getObject(UrlBuilder::class, ['context' => $context]);
        $filter = $this->createMock(\Magento\Catalog\Model\Layer\Filter\AbstractFilter::class);

        $urlBuilder->expects($this->any())->method('getUrl')->willReturnArgument(1);
        $filter->expects($this->any())->method('getRequestVar')->willReturn('test');
        $result = [
            '_current' => true,
            '_use_rewrite' => true,
            '_query' => [
                'test' => null,
                'p' => null,
                'shopbyAjax' => null,
                '_' => null,
            ],
            'price' => null
        ];

        $this->assertEquals($result, $helper->buildUrl($filter, null));
        $this->assertEquals($result, $helper->buildUrl($filter, [1, 2]));
        $result['_query'] = [
            'test' => 1,
            'p' => null,
            'shopbyAjax' => null,
            '_' => null,
        ];
        $this->assertEquals($result, $helper->buildUrl($filter, 1));
    }
}

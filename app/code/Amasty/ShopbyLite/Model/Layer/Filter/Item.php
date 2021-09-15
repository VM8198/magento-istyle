<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_ShopbyLite
 */


namespace Amasty\ShopbyLite\Model\Layer\Filter;

class Item extends \Magento\Catalog\Model\Layer\Filter\Item
{
    /**
     * @var  \Amasty\ShopbyLite\Helper\UrlBuilder
     */
    private $urlBuilderHelper;

    /**
     * @var \Amasty\ShopbyLite\Model\Request
     */
    private $shopbyRequest;

    public function __construct(
        \Magento\Framework\UrlInterface $url,
        \Magento\Theme\Block\Html\Pager $htmlPagerBlock,
        \Amasty\ShopbyLite\Helper\UrlBuilder $urlBuilderHelper,
        \Amasty\ShopbyLite\Model\Request $shopbyRequest,
        array $data = []
    ) {
        $this->urlBuilderHelper = $urlBuilderHelper;
        parent::__construct($url, $htmlPagerBlock, $data);
        $this->shopbyRequest = $shopbyRequest;
    }
    /**
     * Get filter item url
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getUrl()
    {
        return $this->urlBuilderHelper->buildUrl($this->getFilter(), $this->getValue());
    }

    /**
     * @param null $value
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getRemoveUrl($value = null)
    {
        $value = $value !== null ? $value : $this->getValue();

        return $this->urlBuilderHelper->buildUrl($this->getFilter(), $value);
    }

    /**
     * @return string
     */
    public function getOptionLabel()
    {
        return $this->getLabel();
    }

    /**
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function isSelected()
    {
        $filter = $this->getFilter();
        $filterParams = $this->shopbyRequest->getFilterParam($filter);

        if (!empty($filterParams)) {
            $filterParams = explode(',', $filterParams);
            return in_array($this->getValue(), $filterParams);
        }

        return false;
    }
}

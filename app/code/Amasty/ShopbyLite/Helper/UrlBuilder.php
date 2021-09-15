<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_ShopbyLite
 */


namespace Amasty\ShopbyLite\Helper;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use Magento\Framework\App\Helper\Context;

class UrlBuilder extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var FilterInterface
     */
    private $filter;

    /**
     * @var \Amasty\ShopbyLite\Helper\Data
     */
    private $helper;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        Context $context,
        \Amasty\ShopbyLite\Helper\Data $helper,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->helper = $helper;
        $this->storeManager = $storeManager;
    }

    /**
     * @param FilterInterface $filter
     * @param string|array $optionValue
     * @return string
     */
    public function buildUrl(FilterInterface $filter, $optionValue)
    {
        $this->setFilter($filter);

        $query = [$filter->getRequestVar() => $this->calculateResultValue($optionValue)];
        $query['p'] = null;
        $query['shopbyAjax'] = null;
        $query['_'] = null;

        $params = ['_current' => true, '_use_rewrite' => true, '_query' => $query];
        //fix urls like catalogsearch/result/index/price/10-20/?price=10-60&q=bag
        $params['price'] = null;

        return $this->getUrl('*/*/*', $params);
    }

    /**
     * @param FilterInterface $filter
     * @return $this
     */
    public function setFilter(FilterInterface $filter)
    {
        $this->filter = $filter;
        return $this;
    }

    /**
     * @return FilterInterface
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * @param $route
     * @param array $params
     * @return string
     */
    public function getUrl($route, $params = [])
    {
        return parent::_getUrl($route, $params);
    }

    /**
     * @param FilterInterface $filter
     * @param $resultValue
     * @return array|mixed
     */
    public function buildQuery(FilterInterface $filter, $resultValue)
    {
        return [$filter->getRequestVar() => $resultValue];
    }

    /**
     * @return array
     */
    private function getCurrentValues()
    {
        $values = [];
        $filterCode = $this->filter->getRequestVar();
        if ($this->filter->getRequestVar() == ProductAttributeInterface::CODE_PRICE && $this->useBasePrice()) {
            $filterCode = \Amasty\ShopbyLite\Model\Layer\Filter\Price::AM_BASE_PRICE;
        }

        $data = $this->_request->getParam($filterCode);

        if (!empty($data)) {
            $values = is_array($data) ? $data : explode(',', $data);
            foreach ($values as $key => $value) {
                if (empty($value)) {
                    unset($values[$key]);
                }
            }
        }

        return $values;
    }

    /**
     * @return bool
     */
    protected function useBasePrice()
    {
        $rate = $this->storeManager->getStore()->getCurrentCurrencyRate();

        return $rate && $rate != 1;
    }

    /**
     * @param $filter
     * @param $optionValue
     * @return string|null
     */
    private function calculateResultValue($optionValue)
    {
        if ($optionValue === null || is_array($optionValue)) {
            return null;
        }

        $key = array_search($optionValue, $this->getCurrentValues());

        if ($this->isMultiSelectAllowed()) {
            $result = $this->getCurrentValues();
            if ($key !== false) {
                unset($result[$key]);
            } else {
                $result[] = $optionValue;
            }
        } else {
            $result = $key !== false ? [] : [$optionValue];
        }

        $value = $result ? implode(',', $result) : null;

        return $value;
    }

    /**
     * @return bool
     */
    private function isMultiSelectAllowed()
    {
        return $this->helper->isMultiSelectAllowed($this->getFilter()->getRequestVar());
    }
}

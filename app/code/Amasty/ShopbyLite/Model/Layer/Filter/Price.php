<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_ShopbyLite
 */


namespace Amasty\ShopbyLite\Model\Layer\Filter;

use Amasty\ShopbyLite\Api\Data\FromToFilterInterface;
use Magento\Framework\Exception\StateException;
use Magento\Search\Model\SearchEngine;
use Magento\Catalog\Model\Layer\Filter\Dynamic\AlgorithmFactory;

/**
 * Class Price
 */
class Price extends \Magento\CatalogSearch\Model\Layer\Filter\Price implements FromToFilterInterface
{
    const NUMBERS_AFTER_POINT = 4;
    const AM_BASE_PRICE = 'am_base_price';
    const DELTA_FOR_BORDERS_RANGE = 0.0049;

    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * @var string
     */
    private $currencySymbol;

    /**
     * @var \Magento\Catalog\Model\Layer\Filter\DataProvider\Price
     */
    private $dataProvider;

    /**
     * @var \Amasty\ShopbyLite\Model\Request
     */
    private $shopbyRequest;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var SearchEngine
     */
    private $searchEngine;

    /**
     * @var \Amasty\ShopbyLite\Helper\Data
     */
    private $helper;

    /**
     * @var array
     */
    private $currentValue;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    private $messageManager;

    /**
     * @var array
     */
    private $facetedData;

    public function __construct(
        \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Layer $layer,
        \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder,
        \Magento\Catalog\Model\ResourceModel\Layer\Filter\Price $resource,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Search\Dynamic\Algorithm $priceAlgorithm,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\Catalog\Model\Layer\Filter\Dynamic\AlgorithmFactory $algorithmFactory,
        \Magento\Catalog\Model\Layer\Filter\DataProvider\PriceFactory $dataProviderFactory,
        \Amasty\ShopbyLite\Model\Request $shopbyRequest,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        SearchEngine $searchEngine,
        \Amasty\ShopbyLite\Helper\Data $helper,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        array $data = []
    ) {
        $this->currencySymbol = $priceCurrency->getCurrencySymbol();
        $this->dataProvider = $dataProviderFactory->create(['layer' => $layer]);
        $this->shopbyRequest = $shopbyRequest;
        $this->scopeConfig = $scopeConfig;
        $this->priceCurrency = $priceCurrency;
        $this->searchEngine = $searchEngine;
        $this->helper = $helper;
        $this->messageManager = $messageManager;
        parent::__construct(
            $filterItemFactory,
            $storeManager,
            $layer,
            $itemDataBuilder,
            $resource,
            $customerSession,
            $priceAlgorithm,
            $priceCurrency,
            $algorithmFactory,
            $dataProviderFactory,
            $data
        );
    }

    /**
     * @return array
     */
    public function getFromToConfig()
    {
        $facets = $this->getFacetedData();

        if (!isset($facets['data']) || (isset($facets['data']['count']) && $facets['data']['count'] == 0)) {
            return $this->getConfig();
        }

        $min = isset($facets['data']['min']) ? floatval($facets['data']['min']) : 0;
        $max = isset($facets['data']['max']) ? floatval($facets['data']['max']) : 0;
        $min *= $this->getCurrencyRate();
        $max *= $this->getCurrencyRate();
        $step = round($this->helper->getSliderStepValue(), 4);

        $maxByStep = $min;
        while ($maxByStep < $max) {
            $maxByStep += $step;
        }
        $max = $maxByStep;

        if ($min == $max) {
            return $this->getConfig();
        }

        $from = $this->getCurrentFrom() !== null
            ? $this->getCurrentFrom() ? floatval($this->getCurrentFrom()) : ''
            : null;
        $to = $this->getCurrentTo() !== null
            ? $this->getCurrentTo() ? floatval($this->getCurrentTo()) : ''
            : null;
        $template = $this->getTemplateForSlider();

        return [
                'from' => $from,
                'to' => $to,
                'min' => $min,
                'max' => $max,
                'requestVar' => $this->getRequestVar(),
                'step' => $step,
                'template' => $template,
                'curRate' => $this->getCurrencyRate(),
        ];
    }

    /**
     * @return array
     */
    private function getConfig()
    {
        return [
            'from' => null,
            'to' => null,
            'min' => null,
            'max' => null,
            'requestVar' => null,
            'step' => null,
            'template' => null,
            'curRate' => 1,
        ];
    }

    /**
     * @return bool
     */
    public function isApplied()
    {
        foreach ($this->getLayer()->getState()->getFilters() as $filter) {
            if ($filter->getFilter()->getRequestVar() == $this->getRequestVar()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    public function isMultiSelectAllowed()
    {
        return $this->helper->isMultiSelectAllowed($this->getRequestVar());
    }

    /**
     * @return int
     */
    public function getItemsCount()
    {
        $itemsCount = $this->isSlider() ? 0 : parent::getItemsCount();
        if ($itemsCount == 0) {
            /**
             * show up filter event don't have any option
             */
            $fromToConfig = $this->getFromToConfig();
            if ($fromToConfig && $fromToConfig['min'] != $fromToConfig['max']) {
                return 1;
            }
        }

        return $itemsCount;
    }

    /**
     * @return array
     */
    protected function _getItemsData()
    {
        $attribute = $this->getAttributeModel();
        $this->_requestVar = $attribute->getAttributeCode();

        $facets = $this->getFacetedData();

        $data = [];
        if (count($facets) > 1) { // two range minimum
            foreach ($facets as $key => $aggregation) {
                $count = $aggregation['count'];
                $pos = strpos($key, '_');
                if ($pos !== false) {
                    $data[] = $this->prepareData($key, $count);
                }
            }
        }

        if ($this->helper->isSliderAllowed() && count($data) == 1) {
            $data = [];
        }

        return $data;
    }

    /**
     * @param string $key
     * @param int $count
     * @return array
     */
    private function prepareData($key, $count)
    {
        list($from, $to) = explode('_', $key);
        if ($from == '*') {
            $from = $this->getFrom($to);
        }
        if ($to == '*') {
            $to = '';
        }

        $label = $this->_renderRangeLabel(
            empty($from) ? 0 : $from,
            $to ? $to - 0.01 : $to
        );

        $from = $from ? round($from * $this->getCurrencyRate(), self::NUMBERS_AFTER_POINT) : $from;
        $to = $to ? round($to * $this->getCurrencyRate(), self::NUMBERS_AFTER_POINT) : $to;

        $value = $from . '-' . $to . $this->dataProvider->getAdditionalRequestData();
        $data = [
            'label' => $label,
            'value' => $value,
            'count' => $count,
            'from' => $from,
            'to' => $to,
        ];

        return $data;
    }

    /**
     * @param \Magento\Framework\App\RequestInterface $request
     * @return $this
     */
    public function apply(\Magento\Framework\App\RequestInterface $request)
    {
        if ($this->isApplied()) {
            return $this;
        }

        $filter = $this->shopbyRequest->getFilterParam($this);
        $noValidate = 0;

        $newValue = '';

        if (!empty($filter) && is_string($filter)) {
            $copyFilter = $filter;
            $filter = explode('-', $filter);

            $toValue = isset($filter[1]) && $filter[1] ? $filter[1] : '';
            $filter = $filter[0] . '-' . $toValue;
            $validateFilter = $this->dataProvider->validateFilter($copyFilter);

            $values = explode('-', $copyFilter);
            $includeBorders = $this->isSlider() ? self::DELTA_FOR_BORDERS_RANGE : 0;

            $values[0] = $values[0] ? ($values[0] - $includeBorders) / $this->getCurrencyRate() : '';
            $values[1] = $values[1] ? ($values[1] + $includeBorders) / $this->getCurrencyRate() : '';

            $newValue = $values[0] . '-' . $values[1];

            if (!$validateFilter) {
                $noValidate = 1;
            } else {
                $this->setFromTo($validateFilter[0], $validateFilter[1]);
            }
        }

        $request->setQueryValue($this->getRequestVar(), $newValue ?: $filter);
        $request->setPostValue(self::AM_BASE_PRICE, isset($copyFilter) ? $copyFilter : $filter);

        parent::apply($request);

        $request->setQueryValue($this->getRequestVar(), $filter);

        if ($noValidate) {
            return $this;
        }

        if (!empty($filter) && !is_array($filter)) {
            if ($this->isSlider()) {
                $this->getLayer()->getProductCollection()->addFieldToFilter('price', $filter);
            }
        }

        return $this;
    }

    /**
     * @return bool
     */
    private function isSlider()
    {
        return $this->helper->isSliderAllowed();
    }

    /**
     * @return \Magento\Framework\Search\Response\QueryResponse|\Magento\Framework\Search\ResponseInterface|null
     */
    private function getAlteredQueryResponse()
    {
        $alteredQueryResponse = null;
        if ($this->hasCurrentValue()) {
            $productCollection = $this->getLayer()->getProductCollection();
            $requestBuilder = clone $productCollection->getMemRequestBuilder();
            $rangeCalculation = $this->scopeConfig->getValue(AlgorithmFactory::XML_PATH_RANGE_CALCULATION);
            if ($rangeCalculation != AlgorithmFactory::RANGE_CALCULATION_IMPROVED || $this->isSlider()) {
                $attributeCode = $this->getAttributeModel()->getAttributeCode();
                $requestBuilder->removePlaceholder($attributeCode . '.from');
                $requestBuilder->removePlaceholder($attributeCode . '.to');
                $requestBuilder->setAggregationsOnly($attributeCode);
            }
            $queryRequest = $requestBuilder->create();
            $alteredQueryResponse = $this->searchEngine->search($queryRequest);
        }

        return $alteredQueryResponse;
    }

    /**
     * @return mixed
     */
    private function getFacetedData()
    {
        if ($this->facetedData === null) {
            /** @var \Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection $productCollection */
            $productCollection = $this->getLayer()->getProductCollection();
            $alteredQueryResponse = $this->getAlteredQueryResponse();
            try {
                $facets = $productCollection->getFacetedData(
                    $this->getAttributeModel()->getAttributeCode(),
                    $alteredQueryResponse
                );
            } catch (StateException $e) {
                if (!$this->messageManager->hasMessages()) {
                    $this->messageManager->addErrorMessage(
                        __(
                            'Make sure that "%1" attribute can be used in layered navigation',
                            $this->getAttributeModel()->getAttributeCode()
                        )
                    );
                }
                $facets = [];
            }

            $this->facetedData = $facets;
        }

        return $this->facetedData;
    }

    /**
     * @param float|string $fromPrice
     * @param float|string $toPrice
     * @return float|\Magento\Framework\Phrase
     */
    protected function _renderRangeLabel($fromPrice, $toPrice)
    {
        $fromPrice = round($fromPrice * $this->getCurrencyRate(), self::NUMBERS_AFTER_POINT);
        if (!$toPrice) {
            $toPrice = 0;
        }
        if ($this->getCurrencyRate() != 1.0) {
            $toPrice = round($toPrice * $this->getCurrencyRate(), self::NUMBERS_AFTER_POINT);
        }

        $formattedFromPrice = $this->priceCurrency->format($fromPrice);
        if (!$toPrice) {
            return __('%1 and above', $formattedFromPrice);
        } elseif ($fromPrice == $toPrice && $this->dataProvider->getOnePriceIntervalValue()) {
            return $formattedFromPrice;
        } else {
            return __('%1 - %2', $formattedFromPrice, $this->priceCurrency->format($toPrice));
        }
    }

    /**
     * set from and to values for decimal filter
     * @param $from
     * @param $to
     *
     * @return $this
     */
    private function setFromTo($from, $to)
    {
        list($from, $to) = $this->prepareFromTo($from, $to);
        $this->setCurrentValue(['from' => $from, 'to' => $to]);
        return $this;
    }

    /**
     * @return null
     */
    public function getCurrentFrom()
    {
        return $this->getCurrentByKey('from');
    }

    /**
     * @return null
     */
    public function getCurrentTo()
    {
        return $this->getCurrentByKey('to');
    }

    /**
     * @param $key
     *
     * @return null
     */
    private function getCurrentByKey($key)
    {
        $currentValue = $this->getCurrentValue();
        return isset($currentValue[$key]) ? $currentValue[$key] : null;
    }

    /**
     * @param $from
     * @param $to
     *
     * @return array
     */
    private function prepareFromTo($from, $to)
    {
        if ($to && $from > $to) {
            $toTmp = $to;
            $to = $from;
            $from = $toTmp;
        }

        return [$from, $to];
    }

    /**
     * @param $number
     * @param int $slider
     * @return bool|int
     */
    public function getSignsCount($number, $slider = 1)
    {
        if (($number > 0 && $number < 1) && $slider) {
            $number = $this->trimZeros((string)$number);
            $pos = strpos($number, ".");
            if ($pos !== false) {
                return strlen($number) - $pos;
            }
        }

        return 0;
    }

    /**
     * @param $size
     * @return float
     */
    public function getFloatNumber($size)
    {
        if (!$size) {
            $size = 3;
        }

        return (float) 1 / (int)str_pad('1', $size, '0', STR_PAD_RIGHT);
    }

    /**
     * @param $str
     * @return mixed
     */
    private function trimZeros($str)
    {
        preg_match("/(\d\.\d*?[1-9]+)/i", $str, $matches);
        return isset($matches[0]) ? $matches[0] : $str;
    }

    /**
     * @param $min
     * @param $sliderMin
     * @return mixed
     */
    private function getMin($min, $sliderMin)
    {
        if ($sliderMin) {
            $min = ($sliderMin < $min) ? $min : $sliderMin;
        }

        return $min;
    }

    /**
     * @param $min
     * @param $max
     * @param $sliderMax
     * @return mixed
     */
    private function getMax($min, $max, $sliderMax)
    {
        if ($sliderMax) {
            $max = ($sliderMax > $max) && ($max > $min) ? $max : $sliderMax;
        }

        return $max;
    }

    /**
     * @return string
     */
    public function getCurrencySymbol()
    {
        return $this->currencySymbol;
    }

    /**
     * @return string
     */
    private function getTemplateForSlider()
    {
        /** @var \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency */
        $priceCurrency = \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Framework\Pricing\PriceCurrencyInterface::class);
        $trialValue = '345';

        //label position can be customized by "currency_display_options_forming" event. Trigger it.
        $formattedExample = $priceCurrency->format($trialValue, false, 0);

        $labelUnit = $this->currencySymbol;

        $pos = strpos($formattedExample, $trialValue);
        if ($pos ==! 0) {
            $template = $labelUnit . '{from}' . ' - ' . $labelUnit . '{to}';
        } else {
            $template = '{from}' . $labelUnit . ' - {to}' . $labelUnit;
        }

        return $template;
    }
}

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

/**
 * Class Decimal
 */
class Decimal extends \Magento\CatalogSearch\Model\Layer\Filter\Decimal implements FromToFilterInterface
{
    /**
     * @var \Magento\Catalog\Model\Layer\Filter\DataProvider\Price
     */
    private $dataProvider;

    /**
     * @var \Amasty\ShopbyLite\Model\Request
     */
    private $shopbyRequest;

    /**
     * @var array|null
     */
    private $facetedData;

    /**
     * @var SearchEngine
     */
    private $searchEngine;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    private $messageManager;

    /**
     * @var string
     */
    private $currencySymbol;

    /**
     * @var \Amasty\ShopbyLite\Helper\Data
     */
    private $helper;

    public function __construct(
        \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Layer $layer,
        \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder,
        \Magento\Catalog\Model\ResourceModel\Layer\Filter\DecimalFactory $filterDecimalFactory,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\Catalog\Model\Layer\Filter\DataProvider\PriceFactory $dataProviderFactory,
        \Amasty\ShopbyLite\Model\Request $shopbyRequest,
        SearchEngine $searchEngine,
        \Amasty\ShopbyLite\Helper\Data $helper,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        array $data = []
    ) {
        parent::__construct(
            $filterItemFactory,
            $storeManager,
            $layer,
            $itemDataBuilder,
            $filterDecimalFactory,
            $priceCurrency,
            $data
        );
        $this->currencySymbol = $priceCurrency->getCurrencySymbol();
        $this->dataProvider = $dataProviderFactory->create(['layer' => $layer]);
        $this->shopbyRequest = $shopbyRequest;
        $this->searchEngine = $searchEngine;
        $this->helper = $helper;
        $this->messageManager = $messageManager;
    }

    /**
     * @param \Magento\Framework\App\RequestInterface $request
     * @return $this
     */
    public function apply(\Magento\Framework\App\RequestInterface $request)
    {
        $filter = $this->shopbyRequest->getFilterParam($this);
        $isInvalid = false;
        if (!empty($filter) && !is_array($filter)) {
            $filterParams = explode(',', $filter);
            if (!$this->dataProvider->validateFilter($filterParams[0])) {
                $isInvalid = true;
            } else {
                $this->setCurrentValue(true);
            }
        }

        if ($this->isApplied()) {
            return $this;
        }

        if ($isInvalid) {
            return $this;
        }

        $request->setQueryValue($this->getRequestVar(), $filter);

        return parent::apply($request);
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
        $itemsCount = parent::getItemsCount();

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
    public function getFromToConfig()
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
     * @return array
     */
    protected function _getItemsData()
    {
        /** @var \Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection $productCollection */
        $productCollection = $this->getLayer()->getProductCollection();
        $productSize = $productCollection->getSize();
        $facets = $this->getFacetedData();

        $data = [];
        foreach ($facets as $key => $aggregation) {
            if ($key === 'data') {
                continue;
            }
            $count = $aggregation['count'];

            list($from, $to) = explode('_', $key);
            if ($from == '*') {
                $from = '';
            }
            if ($to == '*') {
                $to = '';
            }

            $label = $this->renderRangeLabel(
                empty($from) ? 0 : $from,
                empty($to) ? $to : $to
            );

            $value = $from . '-' . $to;

            $data[] = [
                'label' => $label,
                'value' => $value,
                'count' => $count,
                'from' => $from,
                'to' => $to
            ];
        }

        return $data;
    }

    private function getAlteredQueryResponse()
    {
        $alteredQueryResponse = null;
        if ($this->hasCurrentValue()) {
            $attribute = $this->getAttributeModel();
            $productCollection = $this->getLayer()->getProductCollection();
            $requestBuilder = clone $productCollection->getMemRequestBuilder();
            $requestBuilder->removePlaceholder($attribute->getAttributeCode() . '.from');
            $requestBuilder->removePlaceholder($attribute->getAttributeCode() . '.to');
            $requestBuilder->setAggregationsOnly($attribute->getAttributeCode());
            $queryRequest = $requestBuilder->create();
            $alteredQueryResponse = $this->searchEngine->search($queryRequest);
        }

        return $alteredQueryResponse;
    }

    /**
     * @return array
     */
    private function getFacetedData()
    {
        if ($this->facetedData === null) {
            $productCollection = $this->getLayer()->getProductCollection();
            $alteredQueryResponse = $this->getAlteredQueryResponse();
            try {
                $this->facetedData = $productCollection->getFacetedData(
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
                $this->facetedData = [];
            }
        }

        return $this->facetedData;
    }

    /**
     * @param float|string $fromPrice
     * @param float|string $toPrice
     * @return \Magento\Framework\Phrase
     */
    protected function renderRangeLabel($fromPrice, $toPrice)
    {
        $formattedFromPrice = $this->formatLabelForStateAndRange($fromPrice);
        if ($toPrice === '') {
            return __('%1 and above', $formattedFromPrice);
        } else {
            return __(
                '%1 - %2',
                $formattedFromPrice,
                $this->formatLabelForStateAndRange($toPrice)
            );
        }
    }

    /**
     * @param $value
     * @param $filterSetting
     * @return string
     */
    private function formatLabelForStateAndRange($value)
    {
        return round((float)$value, 2);
    }
}

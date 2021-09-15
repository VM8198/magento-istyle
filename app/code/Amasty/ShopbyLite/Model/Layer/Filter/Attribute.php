<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_ShopbyLite
 */


namespace Amasty\ShopbyLite\Model\Layer\Filter;

use Magento\Catalog\Model\Layer;
use Magento\Framework\Exception\StateException;
use Magento\Search\Model\SearchEngine;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Framework\Filter\StripTags as TagFilter;
use \Magento\Catalog\Model\Layer\Filter\ItemFactory as FilterItemFactory;
use Magento\Catalog\Model\Layer\Filter\Item\DataBuilder as ItemDataBuilder;

/**
 * Class Attribute
 */
class Attribute extends AbstractFilter
{
    const NO_RESULTS_COUNT = 0;

    /**
     * @var TagFilter
     */
    private $tagFilter;

    /**
     * @var SearchEngine
     */
    private $searchEngine;

    /**
     * @var \Amasty\ShopbyLite\Model\Request
     */
    private $shopbyRequest;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    private $messageManager;

    /**
     * @var mixed current applied value
     */
    private $currentValue;

    /**
     * @var \Amasty\ShopbyLite\Helper\Data
     */
    private $helper;

    public function __construct(
        FilterItemFactory $filterItemFactory,
        StoreManagerInterface $storeManager,
        Layer $layer,
        ItemDataBuilder $itemDataBuilder,
        TagFilter $tagFilter,
        SearchEngine $searchEngine,
        \Amasty\ShopbyLite\Model\Request $shopbyRequest,
        \Amasty\ShopbyLite\Helper\Data $helper,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        array $data = []
    ) {
        parent::__construct(
            $filterItemFactory,
            $storeManager,
            $layer,
            $itemDataBuilder,
            $data
        );

        $this->tagFilter = $tagFilter;
        $this->shopbyRequest = $shopbyRequest;
        $this->searchEngine = $searchEngine;
        $this->messageManager = $messageManager;
        $this->helper = $helper;
    }

    /**
     * Apply attribute option filter to product collection.
     *
     * @param \Magento\Framework\App\RequestInterface $request
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function apply(\Magento\Framework\App\RequestInterface $request)
    {
        if ($this->isApplied()) {
            return $this;
        }

        /** @TODO Remove this request */
        $requestedOptionsString = $this->shopbyRequest->getFilterParam($this);

        if (empty($requestedOptionsString)) {
            return $this;
        }

        $requestedOptions = explode(',', $requestedOptionsString);

        if (!$this->isMultiSelectAllowed() && count($requestedOptions) > 1) {
            $requestedOptions = array_slice($requestedOptions, 0, 1);
        }

        $this->setCurrentValue($requestedOptions);
        $this->addState($requestedOptions);

        $attribute = $this->getAttributeModel();

        /** @var \Amasty\ShopbyLite\Model\ResourceModel\Fulltext\Collection $productCollection */
        $productCollection = $this->getLayer()->getProductCollection();
        $productCollection->addFieldToFilter($attribute->getAttributeCode(), $requestedOptions);

        return $this;
    }

    /**
     * @param array $values
     */
    private function addState(array $values)
    {
        if (!$this->shouldAddState()) {
            return;
        }

        foreach ($values as $value) {
            $label = $this->getOptionText($value);
            $item = $this->_createItem($label, $values);
            $this->getLayer()->getState()->addFilter($item);
        }
    }

    /**
     * @return bool
     */
    public function shouldAddState()
    {
        // Could be overwritten in plugins.
        return true;
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
        return count($this->getItems());
    }

    /**
     * @param array $a
     * @param array $b
     * @return int
     */
    public function sortOption($a, $b)
    {
        $pattern = '@^(\d+)@';
        if (preg_match($pattern, $a['label'], $ma) && preg_match($pattern, $b['label'], $mb)) {
            $r = $ma[1] - $mb[1];
            if ($r != 0) {
                return $r;
            }
        }

        return strcasecmp($a['label'], $b['label']);
    }

    /**
     * Get data array for building attribute filter items.
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _getItemsData()
    {
        $options = $this->getOptions();
        if (!$optionsFacetedData = $this->getOptionsFacetedData()) {
            return [];
        }

        $this->addItemsToDataBuilder($options, $optionsFacetedData);

        $itemsData = $this->getItemsFromDataBuilder();

        return $itemsData;
    }

    /**
     * @return array
     */
    private function getOptions()
    {
        return $this->getAttributeModel()->getFrontend()->getSelectOptions();
    }

    /**
     * @return array
     */
    private function getOptionsFacetedData()
    {
        /** @var \Amasty\ShopbyLite\Model\ResourceModel\Fulltext\Collection $productCollectionOrigin */
        $productCollectionOrigin = $this->getLayer()->getProductCollection();
        $attribute = $this->getAttributeModel();

        $alteredQueryResponse = $this->getAlteredQueryResponse();
        try {
            $optionsFacetedData = $productCollectionOrigin->getFacetedData(
                $attribute->getAttributeCode(),
                $alteredQueryResponse
            );

            if (count($optionsFacetedData)) {
                $attributeValue = $this->shopbyRequest->getFilterParam($this);
                $values = explode(",", $attributeValue);
                foreach ($values as $value) {
                    if (!empty($value) && !array_key_exists($value, $optionsFacetedData)) {
                        $optionsFacetedData[$value] = ['value' => $value, 'count' => self::NO_RESULTS_COUNT];
                    }
                }
            }
        } catch (StateException $e) {
            if (!$this->messageManager->hasMessages()) {
                $this->messageManager->addErrorMessage(
                    __(
                        'Make sure that "%1" attribute can be used in layered navigation',
                        $attribute->getAttributeCode()
                    )
                );
            }
            $optionsFacetedData = [];
        }

        return $optionsFacetedData;
    }

    /**
     * @return \Magento\Framework\Search\ResponseInterface|null
     */
    private function getAlteredQueryResponse()
    {
        $alteredQueryResponse = null;
        if ($this->hasCurrentValue()) {
            /** @var \Amasty\ShopbyLite\Model\ResourceModel\Fulltext\Collection $productCollection */
            $productCollection = $this->getLayer()->getProductCollection();
            $requestBuilder = clone $productCollection->getMemRequestBuilder();
            $attributeCode = $this->getAttributeModel()->getAttributeCode();
            $requestBuilder->removePlaceholder($attributeCode);
            $requestBuilder->setAggregationsOnly($attributeCode);
            $queryRequest = $requestBuilder->create();
            $alteredQueryResponse = $this->searchEngine->search($queryRequest);
        }

        return $alteredQueryResponse;
    }

    /**
     * @param array $options
     * @param array $optionsFacetedData
     * @return $this;
     */
    private function addItemsToDataBuilder($options, $optionsFacetedData)
    {
        foreach ($options as $option) {
            if (empty($option['value'])) {
                continue;
            }

            $isFilterableAttribute = $this->getAttributeIsFilterable($this->getAttributeModel());
            if (isset($optionsFacetedData[$option['value']])
                || $isFilterableAttribute != self::ATTRIBUTE_OPTIONS_ONLY_WITH_RESULTS
            ) {
                $count = isset($optionsFacetedData[$option['value']]['count'])
                    ? $optionsFacetedData[$option['value']]['count'] : self::NO_RESULTS_COUNT;
                $this->itemDataBuilder->addItemData(
                    $this->tagFilter->filter($option['label']),
                    $option['value'],
                    $count
                );
            }
        }
        return $this;
    }

    /**
     * Get items data according to attribute settings.
     * @return array
     */
    private function getItemsFromDataBuilder()
    {
        $itemsData = $this->itemDataBuilder->build();
        if (count($itemsData) == 1) {
            $currentItemData = current($itemsData);
            $collectionSize = $this->getLayer()->getProductCollection()->getSize();
            if (!$this->isOptionReducesResults($currentItemData['count'], $collectionSize)) {
                $itemsData = $this->getReducedItemsData($itemsData);
            }
        }

        return $itemsData;
    }

    /**
     * @param mixed $currentValue
     */
    private function setCurrentValue($currentValue)
    {
        $this->currentValue = $currentValue;
    }

    /**
     * @return bool is filter applied
     */
    private function hasCurrentValue()
    {
        return $this->currentValue != null;
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
     * Must not remove filter with one option if it is applied.
     *
     * @param array $itemsData
     * @return array
     */
    private function getReducedItemsData(array $itemsData)
    {
        return $this->isApplied() ? $itemsData : [];
    }
}

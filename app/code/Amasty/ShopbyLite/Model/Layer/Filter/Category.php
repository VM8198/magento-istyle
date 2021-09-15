<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_ShopbyLite
 */


namespace Amasty\ShopbyLite\Model\Layer\Filter;

use Amasty\ShopbyLite\Model\Source\Filter;
use Magento\Search\Model\SearchEngine;

/**
 * Layer category filter
 */
class Category extends \Magento\Catalog\Model\Layer\Filter\AbstractFilter
{
    /**
     * @var \Magento\Framework\Escaper
     */
    private $escaper;

    /**
     * @var \Magento\Catalog\Model\Layer\Filter\DataProvider\Category
     */
    private $dataProvider;

    /**
     * @var \Amasty\ShopbyLite\Helper\Data
     */
    private $helper;

    /**
     * @var \Amasty\ShopbyLite\Model\Request
     */
    private $shopbyRequest;

    /**
     * @var SearchEngine
     */
    private $searchEngine;

    public function __construct(
        \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Layer $layer,
        \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder,
        \Magento\Framework\Escaper $escaper,
        \Magento\Catalog\Model\Layer\Filter\DataProvider\CategoryFactory $categoryDataProviderFactory,
        \Amasty\ShopbyLite\Helper\Data $helper,
        \Amasty\ShopbyLite\Model\Request $shopbyRequest,
        SearchEngine $searchEngine,
        array $data = []
    ) {
        parent::__construct(
            $filterItemFactory,
            $storeManager,
            $layer,
            $itemDataBuilder,
            $data
        );
        $this->helper = $helper;
        $this->escaper = $escaper;
        $this->_requestVar = 'cat';
        $this->dataProvider = $categoryDataProviderFactory->create(['layer' => $this->getLayer()]);
        $this->shopbyRequest = $shopbyRequest;
        $this->searchEngine = $searchEngine;
    }

    /**
     * Apply category filter to product collection
     *
     * @param   \Magento\Framework\App\RequestInterface $request
     * @return  $this
     */
    public function apply(\Magento\Framework\App\RequestInterface $request)
    {
        if ($this->isApplied()) {
            return $this;
        }

        $categoryId = $this->shopbyRequest->getFilterParam($this);
        if (empty($categoryId)) {
            return $this;
        }

        $this->dataProvider->setCategoryId($categoryId);
        if ($this->dataProvider->isValid()) {
            $category = $this->dataProvider->getCategory();
            $this->getLayer()->getProductCollection()->addCategoryFilter($category);
            $this->getLayer()->getState()->addFilter($this->_createItem($category->getName(), $categoryId));
        }

        return $this;
    }

    /**
     * Get filter value for reset current filter state
     *
     * @return mixed|null
     */
    public function getResetValue()
    {
        return $this->dataProvider->getResetValue();
    }

    /**
     * Get filter name
     *
     * @return \Magento\Framework\Phrase
     */
    public function getName()
    {
        return __('Category');
    }

    /**
     * Get data array for building category filter items
     *
     * @return array
     */
    protected function _getItemsData()
    {
        $optionsFacetedData = $this->getFacetedData();
        $category = $this->getLayer()->getCurrentCategory();
        $categories = $category->getChildrenCategories();

        $this->getLayer()->getProductCollection()->addCountToCategories($categories);

        if ($category->getIsActive()) {
            foreach ($categories as $category) {
                if ($category->getIsActive() && isset($optionsFacetedData[$category->getId()])) {
                    $this->itemDataBuilder->addItemData(
                        $this->escaper->escapeHtml($category->getName()),
                        $category->getId(),
                        $optionsFacetedData[$category->getId()]['count']
                    );
                }
            }
        }
        return $this->itemDataBuilder->build();
    }

    /**
     * @return array
     */
    protected function getFacetedData()
    {
        /** @var \Amasty\ShopbyLite\Model\ResourceModel\Fulltext\Collection $productCollection */
        $productCollection = $this->getLayer()->getProductCollection();

        $alteredQueryResponse = $this->getAlteredQueryResponse();
        $optionsFacetedData = $productCollection->getFacetedData('category', $alteredQueryResponse);

        return $optionsFacetedData;
    }

    /**
     * @return \Magento\Framework\Search\ResponseInterface|null
     */
    private function getAlteredQueryResponse()
    {
        $categoryId = $this->getLayer()->getCurrentCategory()->getId();

        /** @var \Amasty\ShopbyLite\Model\ResourceModel\Fulltext\Collection $productCollection */
        $productCollection = $this->getLayer()->getProductCollection();
        $requestBuilder = clone $productCollection->getMemRequestBuilder();
        $requestBuilder->removePlaceholder(Filter::ATTRIBUTE_CODE_CATEGORY);
        $requestBuilder->bind(Filter::ATTRIBUTE_CODE_CATEGORY, $categoryId);
        $requestBuilder->setAggregationsOnly(Filter::ATTRIBUTE_CODE_CATEGORY);

        return $this->searchEngine->search($requestBuilder->create());
    }

    /**
     * @return bool
     */
    public function isMultiselectAllowed()
    {
        return false;
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
}

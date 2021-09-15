<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_ShopbyLite
 */


namespace Amasty\ShopbyLite\Model\Layer;

/**
 * Class FilterList
 */
class FilterList extends \Magento\Catalog\Model\Layer\FilterList
{
    /**
     * @var \Magento\Framework\App\Request\Http
     */
    private $request;

    /**
     * @var bool
     */
    private $filtersLoaded  = false;

    /**
     * @var  \Magento\Framework\Registry
     */
    private $registry;

    /**
     * @var \Amasty\ShopbyLite\Model\Request
     */
    private $shopbyRequest;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Catalog\Model\Layer\FilterableAttributeListInterface $filterableAttributes,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\Registry $registry,
        \Amasty\ShopbyLite\Model\Request $shopbyRequest,
        array $filters = []
    ) {
        $this->request = $request;
        $this->registry = $registry;
        $this->shopbyRequest = $shopbyRequest;
        parent::__construct($objectManager, $filterableAttributes, $filters);
    }

    /**
     * @param \Magento\Catalog\Model\Layer $layer
     * @return array|\Magento\Catalog\Model\Layer\Filter\AbstractFilter[]
     */
    public function getFilters(\Magento\Catalog\Model\Layer $layer)
    {
        if (!$this->filtersLoaded) {
            $this->filters = $this->getAllFilters($layer);
            $this->filtersLoaded = true;
        }
        return $this->filters;
    }

    /**
     * Get both top and left filters. And keep it in registry.
     *
     * @param \Magento\Catalog\Model\Layer $layer
     * @return \Magento\Catalog\Model\Layer\Filter\AbstractFilter[]
     */
    public function getAllFilters(\Magento\Catalog\Model\Layer $layer)
    {
        return parent::getFilters($layer);
    }
}

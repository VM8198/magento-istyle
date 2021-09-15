<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_ShopbyLite
 */


namespace Amasty\ShopbyLite\Block\Navigation\Component;

use Magento\Framework\View\Element\Template;
use Magento\Framework\DataObject\IdentityInterface;
use Amasty\ShopbyLite\Model\Layer\FilterList;

/**
 * Class Ajax
 */
class Ajax extends \Magento\Framework\View\Element\Template implements IdentityInterface
{
    const CACHE_TAG = 'client_';

    /**
     * @var \Amasty\ShopbyLite\Helper\Data
     */
    private $helper;

    /**
     * @var \Magento\Catalog\Model\Layer
     */
    private $layer;

    /**
     * @var \Magento\Framework\Registry
     */
    private $registry;

    /**
     * @var \Magento\Framework\Module\Manager
     */
    private $moduleManager;

    public function __construct(
        Template\Context $context,
        \Magento\Catalog\Model\Layer\Resolver $layerResolver,
        \Amasty\ShopbyLite\Helper\Data $helper,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Module\Manager $moduleManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->layer = $layerResolver->get();
        $this->helper = $helper;
        $this->registry = $registry;
        $this->moduleManager = $moduleManager;
    }

    /**
     * @return bool
     */
    public function canShowBlock()
    {
        return $this->helper->isAjaxEnabled();
    }

    /**
     * @return []
     */
    public function getIdentities()
    {
        return [];
    }

    /**
     * @return bool
     */
    public function scrollUp()
    {
        return $this->helper->isScrollUp();
    }

    /**
     * Retrieve active filters
     *
     * @return array
     */
    protected function getActiveFilters()
    {
        $filters = $this->layer->getState()->getFilters();
        if (!is_array($filters)) {
            $filters = [];
        }

        return $filters;
    }

    /**
     * Retrieve Clear Filters URL
     *
     * @return string
     */
    public function getClearUrl()
    {
        return $this->helper->getAjaxCleanUrl($this->getActiveFilters());
    }

    /**
     * @return int
     */
    public function getCurrentCategoryId()
    {
        return $this->helper->getCurrentCategory()->getId();
    }

    /**
     * @return int
     */
    public function isCategorySingleSelect()
    {
        return 0;
    }

    /**
     * Get config
     *
     * @param string $path
     *
     * @return string
     */
    public function getConfig($path)
    {
        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }
}

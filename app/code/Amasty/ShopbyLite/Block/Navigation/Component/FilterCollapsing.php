<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_ShopbyLite
 */


namespace Amasty\ShopbyLite\Block\Navigation\Component;

use Amasty\ShopbyLite\Model\Layer\FilterList;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Framework\View\Element\Template;

/**
 * Class FilterCollapsing
 */
class FilterCollapsing extends Template
{
    /**
     * @var string
     */
    protected $_template = 'Amasty_ShopbyLite::navigation/filter_collapsing.phtml';

    /**
     * @var FilterList
     */
    private $filterList;

    /**
     * @var \Magento\Catalog\Model\Layer
     */
    private $catalogLayer;

    public function __construct(
        FilterList $filterList,
        LayerResolver $layerResolver,
        Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->filterList = $filterList;
        $this->catalogLayer = $layerResolver->get();
    }

    /**
     * @return array
     */
    public function getFiltersExpanded()
    {
        $expandedFilters = [];
        $position = 0;
        foreach ($this->filterList->getFilters($this->catalogLayer) as $filter) {
            if ($filter->getItemsCount()) {
                if ($filter->isApplied()) {
                    $expandedFilters[] = $position;
                }
                $position++;
            }
        }

        return $expandedFilters;
    }
}

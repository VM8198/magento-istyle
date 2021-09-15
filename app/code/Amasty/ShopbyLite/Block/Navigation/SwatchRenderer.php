<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_ShopbyLite
 */


namespace Amasty\ShopbyLite\Block\Navigation;

use Magento\Swatches\Block\LayeredNavigation\RenderLayered;
use Magento\Catalog\Model\Layer\Filter\Item as FilterItem;
use Magento\Eav\Model\Entity\Attribute\Option;

/**
 * Class SwatchRenderer
 */
class SwatchRenderer extends RenderLayered
{
    protected $_template = 'Amasty_ShopbyLite::layer/filter/swatches.phtml';

    /**
     * @return \Magento\Catalog\Model\Layer\Filter\AbstractFilter
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * @param FilterItem $filterItem
     * @param Option $swatchOption
     * @return array
     */
    protected function getOptionViewData(FilterItem $filterItem, Option $swatchOption)
    {
        $data = parent::getOptionViewData($filterItem, $swatchOption);
        $data['selected'] = $filterItem->isSelected();
        if ($data['selected']) {
            $data['custom_style'] = ' selected';
        }

        return $data;
    }

    /**
     * @param string $attributeCode
     * @param int $optionId
     * @return string
     */
    public function buildUrl($attributeCode, $optionId)
    {
        return $this->getData('urlBuilderHelper')->buildUrl($this->filter, $optionId);
    }
}

<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_ShopbyLite
 */


namespace Amasty\ShopbyLite\Model\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\Eav\Model\Config as EavConfig;

/**
 * Class Attribute
 */
class Attribute implements ArrayInterface
{
    /**
     * @var EavConfig
     */
    private $eavConfig;

    /**
     * @var array
     */
    private $attributes;

    /**
     * @var int
     */
    private $skipAttributeId;

    /**
     * @param EavConfig $eavConfig
     */
    public function __construct(
        EavConfig $eavConfig
    ) {
        $this->eavConfig = $eavConfig;
    }

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray($boolean = 1)
    {
        $optionArray = [];
        $arr = $this->toArray($boolean);
        foreach ($arr as $value => $label) {
            $optionArray[] = [
                'value' => $value,
                'label' => $label
            ];
        }
        return $optionArray;
    }

    /**
     * Get options in "key-value" format
     *
     * @param bool $boolean = true
     * @return array
     */
    public function toArray($boolean = true)
    {
        if ($this->attributes === null) {
            $this->attributes = [];
            /** @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Collection $collection */
            $collection = $this->eavConfig->getEntityType(
                \Magento\Catalog\Model\Product::ENTITY
            )->getAttributeCollection();

            $collection->join(
                ['catalog_eav' => $collection->getTable('catalog_eav_attribute')],
                'catalog_eav.attribute_id=main_table.attribute_id',
                []
            )->addFieldToFilter('catalog_eav.is_filterable', ['neq' =>  0]);

            if ($this->skipAttributeId !== null) {
                $collection->addFieldToFilter('main_table.attribute_id', ['neq' => $this->skipAttributeId]);
            }
            if (!$boolean) {
                $collection->addFieldToFilter('main_table.frontend_input', ['neq' => 'boolean']);
            }
            $collection->addFieldToFilter('main_table.frontend_input', ['in' => ['select', 'multiselect']]);
            /** @var \Magento\Eav\Model\Attribute $item */
            foreach ($collection as $item) {
                $this->attributes[$item->getAttributeCode()] = $item->getFrontendLabel();
            }
        }

        return $this->attributes;
    }

    /**
     * @param $skipAttributeId
     * @return $this
     */
    public function skipAttributeId($skipAttributeId)
    {
        $this->skipAttributeId = $skipAttributeId;
        return $this;
    }
}

<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_ShopbyLite
 */


namespace Amasty\ShopbyLite\Block\Navigation;

use Amasty\ShopbyLite\Model\Layer\Filter\Item;
use Amasty\ShopbyLite\Helper\Data as ShopbyLiteHelper;

/**
 * Class State
 */
class State extends \Magento\LayeredNavigation\Block\Navigation\State
{
    /**
     * @var string
     */
    protected $_template = 'Amasty_ShopbyLite::layer/state.phtml';
    
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * @var ShopbyLiteHelper
     */
    private $helper;

    /**
     * @var \Magento\Framework\View\Element\BlockFactory
     */
    private $blockFactory;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Catalog\Model\Layer\Resolver $layerResolver,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        ShopbyLiteHelper $helper,
        \Magento\Framework\View\Element\BlockFactory $blockFactory,
        array $data = []
    ) {
        $this->storeManager = $context->getStoreManager();
        $this->priceCurrency = $priceCurrency;
        $this->helper = $helper;
        $this->blockFactory = $blockFactory;
        parent::__construct($context, $layerResolver, $data);
    }

    /**
     * @param Item $filter
     * @return string
     */
    public function viewLabel($filter)
    {
        return $this->viewExtendedLabel($filter);
    }

    /**
     * @param Item $filter
     * @return string
     */
    protected function viewExtendedLabel($filter)
    {
        if ($filter->getFilter()->getRequestVar() == 'price') {
            $currencyRate = (float) $filter->getFilter()->getCurrencyRate();

            if ($currencyRate != 1) {
                $value = $this->generateValueLabel($filter);
            } else {
                $value = $filter->getOptionLabel();
            }
        } else {
            $value = $this->stripTags($filter->getOptionLabel());
        }

        return $value;
    }

    /**
     * @param $filterItem
     * @param $currencyRate
     * @return \Magento\Framework\Phrase
     */
    private function generateValueLabel($filterItem)
    {
        $arguments = $filterItem->getLabel()->getArguments();
        $filter = $filterItem->getFilter();

        $stepSlider = $this->helper->getSliderStepValue();

        if (!isset($arguments[1])) {
            $arguments[1] = "";
        }

        $currencySymbol = $this->escapeHtml($filter->getCurrencySymbol());

        $arguments[0] = preg_replace("/[^,.0-9]/", '', $arguments[0]);
        $arguments[1] = preg_replace("/[^,.0-9]/", '', $arguments[1]);

        $posDotInFrom = strpos($arguments[0], '.');
        $posDotInTo = strpos($arguments[1], '.');
        $posCommaInFrom = strpos($arguments[0], ',');
        $posCommaInTo = strpos($arguments[1], ',');

        $arguments[0] = $this->removeSeparator($posDotInFrom, $posCommaInFrom, $arguments[0]);
        $arguments[1] = $this->removeSeparator($posDotInTo, $posCommaInTo, $arguments[1]);

        $arguments[0] = preg_replace("/[']/", '', $arguments[0]);
        $arguments[1] = preg_replace("/[']/", '', $arguments[1]);

        $value = __(
            '%1 - %2',
            $this->generateSpanPrice((float)$arguments[0], $stepSlider, $currencySymbol),
            $this->generateSpanPrice(
                $arguments[1] ? (float)$arguments[1] : $arguments[1],
                $stepSlider,
                $currencySymbol,
                true
            )
        );

        return $value;
    }

    /**
     * @param $posDot
     * @param $posComma
     * @param $value
     * @return string
     */
    private function removeSeparator($posDot, $posComma, $value)
    {
        if ($posDot !== false && $posComma !== false) {
            if ($posDot < $posComma) {
                $value = preg_replace("/[.]/", '', $value);
            } else {
                $value = preg_replace("/[,]/", '', $value);
            }
        }

        return $value;
    }

    /**
     * @param $value
     * @param $currencyRate
     * @param $stepSlider
     * @param $currencySymbol
     * @return string
     */
    private function generateSpanPrice($value, $stepSlider, $currencySymbol, $flagTo = false)
    {
        if (!$value && $flagTo) {
            $resultPrice = __('above');
        } else {
            $resultPrice = $this->priceCurrency->format($value);
        }

        return '<span class="price">' . $resultPrice . '</span>';
    }

    /**
     * @param $value
     * @param $filterItem
     * @return float|string
     */
    public function getFilterValue($value, $filterItem)
    {
        $filter = $filterItem->getFilter();
        if ($filter instanceof \Amasty\ShopbyLite\Model\Layer\Filter\Price && count($value) >= 2) {
            $value[0] = $value[0] ? $value[0] * $filter->getCurrencyRate() : '';
            $value[1] = $value[1] ? $value[1] * $filter->getCurrencyRate() : '';
        } elseif (is_array($value)) {
            $value = $value[0];
        }

        return $value;
    }

    /**
     * @param $resultValue
     * @return string
     */
    public function getDataValue($resultValue)
    {
        $value = null;

        if (isset($resultValue)) {
            $value = $this->escapeHtml(
                $this->stripTags(is_array($resultValue) ? implode('-', $resultValue) : $resultValue, false)
            );
        }

        return $value;
    }

    /**
     * @param $filter
     * @param $value
     * @return array
     */
    public function changeValueForMultiselect($filter, $value)
    {
        if ($filter instanceof \Amasty\ShopbyLite\Model\Layer\Filter\Price) {
            $value = [];
        } else {
            $value = array_filter(array_slice((array)$value, 1));
        }

        return $value;
    }
}

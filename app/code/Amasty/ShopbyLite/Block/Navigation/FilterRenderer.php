<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_ShopbyLite
 */


namespace Amasty\ShopbyLite\Block\Navigation;

use Amasty\ShopbyLite\Helper\Data as ShopbyLiteHelper;
use Amasty\ShopbyLite\Helper\UrlBuilder;
use Amasty\ShopbyLite\Model\Layer\Filter\Item;
use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Framework\Exception\LocalizedException;
use Magento\LayeredNavigation\Block\Navigation\FilterRendererInterface;
use Magento\LayeredNavigation\Block\Navigation\FilterRenderer as NativeFilterRenderer;
use Amasty\ShopbyLite\Model\Source\Filter;

/**
 * Class FilterRenderer
 */
class FilterRenderer extends NativeFilterRenderer implements FilterRendererInterface
{
    const FILTER_TEMPLATE_SLIDER = 'Amasty_ShopbyLite::layer/filter/slider.phtml';
    const FILTER_TEMPLATE_DEFAULT = 'Amasty_ShopbyLite::layer/filter/default.phtml';

    /**
     * @var  UrlBuilder
     */
    private $urlBuilder;

    /**
     * @var  FilterInterface
     */
    private $filter;

    /**
     * @var ShopbyLiteHelper
     */
    private $helper;

    /**
     * @var \Magento\Catalog\Model\Layer
     */
    private $layer;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        UrlBuilder $urlBuilder,
        ShopbyLiteHelper $helper,
        Resolver $resolver,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->urlBuilder = $urlBuilder;
        $this->helper = $helper;
        $this->layer = $resolver->get();
    }

    /**
     * @param FilterInterface $filter
     * @return string
     */
    public function render(FilterInterface $filter)
    {
        $this->setFilter($filter);
        $this->setTemplate($this->getFilterTemplate());

        return parent::render($filter);
    }

    /**
     * @param FilterInterface $filter
     * @return $this
     */
    public function setFilter(FilterInterface $filter)
    {
        $this->filter = $filter;
        return $this;
    }

    /**
     * @return FilterInterface
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * @return string
     */
    private function getFilterTemplate()
    {
        $template = self::FILTER_TEMPLATE_DEFAULT;
        try {
            $attributeCode = $this->getFilter()->getAttributeModel()->getAttributeCode();
            if ($attributeCode == Filter::ATTRIBUTE_CODE_PRICE && $this->helper->isSliderAllowed()) {
                $template = self::FILTER_TEMPLATE_SLIDER;
            }
        } catch (LocalizedException $e) {
            if (!$this->getFilter() instanceof \Amasty\ShopbyLite\Model\Layer\Filter\Category) {
                $template = '';
            }
        }

        return $template;
    }

    /**
     * @return string
     */
    public function getClearUrl()
    {
        if (!array_key_exists('filterItems', $this->_viewVars) || !is_array($this->_viewVars['filterItems'])) {
            return '';
        }

        $items = $this->_viewVars['filterItems'];

        foreach ($items as $item) {
            /** @var Item $item */
            if ($item->isSelected()) {
                return $item->getRemoveUrl();
            }
        }

        return '';
    }

    /**
     * @return string
     */
    public function getSliderUrlTemplate()
    {
        return $this->urlBuilder->buildUrl($this->filter, 'amshopby_slider_from-amshopby_slider_to');
    }

    /**
     * @param string $data
     * @return string
     */
    public function escapeId($data)
    {
        return str_replace(",", "_", $data);
    }

    /**
     * @return int
     */
    public function getScrollOverflow()
    {
        return $this->helper->getScrollOverflowValue();
    }
}

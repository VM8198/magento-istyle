<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_ShopbyLite
 */


namespace Amasty\ShopbyLite\Plugin\Ajax;

use Magento\Catalog\Model\Category;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\View\Layout\Element;

/**
 * Class Ajax
 */
class Ajax
{
    /**
     * @var \Amasty\ShopbyLite\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Framework\Controller\Result\RawFactory
     */
    protected $resultRawFactory;

    public function __construct(
        \Amasty\ShopbyLite\Helper\Data $helper,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory
    ) {
        $this->helper = $helper;
        $this->resultRawFactory = $resultRawFactory;
    }

    /**
     * @param RequestInterface $request
     *
     * @return bool
     */
    protected function isAjax(RequestInterface $request)
    {
        if (!$request instanceof Http) {
            return false;
        }
        $isAjax = $request->isXmlHttpRequest() && $request->isAjax();
        $isScroll = $request->getParam('is_scroll');
        return $this->helper->isAjaxEnabled() && $isAjax && !$isScroll;
    }

    /**
     * @param \Magento\Framework\View\Result\Page $page
     *
     * @return array
     */
    protected function getAjaxResponseData(\Magento\Framework\View\Result\Page $page)
    {
        $layout = $page->getLayout();

        $products = $layout->getBlock('category.products');
        if (!$products) {
            $products = $layout->getBlock('search.result');
        }

        $productsCount = 0;
        $productList = null;
        if ($products) {
            $productList = $products->getChildBlock('product_list') ?: $products->getChildBlock('search_result_list');
            $productsCount = $productList
                ? $productList->getLoadedProductCollection()->getSize()
                : $products->getResultCount();
        }

        $navigation = $layout->getBlock('catalog.leftnav') ?: $layout->getBlock('catalogsearch.leftnav');
        $navigationHtml = '';
        if ($navigation) {
            $navigationHtml = $navigation->toHtml();
        }

        $jsInit = $layout->getBlock('amasty.shopby.jsinit');

        $categoryProducts = $products ? $products->toHtml() : '';

        $h1 = $layout->getBlock('page.main.title');

        $title = $page->getConfig()->getTitle();

        $breadcrumbs = $layout->getBlock('breadcrumbs');

        $htmlCategoryData = '';
        $children = $layout->getChildNames('category.view.container');
        foreach ($children as $child) {
            $htmlCategoryData .= $layout->renderElement($child);
        }
        $htmlCategoryData = '<div class="category-view">' . $htmlCategoryData . '</div>';

        $shopbyCollapse = $layout->getBlock('catalog.navigation.collapsing');
        $shopbyCollapseHtml = '';
        if ($shopbyCollapse) {
            $shopbyCollapseHtml = $shopbyCollapse->toHtml();
        }

        $swatchesChoose = $layout->getBlock('catalog.navigation.swatches.choose');
        $swatchesChooseHtml = '';
        if ($swatchesChoose) {
            $swatchesChooseHtml = $swatchesChoose->toHtml();
        }

        $currentCategory = $productList && $productList->getLayer()
            ? $productList->getLayer()->getCurrentCategory()
            : false;

        $isDisplayModePage = $currentCategory && $currentCategory->getDisplayMode() == Category::DM_PAGE;

        $responseData = [
            'categoryProducts'=> $categoryProducts . $swatchesChooseHtml,
            'navigation' => $navigationHtml . $shopbyCollapseHtml,
            'breadcrumbs' => $breadcrumbs ? $breadcrumbs->toHtml() : '',
            'h1' => $h1 ? $h1->toHtml() : '',
            'title' => $title->get(),
            'categoryData' => $htmlCategoryData,
            'url' => $this->helper->getCurrentUrl(),
            'productsCount' => $productsCount,
            'js_init' => $jsInit ? $jsInit->toHtml() : '',
            'isDisplayModePage' => $isDisplayModePage,
            'currentCategoryId' => $currentCategory ? $currentCategory->getId() ?: 0 : 0
        ];
        if ($layout->getBlock('category.amshopby.ajax')) {
            $responseData['newClearUrl'] = $layout->getBlock('category.amshopby.ajax')->getClearUrl();
        }

        try {
            $sidebarTag = $layout->getElementProperty('div.sidebar.additional', Element::CONTAINER_OPT_HTML_TAG);
            $sidebarClass = $layout->getElementProperty('div.sidebar.additional', Element::CONTAINER_OPT_HTML_CLASS);
            $sidebarAdditional = $layout->renderNonCachedElement('div.sidebar.additional');
            $responseData['sidebar_additional'] = $sidebarAdditional;
            $responseData['sidebar_additional_alias'] = $sidebarTag . '.' . str_replace(' ', '.', $sidebarClass);
        } catch (\Exception $e) {
            null;//container doesn't exist
        }

        $responseData = $this->removeAjaxParam($responseData);
        $responseData = $this->removeEncodedAjaxParams($responseData);

        return $responseData;
    }

    /**
     * @param array $responseData
     * @return array
     */
    private function removeEncodedAjaxParams(array $responseData)
    {
        $pattern = '@aHR0c(Dov|HM6)[A-Za-z0-9_-]+@u';
        array_walk($responseData, function (&$html) use ($pattern) {
            // 'aHR0cDov' and 'aHR0cHM6' are the beginning of the Base64 code for 'http:/' and 'https:'
            $res = preg_replace_callback($pattern, [$this, 'removeAjaxParamFromEncodedMatch'], $html);
            if ($res !== null) {
                $html = $res;
            }
        });

        return $responseData;
    }

    /**
     * @param array $match
     * @return string
     */
    protected function removeAjaxParamFromEncodedMatch($match)
    {
        $spec64 = '+/=';
        $specUrl = '-_,';

        // @codingStandardsIgnoreLine
        $originalUrl = base64_decode(strtr($match[0], $specUrl, $spec64));
        if ($originalUrl === false) {
            return $match[0];
        }
        $url = $this->removeAjaxParam($originalUrl);
        return ($originalUrl == $url) ? $match[0] : rtrim(strtr(base64_encode($url), $spec64, $specUrl), ',');
    }

    protected function removeAjaxParam($data)
    {
        $data = str_replace([
            '?shopbyAjax=1&amp;',
            '?shopbyAjax=1&',
        ], '?', $data);
        $data = str_replace([
            '?shopbyAjax=1',
            '&amp;shopbyAjax=1',
            '&shopbyAjax=1',
        ], '', $data);

        return $data;
    }

    /**
     * @param array $data
     *
     * @return \Magento\Framework\Controller\Result\Raw
     */
    protected function prepareResponse(array $data)
    {
        $response = $this->resultRawFactory->create();
        $response->setHeader('Content-type', 'text/plain');
        if (isset($data['tags'])) {
            $response->setHeader('X-Magento-Tags', $data['tags']);
            unset($data['tags']);
        }

        $response->setContents(json_encode($data));
        return $response;
    }
}

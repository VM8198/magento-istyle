<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_ShopbyLite
 */


namespace Amasty\ShopbyLite\Plugin\Framework\App;

use Magento\Framework\App\FrontController as NativeFrontController;
use Magento\Framework\App\RequestInterface;

/**
 * Class FrontController
 */
class FrontController
{
    const SHOPBY_EXTRA_PARAM = 'amshopby';

    /**
     * @param NativeFrontController $subject
     * @param RequestInterface $request
     * @return array
     */
    public function beforeDispatch(NativeFrontController $subject, RequestInterface $request)
    {
        $this->parseAmshopbyParams($request);

        return [$request];
    }

    /**
     * @param RequestInterface $request
     * @return $this
     */
    private function parseAmshopbyParams(RequestInterface $request)
    {
        if ($amShopbyParams = $request->getParam(self::SHOPBY_EXTRA_PARAM, [])) {
            foreach ($amShopbyParams as $key => $value) {
                $request->setQueryValue($key, implode(',', $value));
            }
            $request->setQueryValue(self::SHOPBY_EXTRA_PARAM, null);
        }

        return $this;
    }
}

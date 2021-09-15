<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_ShopbyLite
 */


namespace Amasty\ShopbyLite\Model;

use Amasty\ShopbyLite\Api\Data\FromToFilterInterface;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Framework\App\RequestInterface;
use Amasty\ShopbyLite\Model\Source\Filter;

/**
 * Class Request
 */
class Request extends \Magento\Framework\DataObject
{
    /**
     * @var RequestInterface
     */
    private $request;

    public function __construct(
        RequestInterface $request,
        array $data = []
    ) {
        parent::__construct($data);
        $this->request = $request;
    }

    /**
     * @param AbstractFilter $filter
     * @return mixed|string
     */
    public function getFilterParam(AbstractFilter $filter)
    {
        $param = $this->getParams($filter);

        if ($filter instanceof FromToFilterInterface) {
            //filter with param "0.0-100" doesn't work. Should use "-100" instead. Fix the slider issue.
            $prefixesToRemove = ['0-', '0.-', '0,-', '0.0-', '0,0-', '0.00-', '0,00-'];
            foreach ($prefixesToRemove as $prefix) {
                if (substr($param, 0, strlen($prefix)) == $prefix) {
                    $param = substr($param, strlen($prefix) - 1);
                }
            }
        }

        return $param;
    }

    /**
     * @param $filter
     * @return string
     */
    private function getParams($filter)
    {
        if ($filter->getRequestVar() == Filter::ATTRIBUTE_CODE_PRICE) {
            $param = $this->getParam(Filter::AM_BASE_CODE_PRICE) ?: $this->getParam($filter->getRequestVar());
        } else {
            $param = $this->getParam($filter->getRequestVar());
        }

        return $param;
    }

    /**
     * @param $requestVar
     * @return mixed
     */
    public function getParam($requestVar)
    {
        $bulkParams = $this->getBulkParams();
        if (array_key_exists($requestVar, $bulkParams)) {
            $data = implode(',', $bulkParams[$requestVar]);
        } else {
            $data = $this->request->getParam($requestVar);
        }

        return $data;
    }

    /**
     * @return array
     */
    public function getRequestParams()
    {
        $result = $this->getBulkParams();

        if (!$result) {
            foreach ($this->request->getParams() as $key => $param) {
                if ($param && $key !== 'id') {
                    $result[$key][] = $param;
                }
            }
        }

        return $result;
    }

    /**
     * @return array
     */
    private function getBulkParams()
    {
        return $this->request->getParam('amshopby', []);
    }
}

<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_ShopbyLite
 */


namespace Amasty\ShopbyLite\Plugin\Framework\Search\Adapter\Mysql\Aggregation\Builder;

use Magento\Framework\App\ScopeResolverInterface;
use Magento\Catalog\Model\Layer\Filter\Dynamic\AlgorithmFactory;
use Magento\Framework\Search\Request\Aggregation\DynamicBucket;

/**
 * Class Dynamic
 */
class Dynamic
{
    /**
     * @var Resource
     */
    private $resource;

    /**
     * @var ScopeResolverInterface
     */
    private $scopeResolver;

    /**
     * @var \Magento\Eav\Model\Config
     */
    private $eavConfig;

    /**
     * @var \Magento\Framework\Search\Dynamic\EntityStorageFactory
     */
    private $entityStorageFactory;

    /**
     * @var \Magento\Framework\Search\Dynamic\DataProviderInterface
     */
    private $priceDataProvider;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var array
     */
    private $data = [];

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Framework\App\ScopeResolverInterface $scopeResolver,
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\Framework\Search\Dynamic\DataProviderInterface $priceDataProvider,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Search\Dynamic\EntityStorageFactory $entityStorageFactory
    ) {
        $this->resource = $resource;
        $this->scopeResolver = $scopeResolver;
        $this->eavConfig = $eavConfig;
        $this->priceDataProvider = $priceDataProvider;
        $this->entityStorageFactory = $entityStorageFactory;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param \Magento\Framework\Search\Adapter\Mysql\Aggregation\Builder\Dynamic $subject
     * @param \Closure $closure
     * @param \Magento\Framework\Search\Adapter\Mysql\Aggregation\DataProviderInterface $dataProvider
     * @param array $dimensions
     * @param \Magento\Framework\Search\Request\BucketInterface $bucket
     * @param \Magento\Framework\DB\Ddl\Table $entityIdsTable
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormatParameter)
     */
    public function aroundBuild(
        \Magento\Framework\Search\Adapter\Mysql\Aggregation\Builder\Dynamic $subject,
        \Closure $closure,
        \Magento\Framework\Search\Adapter\Mysql\Aggregation\DataProviderInterface $dataProvider,
        array $dimensions,
        \Magento\Framework\Search\Request\BucketInterface $bucket,
        \Magento\Framework\DB\Ddl\Table $entityIdsTable
    ) {
        $dataKey = $bucket->getName() . $bucket->getField() . $bucket->getType();
        if (!isset($this->data[$dataKey])) {
            $attribute = $this->eavConfig->getAttribute(\Magento\Catalog\Model\Product::ENTITY, $bucket->getField());

            if ($attribute->getBackendType() == 'decimal') {
                $rangeCalculationPath = $this->scopeConfig->getValue(
                    AlgorithmFactory::XML_PATH_RANGE_CALCULATION,
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                );
                if ($attribute->getAttributeCode() == 'price') {
                    if ($rangeCalculationPath == AlgorithmFactory::RANGE_CALCULATION_IMPROVED) {
                        $bucket = new DynamicBucket($bucket->getName(), $bucket->getField(), 'auto');
                    }
                    $minMaxData['data'] = $this->priceDataProvider->getAggregations(
                        $this->entityStorageFactory->create($entityIdsTable)
                    );
                    $minMaxData['data']['value'] = 'data';
                } else {
                    $currentScope = $dimensions['scope']->getValue();
                    $currentScopeId = $this->scopeResolver->getScope($currentScope)->getId();
                    $select = $this->resource->getConnection()->select();
                    $table = $this->resource->getTableName(
                        'catalog_product_index_eav_decimal'
                    );
                    $select->from(
                        ['main_table' => $table],
                        [
                            'value' => new \Zend_Db_Expr("'data'"),
                            'min' => 'min(main_table.value)',
                            'max' => 'max(main_table.value)',
                            'count' => 'count(*)'
                        ]
                    )
                        ->where('main_table.attribute_id = ?', $attribute->getAttributeId())
                        ->where('main_table.store_id = ? ', $currentScopeId);
                    $select->joinInner(
                        ['entities' => $entityIdsTable->getName()],
                        'main_table.entity_id  = entities.entity_id',
                        []
                    );

                    $minMaxData = $dataProvider->execute($select);
                }

                $defaultData = $closure($dataProvider, $dimensions, $bucket, $entityIdsTable);

                return array_replace($minMaxData, $defaultData);
            }

            $this->data[$dataKey] = $closure($dataProvider, $dimensions, $bucket, $entityIdsTable);
        }

        return $this->data[$dataKey];
    }
}

<?php

include_once dirname(__FILE__) . '/../Helper/Product.php';

use Magento\Catalog\Api\Data\CategoryInterfaceFactory;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Eav\Model\Config;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();

$websiteRepository = $objectManager->get(WebsiteRepositoryInterface::class);
$baseWebsite = $websiteRepository->get('base');

$storeManager = $objectManager->get(StoreManagerInterface::class);
$productRepository = $objectManager->get(ProductRepositoryInterface::class);
$categoryFactory = $objectManager->get(CategoryInterfaceFactory::class);

// Defaults
$defaultAttributeSetId = $objectManager->get(Config::class)->getEntityType(Product::ENTITY)->getDefaultAttributeSetId();
$defaultStoreId = $storeManager->getDefaultStoreView()->getId();
$defaultWebsiteIds = [$baseWebsite->getId()];

$products = $categoryFactory->create();
$products->isObjectNew(true);
$products
    ->setName('Products')
    ->setParentId(2)
    ->setPath('1/2/6')
    ->setLevel(2)
    ->setAvailableSortBy('name')
    ->setDefaultSortBy('name')
    ->setIsActive(true)
    ->setIsAnchor(true)
    ->setPosition(6)
    ->save();

$subscriptions = $categoryFactory->create();
$subscriptions->isObjectNew(true);
$subscriptions
    ->setName('Subscriptions')
    ->setParentId(2)
    ->setPath('1/2/7')
    ->setLevel(2)
    ->setAvailableSortBy('name')
    ->setDefaultSortBy('name')
    ->setIsActive(true)
    ->setIsAnchor(true)
    ->setPosition(7)
    ->save();

$trialSubscriptions = $categoryFactory->create();
$trialSubscriptions->isObjectNew(true);
$trialSubscriptions
    ->setName('Trial')
    ->setParentId(2)
    ->setPath('1/2/8')
    ->setLevel(2)
    ->setAvailableSortBy('name')
    ->setDefaultSortBy('name')
    ->setIsActive(true)
    ->setIsAnchor(true)
    ->setPosition(8)
    ->save();

$complex = $categoryFactory->create();
$complex->isObjectNew(true);
$complex
    ->setName('Complex')
    ->setParentId(2)
    ->setPath('1/2/9')
    ->setLevel(2)
    ->setAvailableSortBy('name')
    ->setDefaultSortBy('name')
    ->setIsActive(true)
    ->setIsAnchor(true)
    ->setPosition(9)
    ->save();

$productInterfaceFactory = $objectManager->get(ProductInterfaceFactory::class);

$product = $productInterfaceFactory->create();
$product->setTypeId(Type::TYPE_SIMPLE)
    ->setAttributeSetId($defaultAttributeSetId)
    ->setStoreId($defaultStoreId)
    ->setWebsiteIds($defaultWebsiteIds)
    ->setName('Simple Product')
    ->setSku('simple-product')
    ->setPrice(10)
    ->setTaxClassId(2)
    ->setStockData(['use_config_manage_stock' => 0])
    ->setVisibility(Visibility::VISIBILITY_BOTH)
    ->setStatus(Status::STATUS_ENABLED)
    ->setCategoryIds([$products->getId()])
    ->save();

$simpleProduct = $productRepository->save($product);

$product = $productInterfaceFactory->create();
$product->setTypeId(Type::TYPE_VIRTUAL)
    ->setAttributeSetId($defaultAttributeSetId)
    ->setStoreId($defaultStoreId)
    ->setWebsiteIds($defaultWebsiteIds)
    ->setName('Virtual Product')
    ->setSku('virtual-product')
    ->setPrice(10)
    ->setTaxClassId(2)
    ->setStockData(['use_config_manage_stock' => 0])
    ->setVisibility(Visibility::VISIBILITY_BOTH)
    ->setStatus(Status::STATUS_ENABLED)
    ->setCategoryIds([$products->getId()])
    ->save();

$virtualProduct = $productRepository->save($product);

$product = $productInterfaceFactory->create();
$product->setTypeId(Type::TYPE_VIRTUAL)
    ->setAttributeSetId($defaultAttributeSetId)
    ->setStoreId($defaultStoreId)
    ->setWebsiteIds($defaultWebsiteIds)
    ->setName('Free Product')
    ->setSku('free-product')
    ->setPrice(0)
    ->setTaxClassId(2)
    ->setStockData(['use_config_manage_stock' => 0])
    ->setVisibility(Visibility::VISIBILITY_BOTH)
    ->setStatus(Status::STATUS_ENABLED)
    ->setCategoryIds([$products->getId()])
    ->save();

$freeProduct = $productRepository->save($product);

$product = $productInterfaceFactory->create();
$product->setTypeId(Type::TYPE_SIMPLE)
    ->setAttributeSetId($defaultAttributeSetId)
    ->setStoreId($defaultStoreId)
    ->setWebsiteIds($defaultWebsiteIds)
    ->setName('Simple Monthly Subscription')
    ->setSku('simple-monthly-subscription-product')
    ->setPrice(10)
    ->setTaxClassId(2)
    ->setStockData(['use_config_manage_stock' => 0])
    ->setVisibility(Visibility::VISIBILITY_BOTH)
    ->setStatus(Status::STATUS_ENABLED)
    ->setCategoryIds([$subscriptions->getId()])
    ->save();

$simpleMonthlySubscription = $productRepository->save($product);

setCustomAttribute($simpleMonthlySubscription, "stripe_sub_enabled", true);
setCustomAttribute($simpleMonthlySubscription, "stripe_sub_interval", "month");
setCustomAttribute($simpleMonthlySubscription, "stripe_sub_interval_count", 1);

$product = $productInterfaceFactory->create();
$product->setTypeId(Type::TYPE_SIMPLE)
    ->setAttributeSetId($defaultAttributeSetId)
    ->setStoreId($defaultStoreId)
    ->setWebsiteIds($defaultWebsiteIds)
    ->setName('Simple Quarterly Subscription')
    ->setSku('simple-quarterly-subscription-product')
    ->setPrice(10)
    ->setTaxClassId(2)
    ->setStockData(['use_config_manage_stock' => 0])
    ->setVisibility(Visibility::VISIBILITY_BOTH)
    ->setStatus(Status::STATUS_ENABLED)
    ->setCategoryIds([$subscriptions->getId()])
    ->save();

$simpleQuarterlySubscription = $productRepository->save($product);

setCustomAttribute($simpleQuarterlySubscription, "stripe_sub_enabled", true);
setCustomAttribute($simpleQuarterlySubscription, "stripe_sub_interval", "month");
setCustomAttribute($simpleQuarterlySubscription, "stripe_sub_interval_count", 3);

$product = $productInterfaceFactory->create();
$product->setTypeId(Type::TYPE_SIMPLE)
    ->setAttributeSetId($defaultAttributeSetId)
    ->setStoreId($defaultStoreId)
    ->setWebsiteIds($defaultWebsiteIds)
    ->setName('Simple Monthly Subscription + Initial Fee')
    ->setSku('simple-monthly-subscription-initial-fee-product')
    ->setPrice(10)
    ->setTaxClassId(2)
    ->setStockData(['use_config_manage_stock' => 0])
    ->setVisibility(Visibility::VISIBILITY_BOTH)
    ->setStatus(Status::STATUS_ENABLED)
    ->setCategoryIds([$subscriptions->getId()])
    ->save();

$simpleMonthlySubscriptionInitialFee = $productRepository->save($product);

setCustomAttribute($simpleMonthlySubscriptionInitialFee, "stripe_sub_enabled", true);
setCustomAttribute($simpleMonthlySubscriptionInitialFee, "stripe_sub_interval", "month");
setCustomAttribute($simpleMonthlySubscriptionInitialFee, "stripe_sub_interval_count", 1);
setCustomAttribute($simpleMonthlySubscriptionInitialFee, "stripe_sub_initial_fee", 3);

$product = $productInterfaceFactory->create();
$product->setTypeId(Type::TYPE_SIMPLE)
    ->setAttributeSetId($defaultAttributeSetId)
    ->setStoreId($defaultStoreId)
    ->setWebsiteIds($defaultWebsiteIds)
    ->setName('Simple Trial Monthly Subscription')
    ->setSku('simple-trial-monthly-subscription-product')
    ->setPrice(10)
    ->setTaxClassId(2)
    ->setStockData(['use_config_manage_stock' => 0])
    ->setVisibility(Visibility::VISIBILITY_BOTH)
    ->setStatus(Status::STATUS_ENABLED)
    ->setCategoryIds([$trialSubscriptions->getId()])
    ->save();

$simpleTrialMonthlySubscription = $productRepository->save($product);

setCustomAttribute($simpleTrialMonthlySubscription, "stripe_sub_enabled", true);
setCustomAttribute($simpleTrialMonthlySubscription, "stripe_sub_interval", "month");
setCustomAttribute($simpleTrialMonthlySubscription, "stripe_sub_interval_count", 1);
setCustomAttribute($simpleTrialMonthlySubscription, "stripe_sub_trial", 14);

$product = $productInterfaceFactory->create();
$product->setTypeId(Type::TYPE_SIMPLE)
    ->setAttributeSetId($defaultAttributeSetId)
    ->setStoreId($defaultStoreId)
    ->setWebsiteIds($defaultWebsiteIds)
    ->setName('Simple Trial Monthly Subscription + Initial Fee')
    ->setSku('simple-trial-monthly-subscription-initial-fee')
    ->setPrice(10)
    ->setTaxClassId(2)
    ->setStockData(['use_config_manage_stock' => 0])
    ->setVisibility(Visibility::VISIBILITY_BOTH)
    ->setStatus(Status::STATUS_ENABLED)
    ->setCategoryIds([$trialSubscriptions->getId()])
    ->save();

$simpleTrialMonthlySubscriptionInitialFee = $productRepository->save($product);

setCustomAttribute($simpleTrialMonthlySubscriptionInitialFee, "stripe_sub_enabled", true);
setCustomAttribute($simpleTrialMonthlySubscriptionInitialFee, "stripe_sub_interval", "month");
setCustomAttribute($simpleTrialMonthlySubscriptionInitialFee, "stripe_sub_interval_count", 1);
setCustomAttribute($simpleTrialMonthlySubscriptionInitialFee, "stripe_sub_trial", 14);
setCustomAttribute($simpleTrialMonthlySubscriptionInitialFee, "stripe_sub_initial_fee", 3);

$product = $productInterfaceFactory->create();
$product->setTypeId(Type::TYPE_VIRTUAL)
    ->setAttributeSetId($defaultAttributeSetId)
    ->setStoreId($defaultStoreId)
    ->setWebsiteIds($defaultWebsiteIds)
    ->setName('Virtual Monthly Subscription')
    ->setSku('virtual-monthly-subscription-product')
    ->setPrice(10)
    ->setTaxClassId(2)
    ->setStockData(['use_config_manage_stock' => 0])
    ->setVisibility(Visibility::VISIBILITY_BOTH)
    ->setStatus(Status::STATUS_ENABLED)
    ->setCategoryIds([$subscriptions->getId()])
    ->save();

$virtualMonthlySubscription = $productRepository->save($product);

setCustomAttribute($virtualMonthlySubscription, "stripe_sub_enabled", true);
setCustomAttribute($virtualMonthlySubscription, "stripe_sub_interval", "month");
setCustomAttribute($virtualMonthlySubscription, "stripe_sub_interval_count", 1);

$product = $productInterfaceFactory->create();
$product->setTypeId(Type::TYPE_VIRTUAL)
    ->setAttributeSetId($defaultAttributeSetId)
    ->setStoreId($defaultStoreId)
    ->setWebsiteIds($defaultWebsiteIds)
    ->setName('Virtual Trial Monthly Subscription')
    ->setSku('virtual-trial-monthly-subscription-product')
    ->setPrice(10)
    ->setTaxClassId(2)
    ->setStockData(['use_config_manage_stock' => 0])
    ->setVisibility(Visibility::VISIBILITY_BOTH)
    ->setStatus(Status::STATUS_ENABLED)
    ->setCategoryIds([$trialSubscriptions->getId()])
    ->save();

$virtualTrialMonthlySubscription = $productRepository->save($product);

setCustomAttribute($virtualTrialMonthlySubscription, "stripe_sub_enabled", true);
setCustomAttribute($virtualTrialMonthlySubscription, "stripe_sub_interval", "month");
setCustomAttribute($virtualTrialMonthlySubscription, "stripe_sub_interval_count", 1);
setCustomAttribute($virtualTrialMonthlySubscription, "stripe_sub_trial", 14);

$product = $productInterfaceFactory->create();
$product->setTypeId(Type::TYPE_VIRTUAL)
    ->setAttributeSetId($defaultAttributeSetId)
    ->setStoreId($defaultStoreId)
    ->setWebsiteIds($defaultWebsiteIds)
    ->setName('Virtual Trial Monthly Subscription + Initial Fee')
    ->setSku('virtual-monthly-subscription-initial-fee-product')
    ->setPrice(10)
    ->setTaxClassId(2)
    ->setStockData(['use_config_manage_stock' => 0])
    ->setVisibility(Visibility::VISIBILITY_BOTH)
    ->setStatus(Status::STATUS_ENABLED)
    ->setCategoryIds([$trialSubscriptions->getId()])
    ->save();

$virtualTrialMonthlySubscriptionInitialFee = $productRepository->save($product);

setCustomAttribute($virtualTrialMonthlySubscriptionInitialFee, "stripe_sub_enabled", true);
setCustomAttribute($virtualTrialMonthlySubscriptionInitialFee, "stripe_sub_interval", "month");
setCustomAttribute($virtualTrialMonthlySubscriptionInitialFee, "stripe_sub_interval_count", 1);
setCustomAttribute($virtualTrialMonthlySubscriptionInitialFee, "stripe_sub_trial", 14);
setCustomAttribute($virtualTrialMonthlySubscriptionInitialFee, "stripe_sub_initial_fee", 14);


// ----------------------------------------------------------------------------

$bundleProduct = $objectManager->create(\Magento\Catalog\Api\Data\ProductInterface::class);
$bundleProduct
        ->setAttributeSetId($defaultAttributeSetId)
        ->setStoreId($defaultStoreId)
        ->setWebsiteIds($defaultWebsiteIds)
        ->setTypeId('bundle')
        ->setSkuType(0) // 0 - dynamic, 1 - fixed
        ->setSku('bundle-dynamic')
        ->setName('Bundle Dynamic')
        ->setWeightType(0) // 0 - dynamic, 1 - fixed
//        ->setWeight(4.0000)
        ->setShipmentType(0) // 0 - together, 1 - separately
        ->setStatus(1) // 1 - enabled, 2 - disabled
        ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
        ->setPriceType(0) // 0 - dynamic, 1 - fixed
//        ->setPrice(20)
        ->setPriceView(0) // 0 - price range, 1 - as low as
        ->setSpecialPrice(50) // percentage of original price
        ->setTaxClassId(2) // 0 - none, 1 - default, 2 - taxable, 4 - shipping
        ->setStockData(['use_config_manage_stock' => 0])
        ->setCategoryIds([$complex->getId()]);

// Set bundle product items
$bundleProduct->setBundleOptionsData(
    [
        [
            'title' => 'Regular Product',
            'default_title' => 'Regular Product',
            'type' => 'select',
            'required' => 0,
            'delete' => '',
        ],
        [
            'title' => 'Subscription',
            'default_title' => 'Subscription',
            'type' => 'select',
            'required' => 1,
            'delete' => '',
        ]
    ]
)->setBundleSelectionsData(
    [
        [
            ['product_id' => $simpleProduct->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => ''],
            ['product_id' => $virtualProduct->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => ''],
            ['product_id' => $freeProduct->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => ''],
        ],
        [
            ['product_id' => $simpleMonthlySubscription->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => ''],
            ['product_id' => $simpleQuarterlySubscription->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => ''],
            ['product_id' => $simpleMonthlySubscriptionInitialFee->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => ''],
            ['product_id' => $simpleTrialMonthlySubscription->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => ''],
            ['product_id' => $simpleTrialMonthlySubscriptionInitialFee->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => ''],
            ['product_id' => $virtualMonthlySubscription->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => ''],
            ['product_id' => $virtualTrialMonthlySubscription->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => ''],
            ['product_id' => $virtualTrialMonthlySubscriptionInitialFee->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => ''],
        ],
    ]
);
setBundleProductItems($bundleProduct);


// ----------------------------------------------------------------------------

$bundleProduct = $objectManager->create(\Magento\Catalog\Api\Data\ProductInterface::class);
$bundleProduct
        ->setAttributeSetId($defaultAttributeSetId)
        ->setStoreId($defaultStoreId)
        ->setWebsiteIds($defaultWebsiteIds)
        ->setTypeId('bundle')
        ->setSkuType(0) // 0 - dynamic, 1 - fixed
        ->setSku('bundle-fixed')
        ->setName('Bundle Fixed')
        ->setWeightType(0) // 0 - dynamic, 1 - fixed
//        ->setWeight(4.0000)
        ->setShipmentType(0) // 0 - together, 1 - separately
        ->setStatus(1) // 1 - enabled, 2 - disabled
        ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
        ->setPriceType(1) // 0 - dynamic, 1 - fixed
//        ->setPrice(20)
        ->setPriceView(0) // 0 - price range, 1 - as low as
        ->setSpecialPrice(50) // percentage of original price
        ->setTaxClassId(2) // 0 - none, 1 - default, 2 - taxable, 4 - shipping
        ->setStockData(['use_config_manage_stock' => 0])
        ->setCategoryIds([$complex->getId()]);

// Set bundle product items
$bundleProduct->setBundleOptionsData(
    [
        [
            'title' => 'Regular Product',
            'default_title' => 'Regular Product',
            'type' => 'select',
            'required' => 0,
            'delete' => '',
        ],
        [
            'title' => 'Subscription',
            'default_title' => 'Subscription',
            'type' => 'select',
            'required' => 1,
            'delete' => '',
        ]
    ]
)->setBundleSelectionsData(
    [
        [
            ['product_id' => $simpleProduct->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => '', 'selection_price_type' => 0, 'price' => 20],
            ['product_id' => $virtualProduct->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => '', 'selection_price_type' => 0, 'price' => 20],
            ['product_id' => $freeProduct->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => '', 'selection_price_type' => 0, 'price' => 0],
        ],
        [
            ['product_id' => $simpleMonthlySubscription->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => '', 'selection_price_type' => 0, 'price' => 20],
            ['product_id' => $simpleQuarterlySubscription->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => '', 'selection_price_type' => 0, 'price' => 20],
            ['product_id' => $simpleMonthlySubscriptionInitialFee->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => '', 'selection_price_type' => 0, 'price' => 20],
            ['product_id' => $simpleTrialMonthlySubscription->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => '', 'selection_price_type' => 0, 'price' => 20],
            ['product_id' => $simpleTrialMonthlySubscriptionInitialFee->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => '', 'selection_price_type' => 0, 'price' => 20],
            ['product_id' => $virtualMonthlySubscription->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => '', 'selection_price_type' => 0, 'price' => 20],
            ['product_id' => $virtualTrialMonthlySubscription->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => '', 'selection_price_type' => 0, 'price' => 20],
            ['product_id' => $virtualTrialMonthlySubscriptionInitialFee->getId(), 'selection_qty' => 1, 'selection_can_change_qty' => 1, 'delete' => '', 'selection_price_type' => 0, 'price' => 20],
        ],
    ]
);
setBundleProductItems($bundleProduct);

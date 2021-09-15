<?php

namespace StripeIntegration\Payments\Test\Integration\Helper;

class Quote
{
    protected $objectManager = null;
    protected $quote = null;
    protected $order = null;
    protected $store = null;
    protected $quoteRepository = null;
    protected $productRepository = null;

    public function __construct()
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->quoteRepository = $this->objectManager->create(\Magento\Quote\Api\CartRepositoryInterface::class);
        $this->productRepository = $this->objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
        $this->checkoutSession = $this->objectManager->get(\Magento\Checkout\Model\Session::class);
        $this->cartManagement = $this->objectManager->get(\Magento\Quote\Api\CartManagementInterface::class);
        $this->orderFactory = $this->objectManager->get(\Magento\Sales\Model\OrderFactory::class);
        $this->objectFactory = $this->objectManager->get(\Magento\Framework\DataObject\Factory::class);
        $this->stripeConfig = $this->objectManager->get(\StripeIntegration\Payments\Model\Config::class);

        $this->quoteManagement = $this->objectManager->get(\StripeIntegration\Payments\Test\Integration\Helper\QuoteManagement::class);

        \Magento\TestFramework\Helper\Bootstrap::getInstance()->loadArea(\Magento\Framework\App\Area::AREA_FRONTEND);

        $this->store = $this->objectManager->get(\Magento\Store\Model\StoreManagerInterface::class)->getStore();
    }

    public function create()
    {
        $this->quote = $this->objectManager
            ->create(\Magento\Quote\Model\Quote::class)
            ->setStoreId($this->store->getId())
            ->setWebsiteId($this->store->getWebsiteId())
            ->setInventoryProcessed(false);

        return $this;
    }

    public function save()
    {
        $this->quote->setTotalsCollectedFlag(false)->collectTotals();
        $this->quoteRepository->save($this->quote);

        return $this;
    }

    public function setCustomer($identifier)
    {
        switch ($identifier) {
            case 'Guest':
                $this->quote->setCustomerIsGuest(true)
                    ->setCheckoutMethod(\Magento\Quote\Api\CartManagementInterface::METHOD_GUEST)
                    ->setCustomerClassId(3);
                break;

            case 'LoggedIn':
                break;

            default:
                # code...
                break;
        }

        return $this;
    }

    public function addProduct($sku, $qty, $bundleQtys = null)
    {
        $product = $this->productRepository->get($sku);

        if ($product->getTypeId() == "bundle" && !empty($bundleQtys))
        {
            $params = [
                'product' => $product->getId(),
                'bundle_option' => [],
                'bundle_option_qty' => [],
                'qty' => $qty
            ];

            $selections = $this->getBundleSelections($product);

            foreach ($bundleQtys as $sku => $skuQty)
            {
                if (isset($selections[$sku]))
                {
                    $optionId = $selections[$sku]['option_id'];
                    $selectionId = $selections[$sku]['selection_id'];

                    $params['bundle_option'][$optionId] = $selectionId;
                    $params['bundle_option_qty'][$optionId] = $skuQty;
                }
            }

            $request = $this->objectFactory->create($params);
            $result = $this->quote->addProduct($product, $request);
            if (is_string($result))
                throw new \Exception($result);
        }
        else
        {
            $this->quote->addProduct($product, $qty);
        }

        return $this;
    }

    public function setCart($identifier)
    {
        $this->quote->removeAllItems();

        switch ($identifier)
        {
            case 'Normal':
                $this->addProduct('simple-product', 2);
                $this->addProduct('virtual-product', 2);
                break;

            case 'Subscription':
                $this->addProduct('simple-monthly-subscription-initial-fee-product', 1);
                break;

            case 'Subscriptions':
                $this->addProduct('virtual-monthly-subscription-product', 1);
                $this->addProduct('simple-monthly-subscription-initial-fee-product', 1);
                break;

            case 'Mixed':
                $this->addProduct('simple-product', 2);
                $this->addProduct('simple-monthly-subscription-initial-fee-product', 2);
                break;

            case 'Trial':
                $this->addProduct('virtual-trial-monthly-subscription-product', 1);
                $this->addProduct('simple-trial-monthly-subscription-product', 1);
                break;

            case 'MixedTrial':
                $this->addProduct('simple-product', 1);
                $this->addProduct('simple-trial-monthly-subscription-product', 1);
                break;

            case 'ZeroAmount':
                $this->addProduct('free-product', 1);
                $this->addProduct('virtual-trial-monthly-subscription-product', 1);
                break;

            case 'DynamicBundleMixedTrial':
                $this->addProduct('bundle-dynamic', 2, ["simple-product" => 2, "simple-trial-monthly-subscription-product" => 2]);
                $this->addProduct('simple-product', 2);
                break;

            case 'FixedBundleMixedTrial':
                $this->addProduct('bundle-fixed', 2, ["simple-product" => 2, "simple-trial-monthly-subscription-product" => 2]);
                $this->addProduct('simple-product', 2);
                break;

            default:
                break;
        }

        return $this;
    }

    public function getBundleSelections($product)
    {
        $selectionCollection = $product->getTypeInstance()
            ->getSelectionsCollection(
                $product->getTypeInstance()->getOptionsIds($product),
                $product
            );

        $bundleSelections = [];
        foreach ($selectionCollection as $selection)
            $bundleSelections[$selection->getSku()] = $selection->getData();

        return $bundleSelections;
    }

    public function setShippingAddress($identifier)
    {
        $address = $this->getAddress($identifier);

        if ($address)
        {
            $this->quote->getShippingAddress()->addData($address);
        }

        return $this->save();
    }

    public function setShippingMethod($identifier)
    {
        $shippingAddress = $this->quote->getShippingAddress();

        if ($shippingAddress)
        {
            $shippingAddress->setCollectShippingRates(true)->collectShippingRates()->save();

            switch ($identifier) {
                case 'FlatRate':
                    $shippingAddress->setShippingMethod('flatrate_flatrate');
                    break;

                default:
                    # code...
                    break;
            }

            // foreach ($this->quote->getAllItems() as $quoteItem)
            // {
            //     $shippingAddress->requestShippingRates($quoteItem);
            // }
        }

        return $this->save();
    }

    public function setBillingAddress($identifier)
    {
        $address = $this->getAddress($identifier);

        if ($address)
        {
            $this->quote->getBillingAddress()->addData($address);
            $this->quote->setCustomerEmail($address["email"]);
        }

        return $this->save();
    }

    public function setPaymentMethod($identifier)
    {
        $data = null;

        switch ($identifier)
        {
            case 'SuccessCard':
                $data = [
                    'method' => 'stripe_payments',
                    'additional_data' => [
                        "cc_save" => false,
                        "cc_stripejs_token" => "pm_card_visa:visa:4242"
                    ]
                ];
                break;

            case 'InsufficientFundsCard':
                $data = [
                    'method' => 'stripe_payments',
                    'additional_data' => [
                        "cc_save" => false,
                        "cc_stripejs_token" => "pm_card_chargeDeclinedInsufficientFunds:visa:4242"
                    ]
                ];
                break;

            case 'ElevatedRiskCard':
                $data = [
                    'method' => 'stripe_payments',
                    'additional_data' => [
                        "cc_save" => false,
                        "cc_stripejs_token" => "pm_card_riskLevelElevated:visa:4242"
                    ]
                ];
                break;

            case 'StripeCheckoutCard':
                $data = [
                    'method' => 'stripe_payments_checkout_card'
                ];
                break;

            case 'MexicoInstallmentsCard':
                $paymentMethod = $this->createPaymentMethodFrom('4000004840000008');
                $data = [
                    'method' => 'stripe_payments',
                    'additional_data' => [
                        "cc_save" => false,
                        "cc_stripejs_token" => "{$paymentMethod->id}:visa:4242"
                    ]
                ];
                break;
            default:
                break;

        }

        if ($data)
            $this->quote->getPayment()->importData($data);

        return $this->save();
    }

    public function createPaymentMethodFrom($cardNumber)
    {
        return $this->stripeConfig->getStripeClient()->paymentMethods->create([
          'type' => 'card',
          'card' => [
            'number' => $cardNumber,
            'exp_month' => 8,
            'exp_year' => 2025,
            'cvc' => '314',
          ],
        ]);
    }

    public function placeOrder()
    {
        $this->quote->collectTotals()->save();
        return $this->cartManagement->submit($this->quote);
        // $orderId = $this->quoteManagement->placeOrder($this->quote->getId());
        // return $this->orderFactory->create()->load($orderId);
    }

    public function mockOrder()
    {
        $order = $this->quoteManagement->mockOrder($this->quote);
        $this->checkoutSession->replaceQuote($this->quote);
        return $order;
    }

    // ---------------------

    public function getQuote()
    {
        return $this->quote;
    }

    public function getQuoteItem($sku)
    {
        foreach ($this->quote->getAllItems() as $quoteItem)
        {
            if ($quoteItem->getSku() == $sku)
                return $quoteItem;
        }

        return null;
    }

    // --------------------

    public function getAddress($identifier)
    {
        switch ($identifier)
        {
            case 'NewYork':
                return [
                    'telephone' => "917-535-4022",
                    'postcode' => "10013",
                    'country_id' => 'US',
                    'region_id' => 43, // 43 = 8.375%
                    'city' => 'New York',
                    'street' => ['1255 Duncan Avenue'],
                    'lastname' => 'Jerry',
                    'firstname' => 'Flint',
                    'email' => 'flint@example.com',
                ];
            case 'California':
                return [
                    'telephone' => "626-945-7637",
                    'postcode' => "91752",
                    'country_id' => 'US',
                    'region_id' => 12, // 12 = 8.25%
                    'city' => 'Mira Loma',
                    'street' => ['2974 Providence Lane'],
                    'lastname' => 'Strother',
                    'firstname' => 'Joyce',
                    'email' => 'joyce@example.com',
                ];
            case 'Mexico':
                return [
                    'telephone' => "771.715-2115",
                    'postcode' => "42000",
                    'country_id' => 'MX',
                    'region_id' => 597, // Puebla
                    'city' => 'HIDALGO',
                    'street' => ['GUERRERO NO. 521', 'PACHUCA DE SOTO CENTRO'],
                    'lastname' => 'Hopi',
                    'firstname' => 'Huyana',
                    'email' => 'huyana@example.com',
                ];
            case 'Michigan':
                return [
                    'telephone' => "701-270-0720",
                    'postcode' => "58259",
                    'country_id' => 'US',
                    'region_id' => 33, // 33 = 8.25%
                    'city' => 'Michigan',
                    'street' => ['3510 Catherine Drive'],
                    'lastname' => 'Cook',
                    'firstname' => 'Crystal',
                    'email' => 'crystal@example.com',
                ];
            default:
                return null;
        }
    }
}

<?php

namespace StripeIntegration\Payments\Test\Integration\Unit\Model;

use PHPUnit\Framework\Constraint\StringContains;

class PaymentIntentTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
    }

    /**
     * @ticket MAGENTO-65
     *
     * @magentoConfigFixture current_store payment/stripe_payments/active 1
     * @magentoConfigFixture current_store payment/stripe_payments_basic/stripe_mode test
     * @magentoConfigFixture current_store payment/stripe_payments_basic/stripe_test_pk pk_test_51Ig7MJHLyfDWKHBqqOpnyTkavM0LlpuH1QnrM1IsRGe26qwwo1uhQZbHyrnaiJuWpiIEkoFHgzgZoeLlfLOXp4ef00ApmFEugB
     * @magentoConfigFixture current_store payment/stripe_payments_basic/stripe_test_sk sk_test_51Ig7MJHLyfDWKHBqRZ6h9gRk1C738LWP1ljHVAyWsON7CIennpQV25sHvISdpbfHBqQWCNBTivTsKiIFjAPJhyB500ytiSiSSF
     * @magentoConfigFixture current_store payment/stripe_payments/checkout_mode 0
     *
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Taxes.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Addresses.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Products.php
     */
    public function testPreloadFromCache()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("Normal")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("InsufficientFundsCard");

        // $this->expectException(\Magento\Framework\Exception\CouldNotSaveException::class);
        // $this->expectExceptionMessage('Your card has insufficient funds.');
        $this->expectException(\Error::class);
        $order = $this->quote->placeOrder();

        $this->expectException(\Error::class);
        $order = $this->quote->placeOrder();

        $this->expectException(\Error::class);
        $order = $this->quote->placeOrder();
    }
}

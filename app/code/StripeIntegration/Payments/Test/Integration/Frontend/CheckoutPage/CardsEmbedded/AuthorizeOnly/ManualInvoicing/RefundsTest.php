<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\CheckoutPage\CardsEmbedded\AuthorizeOnly\ManualInvoicing;

class RefundsTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->cartManagement = $this->objectManager->get(\Magento\Quote\Api\CartManagementInterface::class);
        $this->webhooks = $this->objectManager->get(\StripeIntegration\Payments\Helper\Webhooks::class);
        $this->helper = $this->objectManager->get(\StripeIntegration\Payments\Helper\Generic::class);
        $this->stripeConfig = $this->objectManager->get(\StripeIntegration\Payments\Model\Config::class);
        $this->subscriptionFactory = $this->objectManager->get(\StripeIntegration\Payments\Model\SubscriptionFactory::class);
        $this->productRepository = $this->objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
        $this->eventFactory = $this->objectManager->get(\StripeIntegration\Payments\Test\Integration\Mock\Events\EventFactory::class);
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/active 1
     * @magentoConfigFixture current_store payment/stripe_payments_basic/stripe_mode test
     * @magentoConfigFixture current_store payment/stripe_payments_basic/stripe_test_pk pk_test_51Ig7MJHLyfDWKHBqqOpnyTkavM0LlpuH1QnrM1IsRGe26qwwo1uhQZbHyrnaiJuWpiIEkoFHgzgZoeLlfLOXp4ef00ApmFEugB
     * @magentoConfigFixture current_store payment/stripe_payments_basic/stripe_test_sk sk_test_51Ig7MJHLyfDWKHBqRZ6h9gRk1C738LWP1ljHVAyWsON7CIennpQV25sHvISdpbfHBqQWCNBTivTsKiIFjAPJhyB500ytiSiSSF
     * @magentoConfigFixture current_store payment/stripe_payments/checkout_mode 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize
     * @magentoConfigFixture current_store payment/stripe_payments/automatic_invoicing 0
     *
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Taxes.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Addresses.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Products.php
     */
    public function testRefunds()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->addProduct('simple-product', 1)
            ->addProduct('virtual-monthly-subscription-product', 1)
            ->addProduct('simple-monthly-subscription-product', 1)
            ->addProduct('simple-trial-monthly-subscription-product', 1)
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("SuccessCard");

        $order = $this->quote->placeOrder();

        $invoicesCollection = $order->getInvoiceCollection();

        $this->assertEquals("processing", $order->getState());
        $this->assertEquals("processing", $order->getStatus());
        $this->assertEquals(0, $invoicesCollection->count());

        $this->assertTrue($order->canInvoice());
        $this->assertTrue($order->canCancel());
        $this->assertEquals(0, $order->getTotalPaid());
        $this->assertEquals(0, $order->getBaseTotalPaid());
        $this->assertEquals(58.32, $order->getTotalDue());
        $this->assertEquals(58.32, $order->getBaseTotalDue());

        $stripe = $this->stripeConfig->getStripeClient();
        $customerId = $order->getPayment()->getAdditionalInformation("customer_stripe_id");
        $customer = $stripe->customers->retrieve($customerId);
        $this->assertEquals(3, count($customer->subscriptions->data));

        $subscriptions = array_reverse($customer->subscriptions->data);
        $event = $this->eventFactory->create();
        foreach ($subscriptions as $subscription)
            $event->triggerSubscriptionEvents($subscription, $this);

        $event->triggerPaymentIntentEvents($order->getPayment()->getLastTransId(), $this);

        // Refresh the order object
        $order = $this->helper->loadOrderByIncrementId($order->getIncrementId());

        $invoicesCollection = $order->getInvoiceCollection();

        $this->assertEquals("processing", $order->getState());
        $this->assertEquals("processing", $order->getStatus());
        $this->assertEquals(0, $invoicesCollection->count());

        $this->assertTrue($order->canInvoice());
        $this->assertTrue($order->canCancel());
        $this->assertEquals(42.49, $order->getTotalPaid());
        $this->assertEquals(42.49, $order->getBaseTotalPaid());
        $this->assertEquals(15.83, $order->getTotalDue());
        $this->assertEquals(15.83, $order->getBaseTotalDue());

        // Cancel the order
        $order->cancel();

        // Refresh the order object
        $order = $this->helper->loadOrderByIncrementId($order->getIncrementId());

        $this->assertFalse($order->canInvoice());
        $this->assertFalse($order->canCancel());
        $this->assertEquals("canceled", $order->getState());
        $this->assertEquals("canceled", $order->getStatus());
        $this->assertEquals(42.49, $order->getTotalPaid());
        $this->assertEquals(42.49, $order->getBaseTotalPaid());
        $this->assertEquals(42.49, $order->getTotalRefunded());
        $this->assertEquals(42.49, $order->getBaseTotalRefunded());
        $this->assertEquals(15.83, $order->getTotalCanceled());
        $this->assertEquals(15.83, $order->getBaseTotalCanceled());

        $charges = $stripe->charges->all(['limit' => 10, 'customer' => $customer->id]);

        $expected = [
            ['amount' => 1583, 'amount_captured' => 0, 'amount_refunded' => 1583],
            ['amount' => 1583, 'amount_captured' => 1583, 'amount_refunded' => 1583],
            ['amount' => 1083, 'amount_captured' => 1083, 'amount_refunded' => 1083],
        ];

        for ($i = 0; $i < count($charges); $i++)
        {
            $this->assertEquals($expected[$i]['amount'], $charges->data[$i]->amount);
            $this->assertEquals($expected[$i]['amount_captured'], $charges->data[$i]->amount_captured);
            $this->assertEquals($expected[$i]['amount_refunded'], $charges->data[$i]->amount_refunded);
        }
    }
}

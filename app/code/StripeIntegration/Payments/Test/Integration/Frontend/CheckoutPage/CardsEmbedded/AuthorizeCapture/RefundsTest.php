<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\CheckoutPage\CardsEmbedded\AuthorizeCapture;

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
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests();
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/active 1
     * @magentoConfigFixture current_store payment/stripe_payments_basic/stripe_mode test
     * @magentoConfigFixture current_store payment/stripe_payments_basic/stripe_test_pk pk_test_51Ig7MJHLyfDWKHBqqOpnyTkavM0LlpuH1QnrM1IsRGe26qwwo1uhQZbHyrnaiJuWpiIEkoFHgzgZoeLlfLOXp4ef00ApmFEugB
     * @magentoConfigFixture current_store payment/stripe_payments_basic/stripe_test_sk sk_test_51Ig7MJHLyfDWKHBqRZ6h9gRk1C738LWP1ljHVAyWsON7CIennpQV25sHvISdpbfHBqQWCNBTivTsKiIFjAPJhyB500ytiSiSSF
     * @magentoConfigFixture current_store payment/stripe_payments/checkout_mode 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize_capture
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
            ->addProduct('simple-trial-monthly-subscription-product', 1)
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("SuccessCard");

        $order = $this->quote->placeOrder();

        // Invoice checks
        $invoicesCollection = $order->getInvoiceCollection();
        $this->assertEquals(1, $invoicesCollection->count());
        $invoice = $invoicesCollection->getFirstItem();
        $this->assertTrue($invoice->getIsPaid());

        // Order checks
        $this->assertEquals(42.49, $order->getBaseGrandTotal());
        $this->assertEquals(42.49, $order->getGrandTotal());
        $this->assertEquals(42.49, $order->getTotalInvoiced());
        $this->assertEquals(42.49, $order->getTotalPaid());
        $this->assertEquals(0, $order->getTotalDue());
        $this->assertEquals(0, $order->getTotalRefunded());
        $this->assertEquals("processing", $order->getState());
        $this->assertEquals("processing", $order->getStatus());

        // Stripe checks
        $stripe = $this->stripeConfig->getStripeClient();
        $customerId = $order->getPayment()->getAdditionalInformation("customer_stripe_id");
        $customer = $stripe->customers->retrieve($customerId);
        $this->assertEquals(2, count($customer->subscriptions->data));

        // Trigger all webhooks
        $subscriptions = array_reverse($customer->subscriptions->data);
        $event = $this->eventFactory->create();
        foreach ($subscriptions as $subscription)
            $event->triggerSubscriptionEvents($subscription, $this);

        $event->triggerPaymentIntentEvents($order->getPayment()->getLastTransId(), $this);

        // Refresh the order object
        $order = $this->helper->loadOrderByIncrementId($order->getIncrementId());

        // Invoice checks
        $invoicesCollection = $order->getInvoiceCollection();
        $this->assertEquals(1, $invoicesCollection->count());
        $invoice = $invoicesCollection->getFirstItem();
        $this->assertEquals(\Magento\Sales\Model\Order\Invoice::STATE_PAID, $invoice->getState());

        // Order checks
        $this->assertEquals(42.49, $order->getBaseGrandTotal());
        $this->assertEquals(42.49, $order->getGrandTotal());
        $this->assertEquals(42.49, $order->getTotalInvoiced());
        $this->assertEquals(26.66, $order->getTotalPaid());
        $this->assertEquals(15.83, $order->getTotalDue());
        $this->assertEquals(0, $order->getTotalRefunded());
        $this->assertEquals(0, $order->getTotalCanceled());
        $this->assertEquals("processing", $order->getState());
        $this->assertEquals("processing", $order->getStatus());

        // Refund the order
        $this->assertTrue($order->canCreditmemo());
        $this->tests->refundOnline($invoice, ['simple-product' => 1, 'virtual-monthly-subscription-product' => 1], $shipping = 5);

        // Refresh the order object
        $order = $this->helper->loadOrderByIncrementId($order->getIncrementId());

        // Invoice checks
        $invoicesCollection = $order->getInvoiceCollection();
        $this->assertEquals(1, $invoicesCollection->count());
        $this->assertEquals(\Magento\Sales\Model\Order\Invoice::STATE_PAID, $invoice->getState());

        // Order checks
        $this->assertEquals(42.49, $order->getBaseGrandTotal());
        $this->assertEquals(42.49, $order->getGrandTotal());
        $this->assertEquals(42.49, $order->getTotalInvoiced());
        $this->assertEquals(26.66, $order->getTotalPaid());
        $this->assertEquals(15.83, $order->getTotalDue());
        $this->assertEquals(26.66, $order->getTotalRefunded());
        $this->assertEquals(0, $order->getTotalCanceled());
        $this->assertFalse($order->canCreditmemo());
        $this->assertEquals("processing", $order->getState());
        $this->assertEquals("processing", $order->getStatus());

        // Expire the trial subscription
        $ordersCount = $this->objectManager->get('Magento\Sales\Model\Order')->getCollection()->count();
        foreach ($subscriptions as $subscription)
        {
            if ($subscription->status == "trialing")
            {
                $stripe->subscriptions->update($subscription->id, ['trial_end' => "now"]);
                $subscription = $stripe->subscriptions->retrieve($subscription->id, ['expand' => ['latest_invoice']]);

                // Trigger webhook events for the trial end
                $event = $this->eventFactory->create();
                $event->triggerSubscriptionEvents($subscription, $this);

            }
        }

        // Check that a new order was created
        $newOrdersCount = $this->objectManager->get('Magento\Sales\Model\Order')->getCollection()->count();
        $this->assertEquals($ordersCount + 1, $newOrdersCount);

        // Refresh the order object
        $order = $this->helper->loadOrderByIncrementId($order->getIncrementId());

        // Invoice checks
        $invoicesCollection = $order->getInvoiceCollection();
        $this->assertEquals(1, $invoicesCollection->count());
        $this->assertEquals(\Magento\Sales\Model\Order\Invoice::STATE_PAID, $invoice->getState());

        // Order checks
        $this->assertEquals(42.49, $order->getBaseGrandTotal());
        $this->assertEquals(42.49, $order->getGrandTotal());
        $this->assertEquals(42.49, $order->getTotalInvoiced());
        $this->assertEquals(42.49, $order->getTotalPaid());
        $this->assertEquals(0, $order->getTotalDue());
        $this->assertEquals(26.66, $order->getTotalRefunded());
        $this->assertEquals(0, $order->getTotalCanceled());
        $this->assertEquals("processing", $order->getState());
        $this->assertEquals("processing", $order->getStatus());

        // Refund the trial subscription via the 1st order
        $this->assertTrue($order->canCreditmemo());
        $this->tests->refundOnline($invoice, ['simple-trial-monthly-subscription-product' => 1], $shipping = 5);

        // Refresh the order object
        $order = $this->helper->loadOrderByIncrementId($order->getIncrementId());

        // Order checks
        $this->assertEquals(42.49, $order->getBaseGrandTotal());
        $this->assertEquals(42.49, $order->getGrandTotal());
        $this->assertEquals(42.49, $order->getTotalInvoiced());
        $this->assertEquals(42.49, $order->getTotalPaid());
        $this->assertEquals(0, $order->getTotalDue());
        $this->assertEquals(42.49, $order->getTotalRefunded());
        $this->assertEquals(0, $order->getTotalCanceled());
        $this->assertFalse($order->canCreditmemo());
        $this->assertEquals("closed", $order->getState());
        $this->assertEquals("closed", $order->getStatus());

        // @todo - check that the newly created order has also been closed


        // Stripe checks
        $charges = $stripe->charges->all(['limit' => 10, 'customer' => $customer->id]);

        $expected = [
            ['amount' => 1583, 'amount_captured' => 1583, 'amount_refunded' => 1583],
            ['amount' => 1583, 'amount_captured' => 1583, 'amount_refunded' => 1583],
            ['amount' => 1083, 'amount_captured' => 1083, 'amount_refunded' => 1083],
        ];

        for ($i = 0; $i < count($charges); $i++)
        {
            $this->assertEquals($expected[$i]['amount'], $charges->data[$i]->amount, "Charge $i");
            $this->assertEquals($expected[$i]['amount_captured'], $charges->data[$i]->amount_captured, "Charge $i");
            $this->assertEquals($expected[$i]['amount_refunded'], $charges->data[$i]->amount_refunded, "Charge $i");
        }
    }
}

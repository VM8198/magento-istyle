<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\CheckoutPage\CardsEmbedded\AuthorizeOnly\AutomaticInvoicing;

class PlaceOrderTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->cartManagement = $this->objectManager->get(\Magento\Quote\Api\CartManagementInterface::class);
        $this->webhooks = $this->objectManager->get(\StripeIntegration\Payments\Helper\Webhooks::class);
        $this->request = $this->objectManager->get(\Magento\Framework\App\Request\Http::class);
        $this->helper = $this->objectManager->get(\StripeIntegration\Payments\Helper\Generic::class);
        $this->stripeConfig = $this->objectManager->get(\StripeIntegration\Payments\Model\Config::class);
        $this->subscriptionFactory = $this->objectManager->get(\StripeIntegration\Payments\Model\SubscriptionFactory::class);
        $this->productRepository = $this->objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
        $this->eventFactory = $this->objectManager->get(\StripeIntegration\Payments\Test\Integration\Mock\Events\EventFactory::class);
    }

    /**
     * @ticket MAGENTO-5
     *
     * @magentoAppIsolation enabled
     * @magentoConfigFixture current_store payment/stripe_payments/active 1
     * @magentoConfigFixture current_store payment/stripe_payments_basic/stripe_mode test
     * @magentoConfigFixture current_store payment/stripe_payments_basic/stripe_test_pk pk_test_51Ig7MJHLyfDWKHBqqOpnyTkavM0LlpuH1QnrM1IsRGe26qwwo1uhQZbHyrnaiJuWpiIEkoFHgzgZoeLlfLOXp4ef00ApmFEugB
     * @magentoConfigFixture current_store payment/stripe_payments_basic/stripe_test_sk sk_test_51Ig7MJHLyfDWKHBqRZ6h9gRk1C738LWP1ljHVAyWsON7CIennpQV25sHvISdpbfHBqQWCNBTivTsKiIFjAPJhyB500ytiSiSSF
     * @magentoConfigFixture current_store payment/stripe_payments/checkout_mode 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize
     * @magentoConfigFixture current_store payment/stripe_payments/automatic_invoicing 1
     *
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Taxes.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Addresses.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Products.php
     */
    public function testNormalCart()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("Normal")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("SuccessCard");

        $order = $this->quote->placeOrder();

        // Refresh the order object
        $order = $this->helper->loadOrderByIncrementId($order->getIncrementId());

        $invoicesCollection = $order->getInvoiceCollection();

        $this->assertEquals("processing", $order->getState());
        $this->assertEquals("processing", $order->getStatus());
        $this->assertNotEmpty($invoicesCollection);
        $this->assertEquals(1, $invoicesCollection->count());

        $invoice = $invoicesCollection->getFirstItem();

        $this->assertEquals(2, count($invoice->getAllItems()));
        $this->assertTrue($invoice->canCapture());
        $this->assertEquals(\Magento\Sales\Model\Order\Invoice::STATE_OPEN, $invoice->getState());
    }

    /**
     * @ticket MAGENTO-5
     *
     * @magentoAppIsolation enabled
     * @magentoConfigFixture current_store payment/stripe_payments/active 1
     * @magentoConfigFixture current_store payment/stripe_payments_basic/stripe_mode test
     * @magentoConfigFixture current_store payment/stripe_payments_basic/stripe_test_pk pk_test_51Ig7MJHLyfDWKHBqqOpnyTkavM0LlpuH1QnrM1IsRGe26qwwo1uhQZbHyrnaiJuWpiIEkoFHgzgZoeLlfLOXp4ef00ApmFEugB
     * @magentoConfigFixture current_store payment/stripe_payments_basic/stripe_test_sk sk_test_51Ig7MJHLyfDWKHBqRZ6h9gRk1C738LWP1ljHVAyWsON7CIennpQV25sHvISdpbfHBqQWCNBTivTsKiIFjAPJhyB500ytiSiSSF
     * @magentoConfigFixture current_store payment/stripe_payments/checkout_mode 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize
     * @magentoConfigFixture current_store payment/stripe_payments/automatic_invoicing 1
     *
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Taxes.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Addresses.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Products.php
     */
    public function testMixedTrialCart()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("MixedTrial")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("SuccessCard");

        $order = $this->quote->placeOrder();

        $this->assertEquals("processing", $order->getState());
        $this->assertEquals("processing", $order->getStatus());
        $this->assertEquals(1, $order->getInvoiceCollection()->count());
        $invoice = $order->getInvoiceCollection()->getFirstItem();
        $this->assertEquals(\Magento\Sales\Model\Order\Invoice::STATE_OPEN, $invoice->getState());
        $this->assertTrue($invoice->canCapture());

        $subscriptionModel = $this->subscriptionFactory->create();
        $subscriptionsCollection = $subscriptionModel->getCollection()->getByOrderIncrementId($order->getIncrementId());
        $this->assertEquals(1, $subscriptionsCollection->getSize());

        $stripeSubscription = null;
        foreach ($subscriptionsCollection as $row)
        {
            $this->assertNotEmpty($row->getSubscriptionId());
            $stripeSubscription = $this->stripeConfig->getStripeClient()->subscriptions->retrieve($row->getSubscriptionId(), ['expand' => ['latest_invoice']]);
            $this->assertNotEmpty($stripeSubscription->latest_invoice);

            $event = $this->eventFactory->create()->setType("invoice.payment_succeeded");
            $event->dispatch($stripeSubscription->latest_invoice);
            $this->assertEquals(200, $event->getResponse()->getStatusCode());
        }

        // Refresh the order object
        $order = $this->helper->loadOrderByIncrementId($order->getIncrementId());

        $invoicesCollection = $order->getInvoiceCollection();

        $this->assertNotEmpty($invoicesCollection);
        $this->assertEquals(1, $invoicesCollection->count());

        $invoice = $invoicesCollection->getFirstItem();
        $this->assertEquals(2, count($invoice->getAllItems()));
        $this->assertEquals(31.66, $invoice->getGrandTotal());

        $this->assertEquals(\Magento\Sales\Model\Order\Invoice::STATE_OPEN, $invoice->getState());
        $this->assertEquals("processing", $order->getState());
        $this->assertEquals("processing", $order->getStatus());

        // Capture the invoice payment
        $paymentIntentId = $order->getPayment()->getLastTransId();
        $this->assertNotEmpty($paymentIntentId);
        $paymentIntent = $this->stripeConfig->getStripeClient()->paymentIntents->retrieve($paymentIntentId);
        $this->assertEquals("manual", $paymentIntent->capture_method);
        $this->assertEquals("1583", $paymentIntent->amount_capturable);
        $this->assertEquals("0", $paymentIntent->amount_received);
        $this->assertEquals("requires_capture", $paymentIntent->status);

        $invoice->setRequestedCaptureCase('online')->capture()->save();

        $this->assertEquals(\Magento\Sales\Model\Order\Invoice::STATE_PAID, $invoice->getState());
        $this->assertEquals("processing", $order->getState());
        $this->assertEquals("processing", $order->getStatus());

        // Check that the Payment Intent was captured
        $paymentIntent = $this->stripeConfig->getStripeClient()->paymentIntents->retrieve($paymentIntentId);
        $this->assertEquals("0", $paymentIntent->amount_capturable);
        $this->assertEquals("1583", $paymentIntent->amount_received);
        $this->assertEquals("succeeded", $paymentIntent->status);
    }
}

<?php

namespace StripeIntegration\Payments\Test\Integration\StripeDashboard\Captures;

class PartialTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();
        $this->cartManagement = $this->objectManager->get(\Magento\Quote\Api\CartManagementInterface::class);
        $this->webhooks = $this->objectManager->get(\StripeIntegration\Payments\Helper\Webhooks::class);
        $this->request = $this->objectManager->get(\Magento\Framework\App\Request\Http::class);
        $this->helper = $this->objectManager->get(\StripeIntegration\Payments\Helper\Generic::class);
        $this->stripeConfig = $this->objectManager->get(\StripeIntegration\Payments\Model\Config::class);
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
     *
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Taxes.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Addresses.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Products.php
     */
    public function testPartialCapture()
    {
        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("Normal")
            ->setShippingAddress("California")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("California")
            ->setPaymentMethod("SuccessCard");

        $order = $this->quote->placeOrder();
        $orderIncrementId = $order->getIncrementId();

        $currency = $order->getOrderCurrencyCode();
        $amount = $this->helper->convertMagentoAmountToStripeAmount($order->getGrandTotal(), $currency);
        $amountCaptured = 500;
        $amountPaid = ($amountCaptured / 100);

        $this->assertEquals("processing", $order->getStatus());
        $this->assertEquals(0, $order->getTotalPaid());
        $this->assertEquals($order->getGrandTotal(), $order->getTotalDue());

        // Partially capture the charge
        $stripe = $this->stripeConfig->getStripeClient();
        $paymentIntentId = $order->getPayment()->getLastTransId();
        $paymentIntent = $stripe->paymentIntents->capture($paymentIntentId, ["amount_to_capture" => $amountCaptured]);

        // charge.captured
        $event = $this->eventFactory->create()->setType("charge.captured");
        $event->dispatch($paymentIntent->charges->data[0]);
        $this->assertEquals(200, $event->getResponse()->getStatusCode());

        // payment_intent.succeeded
        $event = $this->eventFactory->create()->setType("payment_intent.succeeded");
        $event->dispatch($paymentIntent);
        $this->assertEquals(200, $event->getResponse()->getStatusCode());

        // Refresh the order object
        $order = $this->helper->loadOrderByIncrementId($orderIncrementId);
        $this->assertEquals("processing", $order->getStatus());
        $this->assertEquals($amountPaid, $order->getTotalPaid());
        $this->assertEquals($order->getGrandTotal() - $amountPaid, $order->getTotalDue());
    }
}

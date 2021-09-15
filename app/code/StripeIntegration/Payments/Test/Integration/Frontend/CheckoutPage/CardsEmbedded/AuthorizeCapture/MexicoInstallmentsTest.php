<?php

namespace StripeIntegration\Payments\Test\Integration\Frontend\CheckoutPage\CardsEmbedded\AuthorizeCapture;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Checkout\Model\SessionFactory as CheckoutSessionFactory;
use PHPUnit\Framework\Constraint\StringContains;

class MexicoInstallmentsTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        $this->objectManager = \Magento\TestFramework\ObjectManager::getInstance();

        $this->checkoutSession = $this->objectManager->get(CheckoutSessionFactory::class)->create();
        $this->transportBuilder = $this->objectManager->get(\Magento\TestFramework\Mail\Template\TransportBuilderMock::class);
        $this->eventManager = $this->objectManager->get(\Magento\Framework\Event\ManagerInterface::class);
        $this->orderSender = $this->objectManager->get(\Magento\Sales\Model\Order\Email\Sender\OrderSender::class);
        $this->helper = $this->objectManager->get(\StripeIntegration\Payments\Helper\Generic::class);
        $this->stripeConfig = $this->objectManager->get(\StripeIntegration\Payments\Model\Config::class);
        $this->eventFactory = $this->objectManager->get(\StripeIntegration\Payments\Test\Integration\Mock\Events\EventFactory::class);
        $this->quote = new \StripeIntegration\Payments\Test\Integration\Helper\Quote();
        $this->tests = new \StripeIntegration\Payments\Test\Integration\Helper\Tests();
        $this->orderRepository = $this->objectManager->get(\Magento\Sales\Api\OrderRepositoryInterface::class);
        $this->api = $this->objectManager->get(\StripeIntegration\Payments\Api\Service::class);
    }

    /**
     * @magentoConfigFixture current_store payment/stripe_payments/active 1
     * @magentoConfigFixture current_store payment/stripe_payments_basic/stripe_mode test
     * @magentoConfigFixture current_store payment/stripe_payments_basic/stripe_test_pk pk_test_51H2d4OENPp2qkdrck7If7ApDUeU6MRrQQwBAqyPLVD5AcsCtGaaBfQJXZmll9WucqVT0Jiv01Fqex3bzG1QcyCLO00VLYqwVz2
     * @magentoConfigFixture current_store payment/stripe_payments_basic/stripe_test_sk sk_test_51H2d4OENPp2qkdrcKSFW4SWarfNszsYcDnK6BqkRFpYNPPD5UFaeWcKn6sZxugm6Ag6fI0w45Sx9jLNN3QPIoFcm00oSEgG57Z
     * @magentoConfigFixture current_store payment/stripe_payments/checkout_mode 0
     * @magentoConfigFixture current_store payment/stripe_payments/payment_action authorize_capture
     * @magentoConfigFixture current_store currency/options/base MXN
     * @magentoConfigFixture current_store currency/options/allow MXN
     * @magentoConfigFixture current_store currency/options/default MXN
     *
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Taxes.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Addresses.php
     * @magentoDataFixture ../../../../app/code/StripeIntegration/Payments/Test/Integration/_files/Data/Products.php
     */
    public function testPlans()
    {
        $this->markTestIncomplete("The test is not finished.");

        $this->quote->create()
            ->setCustomer('Guest')
            ->setCart("Normal")
            ->setShippingAddress("Mexico")
            ->setShippingMethod("FlatRate")
            ->setBillingAddress("Mexico")
            ->setPaymentMethod("MexicoInstallmentsCard");

        $quote = $this->quote->getQuote();

        $paymentMethodId = $quote->getPayment()->getAdditionalInformation("token");
        $this->assertNotEmpty($paymentMethodId);

        $plans = $this->api->get_installment_plans($paymentMethodId);
    }
}

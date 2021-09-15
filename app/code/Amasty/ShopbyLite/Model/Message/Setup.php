<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_ShopbyLite
 */


namespace Amasty\ShopbyLite\Model\Message;

class Setup implements \Magento\Framework\Notification\MessageInterface
{
    /**
     * @var \Magento\Framework\Module\Manager
     */
    private $moduleManager;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $urlBuilder;

    /**
     * @param \Magento\Indexer\Model\Indexer\Collection $collection
     * @param \Magento\Framework\UrlInterface $urlBuilder
     */
    public function __construct(
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Framework\UrlInterface $urlBuilder
    ) {
        $this->moduleManager = $moduleManager;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Check whether all indices are valid or not
     *
     * @return bool
     */
    public function isDisplayed()
    {
        if ($this->moduleManager->isEnabled('Amasty_Shopby')) {
            return true;
        }

        return false;
    }

    //@codeCoverageIgnoreStart

    /**
     * Retrieve unique message identity
     *
     * @return string
     */
    public function getIdentity()
    {
        return hash('sha256', 'SHOPBY INSTALLED');
    }

    /**
     * Retrieve message text
     *
     * @return \Magento\Framework\Phrase
     */
    public function getText()
    {
        $url = $this->urlBuilder->getUrl('adminhtml/system_config/edit', ['section' => 'amshopby']);
        //@codingStandardsIgnoreStart
        return __(
            'You have Improved Layered Navigation extension installed. '
            . 'Please disable Lite Layered Navigation extension for correct work.'
        );
        //@codingStandardsIgnoreEnd
    }

    /**
     * Retrieve message severity
     *
     * @return int
     */
    public function getSeverity()
    {
        return self::SEVERITY_CRITICAL;
    }

    //@codeCoverageIgnoreEnd
}

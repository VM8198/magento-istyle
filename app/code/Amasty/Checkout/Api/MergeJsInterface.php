<?php
/**
 * @author Amasty Team
 * @copyright Copyright (c) 2020 Amasty (https://www.amasty.com)
 * @package Amasty_Checkout
 */


namespace Amasty\Checkout\Api;

interface MergeJsInterface
{
    /**
     * @param string[] $fileNames
     * @return boolean
     */
    public function createBundle(array $fileNames);
}

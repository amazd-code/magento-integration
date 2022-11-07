<?php

/**
 * Amazd Integration
 *
 * @copyright  Copyright (c) Amazd (https://www.amazd.co/)
 * @license    https://github.com/amazd-code/magento-integration/blob/master/LICENSE.md
 */

namespace Amazd\Integration\Model;

class CheckoutResult
{
    /**
     * @var bool is success
     */
    public $success;
    /**
     * @var string error message
     */
    public $error;

    /**
     * Index constructor
     *
     * @param bool $success
     * @param string $error - error message
     */
    public function __construct($success = false, $error = null)
    {
        $this->success = $success;
        $this->error = $error;
    }
}

<?php

/**
 * Amazd Integration
 *
 * @category  Amazd
 * @package   Amazd_Integration
 * @copyright 2022 Amazd (https://www.amazd.co/)
 * @license   https://github.com/amazd-code/magento-integration/blob/master/LICENSE MIT
 */

namespace Amazd\Integration\Controller\Index;

class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var rawResultFactory
     */
    protected $rawResultFactory;

    /**
     * Index constructor
     *
     * @param Context $context
     * @param RawFactory $rawResultFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\Result\RawFactory $rawResultFactory
    ) {
        $this->rawResultFactory = $rawResultFactory;
        return parent::__construct($context);
    }

    /**
     * Index execute
     */
    public function execute()
    {
        $page = $this->rawResultFactory->create();
        $page->setHeader('Content-Type', 'text/html');
        $page->setContents('<body>Amazd Integration is active</body>');
        return $page;
    }
}

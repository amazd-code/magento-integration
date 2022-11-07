<?php

/**
 * Amazd
 *
 * Integration of Amazd wishbag-to-checkout flow.
 *
 * @copyright  Copyright (c) Amazd (https://www.amazd.co/)
 * @license    https://github.com/amazd-code/magento-integration/blob/master/LICENSE.md
 */

namespace Amazd\Integration\Controller\Index;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Amazd\Integration\Model\CheckoutResult;

class Checkout extends \Magento\Framework\App\Action\Action
{
    /**
     * @var rawResultFactory
     */
    protected $rawResultFactory;
    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    protected $maskedQuoteIdToQuoteId;
    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;
    /**
     * @var Magento cart of the current session
     */
    protected $cart;

    /**
     * Checkout constructor
     *
     * @param Context $context
     * @param RawFactory $rawResultFactory
     * @param CartRepositoryInterface $quoteRepository
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param ProductRepository $productRepository
     * @param Cart $cart
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\Result\RawFactory $rawResultFactory,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Checkout\Model\Cart $cart
    ) {
        $this->rawResultFactory = $rawResultFactory;
        $this->quoteRepository = $quoteRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->productRepository = $productRepository;
        $this->cart = $cart;
        return parent::__construct($context);
    }

    /**
     * Checkout execute
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $quoteMaskId = $this->getRequest()->getParam('token');
        $result = null;

        try {
            $result = $this->mergeQuote($quoteMaskId);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        if ($result && $result->success) {
            return $resultRedirect->setPath('checkout/cart');
        } elseif ($result && $result->error) {
            $page = $this->rawResultFactory->create();
            $page->setHeader('Content-Type', 'text/xml');
            $page->setContents('<body>' . $result->error . '</body>');
            return $page;
        } else {
            return $resultRedirect->setPath('/');
        }
    }

    /**
     * Merge specified quote with quote of the current session.
     *
     * @param String $quoteMaskId
     */
    private function mergeQuote($quoteMaskId)
    {
        $result = new CheckoutResult();

        if (!$quoteMaskId) {
            $result->error = 'Invalid token';
            return $result;
        }

        $quoteId = null;

        try {
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($quoteMaskId);
            if (!$quoteId) {
                $result->error = 'Cart id not found or this link is already used';
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $result->error = 'Cart id not found or this link is already used';
        }

        if (!$quoteId) {
            return $result;
        }

        $quote = $this->quoteRepository->get($quoteId);
        if (!$quote) {
            $result->error = 'Cart not found';
            return $result;
        }

        if (!$quote->getId()) {
            $result->error = 'Cart is not available';
            return $result;
        }

        $items = $quote->getItemsCollection();

        foreach ($items as $item) {
            if ($item->getParentItemId()) {
                continue;
            }

            $productId = $item->getProductId();
            if ($this->cart->getQuote()->hasProductId($productId)) {
                continue;
            }

            try {
                $product = $this->productRepository->getById($productId, false, $quote->getStoreId(), true);
                $info = $item->getProduct()->getTypeInstance(true)
                    ->getOrderOptions($item->getProduct())['info_buyRequest'];
                $productType = $item->getProductType();
                $info['qty'] = $item->getQty();

                if ($productType === 'configurable' || $productType === 'bundle') {
                    $this->cart->addProduct($product, $info);
                } else {
                    $this->cart->addProduct($item->getProduct(), $info);
                }
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                $result->error = 'Can not add product to cart';
                return $result;
            }
        }
        $this->cart->save();

        // This is temporary guest cart created by Amazd backend. Remove after merged with current cart.
        $quote->delete();

        $result->success = true;
        return $result;
    }
}

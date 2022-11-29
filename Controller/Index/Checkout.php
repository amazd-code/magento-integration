<?php

/**
 * Amazd
 *
 * Integration of Amazd wishbag-to-checkout flow.
 *
 * @category  Amazd
 * @package   Amazd_Integration
 * @copyright 2022 Amazd (https://www.amazd.co/)
 * @license   https://github.com/amazd-code/magento-integration/blob/master/LICENSE MIT
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
     * @param  Context                         $context
     * @param  RawFactory                      $rawResultFactory
     * @param  CartRepositoryInterface         $quoteRepository
     * @param  MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param  ProductRepository               $productRepository
     * @param  Cart                            $cart
     * @return CheckoutResult                  $result
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
        $maskedQuoteId = $this->getRequest()->getParam('token');

        try {
            $this->_mergeQuote($maskedQuoteId);
        } catch (LocalizedException $e) {
            error_log($e->getMessage());
            $this->_showError('Something went wrong opening your wishbag');
        }

        return $resultRedirect->setPath('checkout/cart');
    }

    /**
     * Merge specified quote with quote of the current session.
     *
     * @param string $maskedQuoteId
     */
    private function _mergeQuote($maskedQuoteId)
    {
        if (!$maskedQuoteId) {
            $this->_showError('Invalid token');
            return;
        }

        $quoteId = null;

        try {
            $quoteId = $this->maskedQuoteIdToQuoteId->execute($maskedQuoteId);
        } catch (LocalizedException $e) {
            error_log($e->getMessage());
        }

        if (!$quoteId) {
            $this->_showError('Cart is not found or this link is already used');
            return;
        }

        $quote = $this->quoteRepository->get($quoteId);
        if (!$quote) {
            $this->_showError('Cart is not found');
            return;
        }

        if (!$quote->getId()) {
            $this->_showError('Cart is not available');
            return;
        }

        $items = $quote->getItemsCollection();

        foreach ($items as $item) {
            try {
                if ($item->getParentItemId()) {
                    continue;
                }

                $productId = $item->getProductId();
                if ($this->cart->getQuote()->hasProductId($productId)) {
                    continue;
                }

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
                error_log($e->getMessage());
                $this->_showError('Cannot add product ' . $productId . ' to your cart');
            }
        }

        try {
            $couponCode = $quote->getCouponCode();

            if ($couponCode) {
                $this->cart->getQuote()->setCouponCode($couponCode)->collectTotals()->save();
            }
        } catch (LocalizedException $e) {
            error_log($e->getMessage());
            $this->_showError('Could not apply coupon');
        }

        $this->cart->save();

        // This is temporary guest cart created by Amazd backend. Remove after merged with current cart.
        $quote->delete();
    }

    /**
     * Show Amazd wishbag related error to a user
     *
     * @param string $message
     */
    private function _showError($message)
    {
        $this->messageManager->addErrorMessage("There's problem opening your Amazd wishbag: " . $message);
    }
}

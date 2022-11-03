<?php

namespace Amazd\Integration\Controller\Index;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Amazd\Integration\Model\CheckoutResult;

class Checkout extends \Magento\Framework\App\Action\Action
{
	protected $_pageFactory;
	protected $quoteFactory;
	protected $maskedQuoteIdToQuoteId;
	protected $quoteRepository;
	protected $productRepository;
	protected $cart;

	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\View\Result\PageFactory $pageFactory,
		\Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
		\Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
		\Magento\Catalog\Model\ProductRepository $productRepository,
		\Magento\Checkout\Model\Cart $cart
	) {
		$this->_pageFactory = $pageFactory;
		$this->quoteRepository = $quoteRepository;
		$this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
		$this->productRepository = $productRepository;
		$this->cart              = $cart;
		return parent::__construct($context);
	}

	public function execute()
	{
		$resultRedirect   = $this->resultRedirectFactory->create();
		$quoteMaskId = $this->getRequest()->getParam('token');

		try {
			$result = $this->openCheckout($quoteMaskId);
		} catch (LocalizedException $e) {
			$this->messageManager->addErrorMessage($e->getMessage());
		}

		if ($result && $result->success) {
			return $resultRedirect->setPath('checkout/cart');
		} else if ($result && $result->error) {
			echo $result->error;
			exit;
		} else {
			return $resultRedirect->setPath('/');
		}
	}

	private function openCheckout($quoteMaskId)
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
				$result->error = "Cart id not found";
			}
		} catch (LocalizedException $e) {
			$this->messageManager->addErrorMessage($e->getMessage());
			$result->error = "Cart id not found";
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
			if (!$item->getParentItemId()) {
				$storeId = $quote->getStoreId();
				try {
					/**
					 * We need to reload product in this place, because products
					 * with the same id may have different sets of order attributes.
					 */
					$product     = $this->productRepository->getById($item->getProductId(), false, $storeId, true);
					$options     = $item->getProduct()->getTypeInstance(true)
						->getOrderOptions($item->getProduct());
					$info        = $options['info_buyRequest'];
					$productType = $item->getProductType();
					$info['qty'] = $item->getQty();

					if ($productType === 'configurable' || $productType === 'bundle') {
						$this->cart->addProduct($product, $info);
					} else {
						$this->cart->addProduct($item->getProduct(), $info);
					}
				} catch (NoSuchEntityException $e) {
					throw new LocalizedException(__('Can not add product to cart'));
				}
			}
		}
		$this->cart->save();

		$result->success = true;
		return $result;
	}
}

<?php
class Basket_IndexController {
	
	private function _addOrder($filteredData, $personData) {
		$payment = $this->_getPayment($filteredData['fk_orp_id']);
		$payment->init(); //luŸne wi¹zanie np. eventów p³atnoœci
		
		//...
		
		$orderModel = new order_Model_Default();
        $orderId = $orderModel->add(
            $shippingCost,
            $filteredData,
            $this->_getBasketId(),
            $arrCurrentBasketInfo,
            $personData,
            true,
            $aBasketService,
            $langId
        );
		
		$this->_redirectWithPaymentContext($paymentTypeId);
	}

	private function _redirectWithPaymentContext($paymentTypeId) {
		$payment = $this->_getPayment($paymentTypeId);
		return $this->_redirectByPayment($payment);
	}
	
	private function _getPayment($paymentTypeId) {
		//jak to siê nie podoba, to spróbuj tego: http://www.mwop.net/blog/235-A-Simple-Resource-Injector-for-ZF-Action-Controllers.html
		$collector = $this->getInvokeArg('bootstrap')->getResource('paymentCollector');
		
		$behaviour = DB::getPaymentBehaviour($paymentTypeId); //siakieœ doctrine'owe coœ
		
		if ($collector->isRegistered($behaviour)) {
			$payment = $collector->getPayment($behaviour);
			return $payment;
		} else {
			throw new Exception(sprintf("Payment type %s not registered, check your application.ini"));
		}
	}
	
	private function _redirectByPayment(Payment $payment) {
		if (is_a($payment, 'RedirectingPayment')) {
			return $this->_helper->redirector->goToUrl($payment->getRedirectionTarget());
		} else {
			return $this->_helper->redirector->goToRoute('standard_summary');
		}
	}
}
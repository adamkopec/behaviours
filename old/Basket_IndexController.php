<?php
class Basket_IndexController extends Zend_Controller_Action {
	/**
	<method name="_addOrder" ccn="5" ccn2="6" cloc="13" eloc="44" lloc="27" loc="70" ncloc="57" npath="18"/>
    <method name="_redirectWithPaymentContext" ccn="5" ccn2="6" cloc="1" eloc="29" lloc="22" loc="34" ncloc="33" npath="10"/>
	**/
	private function _addOrder($filteredData, $personData) {
        $langId = $this->_getParam(ModelLang::USER_LANG_ID);
        $paymentTypeId = $filteredData['fk_orp_id'] = $this->_getPaymentId();
        
        $shippingTypeId = (int) $filteredData['fk_osh_id']['normal'];
        if (is_numeric($shippingTypeId)) {
            $filteredData['fk_osh_id'] = $shippingTypeId;
        }
        
        // basket products
        $basketModel = new basket_Model_Default();
        $arrCurrentBasketInfo = $basketModel->getBasketProductsForBasketShowGrouped($this->_getBasketId(), $langId, $paymentTypeId, null, ModelUser::getCurrentPersonId());
        $arrCurrentBasketInfo['products'] = $arrCurrentBasketInfo['products']['normal'];

//        $stuffDeliveryPrice = 0.0; //nie ma ustawionej odgórnie ceny transportu
//        $deliveryPriceLessPercent = 100; //100%
//        $reactionDeliveryPrice = null;

        $sessionNamespace = new Zend_Session_Namespace(marketing_Model_Default::LINE_DISC_SESSION_NAMESPACE);
        $sessionNamespace->disc = null;

        foreach ($arrCurrentBasketInfo['products'] as $basket) {
            if (isset($basket['marketingInfo']['line_discount'])) {
                $sessionNamespace->disc = (float) $basket['marketingInfo']['line_discount'];
            }
        }

        $shippingCost = basket_Model_Shipping::getMarketingShippingConstSimple($shippingTypeId, $paymentTypeId, array('normal' => $arrCurrentBasketInfo['products']));

        // uslugi dodatkowe
        $oService = new EisBasketService();
        $aBasketService = $oService->getByBasketId($this->_getBasketId());

        // SAVE CHANGES
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
        //$checkAvailabilityModel = new basket_Model_CheckAvailability; // do rezerwacji
        //$checkAvailabilityModel->setCurrentBasketProducts($arrCurrentBasketInfo);
        //$checkAvailabilityModel->makeReservation($this->_getBasketId());
        
        # mysterious 
        {{
            $omysteriousModel = new mysterious_Model_mysterious();
            # usuniecie wizyty w przypadku gdy zostala zarezerwowana a potem z niej zrezygnowano
            if(!isset($personData['mysterious_callendar']['date']) || !($personData['mysterious_callendar']['date']) ) {
                $omysteriousModel->deletemysteriousVisitByBasketId($this->_getBasketId());
            }
            
            # update powiazanie wizyty z zamowieniem
            $omysteriousModel->updateOrderIdInVisit($this->_getBasketId(), $orderId);
        }}

        // session update
        $this->_sessionNamespace->orderId = $orderId;
        $this->_sessionNamespace->paymentTypeId = $paymentTypeId;
        $this->_sessionNamespace->shippingTypeId = $shippingTypeId;

        $this->_markCurrentDiscountCodeAsUsed();
        
        $this->_redirectWithPaymentContext($paymentTypeId);
    }
	
	private function _redirectWithPaymentContext($paymentTypeId) {
        $orderId = $this->_sessionNamespace->orderId;
        
        if ($orderId === false) {
            $this->getHelper('Messenger')->err('order_was_already_ordered', true);
            $this->_redirect('/');
        }

        $this->_sessionNamespace->td_token = true;
        switch($paymentTypeId)
        {
            case EisOrderPayment::getIdForPayU():
                $this->getHelper('Messenger')->ok('epayment_message', true);
                $this->getHelper('Redirector')->gotoRoute(array('id' => $orderId), 'basket_show_payment');
                break;

            case EisOrderPayment::getIdForMTransfer():
                if (!class_exists('mtransfer_Model_Settings') || !mtransfer_Model_Settings::getDefaultId()) {
                    FooCompany_Log::crit('mtransfer_no_enabled');
                    $this->getHelper('Messenger')->err('mtransfer_message_no_enabled');
                    $this->getHelper('Redirector')->gotoReferer();
                } else {
                    $this->getHelper('Messenger')->ok('mtransfer_message');
                    $this->getHelper('Redirector')->gotoRoute(array('id' => $orderId), 'basket_show_mtransfer');
                }
                break;

            default:
                // payment type not found
                FooCompany_Log::debug(__METHOD__ . ': invalid payment id: ' . $paymentTypeId . ', record not found.', 'basket_order');
                $this->getHelper('Messenger')->ok('transfer_payment_message', true);
                $this->_redirect(FooCompany_View_Helper_Url::urlSSL(array(), 'basket_checkout_summary'));                
        }
    }
}
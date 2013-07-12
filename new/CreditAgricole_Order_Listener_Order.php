<?php
class CreditAgricole_Order_Listener_Order implements FooCompany_Event_Listener {

    /**
     * Returns a class name representing concrete event class
     *
     * @return string
     */
    public function getAcceptedEventClass()
    {
        return 'order_Model_Event_OrderReady';
    }

    /**
     * Reacts on event
     *
     * @param FooCompany_Event_Event $event
     * @return void
     */
    public function react(FooCompany_Event_Event $event)
    {
        $order = $this->_getOrder($event);
        $oCA = new creditagricole_Model_CA();
		$oCA->prepareXml($order->EisOrderProduct,$order);
		$oCA->sendRequest();
		$oCA->saveApplication();
    }

	protected function _getOrder($event) {
		return $event->getContext()->getOrder();
	}
}
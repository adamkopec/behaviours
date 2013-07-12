<?php

interface PaymentCollectorInterface {

    /**
     * @param Payment $payment
     * @return void
     */
    public function registerPayment(Payment $payment);
}

class PaymentCollector implements PaymentCollectorInterface, ArrayAccess {
	/**
	 * @var Payment[]|array
	 **/
	protected $methods = array();
	
	///...
}
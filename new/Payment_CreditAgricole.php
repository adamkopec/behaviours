<?php

class Payment_CreditAgricole implements Payment {
	
	const CODE = 'creditagricole';
	
	public function getCode() {
		return self::CODE;
	}
	
	public function init() {
		FooCompany_Event_Bus::addListener(new CreditAgricole_Order_Listener_Order());
	}
}
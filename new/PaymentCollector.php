<?php

class PaymentCollector implements ArrayAccess {
	/**
	 * @var Payment[]|array
	 **/
	protected $methods = array();
	
	...
}
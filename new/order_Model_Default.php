<?php

class order_Model_Default {

	public function add(/*...*/) {
		//...
	
		$context = new order_Model_Event_Context_Order($orderModel);
		FooCompany_Event_Bus::broadcast(
			new order_Model_Event_OrderReady(
				$context
			)
		);
	
		$orderModel->save();
		
		//...
	}

}
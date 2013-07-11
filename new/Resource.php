<?php

class FooCompany_Application_Resource_Paymentcollector extends Zend_Application_Resource_ResourceAbstract {
	
	public function init() {
		$collector = $this->getPaymentCollector();
		Zend_Registry::set('paymentCollector', $collector);
		return $collector;
	}
	
	public function getPaymentCollector() {
		$options = $this->getOptions();
		$collector = $this->_getClass();
		
		$payments = isset($options['payments']) ? $options['payments'] : array();
		
		foreach($payments as $payment) {
			$collector->registerPayment($this->_instantiateWithTypeCheck($payment,'Payment'));
		}
		
		return $collector;
	}
	
	protected function _getClass() {
		$options = $this->getOptions();
		if (isset($options['custom'])) {
			return $this->_instantiate($options['custom']);
		} else {
			return new PaymentCollector();
		}
	}
	
	protected function _instantiateWithTypeCheck($config, $type) {
		$newClass = $this->_instantiate($config);
		
		if (is_a($newClass, $type)) {
			return $newClass;
		} else {
			throw new Exception(sprintf("Cannot instantiate class as %s, got: %s", $type, get_class($newClass)));
		}
	}
	
	protected function _instantiate($config) {
		$className = $this->_extractClassName($config);
		$options = $this->_extractOptions($config);
		
		if (!class_exists($className, true)) {
			throw new Exception(sprintf("Cannot instantiate %s, class not available", $className));
		}
		
		return new $className($options);
	}
	
	protected function _extractClassName($config) {
		if (!isset($config['class'])) {
			if (is_string($config)) {
				return $config;
			} else {
				throw new Exception("Cannot instantiate class, name not provided neither directly nor through 'class' option.");
			}
		} else {
			return $config['class'];
		}
	}
	
	protected function _extractOptions($config) {
		$options = null;
		if (isset($config['options'])) {
			$options = $config['options'];
		}
		return $options;
	}
}
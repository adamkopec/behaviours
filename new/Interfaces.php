<?php

interface Payment {
	/**
	 * @return string
	 **/
	public function getCode();
	public function init();
}

interface RedirectingPayment {
	/**
	 * @return string
	 */
	public function getRedirectionTarget();
}
<?php
/**
 * Created by JetBrains PhpStorm.
 * User: adamkopec
 * Date: 12.07.2013
 * Time: 11:18
 */

class Payment_PayU implements Payment, RedirectingPayment {

    /**
     * @return string0
     **/
    public function getCode()
    {
        return 'payu';
    }

    public function init()
    {
        //doNothing();
    }

    /**
     * @return string
     */
    public function getRedirectionTarget()
    {
        return 'http://payu.pl/siakies_parametry/';
    }

}
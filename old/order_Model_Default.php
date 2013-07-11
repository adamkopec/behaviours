<?php
class order_Model_Default extends FooCompany_Model_Attributes_Dynamic {
	
	/**
	<method name="add" ccn="62" ccn2="73" cloc="51" eloc="392" lloc="292" loc="535" ncloc="484" npath="2526296988698438400"/>
	**/
    public function add($shippingCost, $filteredData, $basketId, $arrCurrentBasketInfo, $personData, $isElPayment, $aBasketService = null, $langId = null)
    {
        if ($langId === null) {
            $langId = MtcsLanguages::getDefaultLangId();
        }

        if (!default_Model_Shop::isDetal() && ModelUser::isLogged() && !ModelUser::isNotDemoUser()) { //USER DEMO - nie moze sk³adac zamówienia
            throw new FooCompany_Exception('Demo user cannot order!');
        }
        
        // Informacje dotyczace zamowienia potrzebne do stworzenia notyfikacji
        $aNotifyInformation = array();

        $prsId = ModelUser::getCurrentPersonId();

        //eksportowe
        $zeroVatRate = null;

        //detal lub hurtownia polska
        if (default_Model_Shop::isDetal() || default_Model_Shop::isWholesale()) {
            $zeroVatRate = null;
        } else {
            //price visibility net
            if($personData['prs_price_visibility'] == product_Model_PriceParse::PRICE_VAT_PRESENTATION_NET) {
                $zeroVatRate = 0.00;
            } else { 
                $zeroVatRate = null;
            }
        }

        $currentBasket = $arrCurrentBasketInfo['currentBasket'];
        $max_execution_time = $arrCurrentBasketInfo['max_execution_time'];
        $currentBasketProducts = $arrCurrentBasketInfo['products'];
       // $productSumGross        = $arrCurrentBasketInfo['prd_basket_value_sum_gross'];
        $productSumGross = 0.00;
        $productSum = 0.00;
        $productSumSystemGross = 0.00;
        $productSystemSum = 0.00;

        //ZMIANY OBLICZANIA WARTOSCI BRUTTO
        if(isset($filteredData[basket_Form_Checkout_Shipping::VAT_INVOICE_KEY]) &&
            $filteredData[basket_Form_Checkout_Shipping::VAT_INVOICE_KEY] === 'true')
        {
            $filteredData[basket_Form_Checkout_Shipping::VAT_INVOICE_KEY] = true;
        }

        $fromNetto = (default_Model_Shop::isWholesale()) ? true : false;

        $arrVatSummary = array();
        $arrVatSummarySystemCurr = array();

        // produkty
        foreach ($currentBasketProducts as &$BskProducts) {
            if (isset($zeroVatRate)) {
                $BskProducts['vat_rate'] = $zeroVatRate;
                $BskProducts['itg_vat_id'] = EisVat::ALTM_EXPORT_ZERO_VAT_ID;
                $BskProducts['prd_basket_price_gross'] = $BskProducts['prd_basket_price'];
            }

            if (!isset($arrVatSummary[$BskProducts['vat_rate']])) {
                $arrVatSummary[$BskProducts['vat_rate']] = 0.00;
            }

            if (!isset($arrVatSummarySystemCurr[$BskProducts['vat_rate']])) {
                $arrVatSummarySystemCurr[$BskProducts['vat_rate']] = 0.00;
            }
                
            //zamówienia na fakturê lub w hurtowni
            if ($fromNetto) {
                $BskProducts['prd_basket_value']         = round($BskProducts['bpr_product_count'] * $BskProducts['prd_basket_price'],2);
                $BskProducts['prd_basket_value_gross']   = round($BskProducts['prd_basket_value'] * (1 + $BskProducts['vat_rate']),2);
                   
                $BskProducts['prd_basket_value_system']         = round($BskProducts['bpr_product_count'] * $BskProducts['prd_basket_price_system'],2);
                $BskProducts['prd_basket_value_system_gross']   = round($BskProducts['prd_basket_value_system'] * (1 + $BskProducts['vat_rate']),2);
                    
                $arrVatSummary[$BskProducts['vat_rate']] += $BskProducts['prd_basket_value'] ;
                $arrVatSummarySystemCurr[$BskProducts['vat_rate']] += $BskProducts['prd_basket_value_system'] ;
                 
            //zamówienia na paragon (lub w hurtowni zagranicznej    
            } else { 
                $BskProducts['prd_basket_value_gross']   = round($BskProducts['bpr_product_count'] * $BskProducts['prd_basket_price_gross'],2);                   
                $BskProducts['prd_basket_value']         = round($BskProducts['prd_basket_value_gross']/(1 + $BskProducts['vat_rate']),2);                   
                
                $BskProducts['prd_basket_value_system_gross']   = round($BskProducts['bpr_product_count'] * $BskProducts['prd_basket_price_system_gross'],2);                   
                $BskProducts['prd_basket_value_system']         = round($BskProducts['prd_basket_value_system_gross']/(1 + $BskProducts['vat_rate']),2);                   
                
                $arrVatSummary[$BskProducts['vat_rate']] += $BskProducts['prd_basket_value_gross'] ;
                $arrVatSummarySystemCurr[$BskProducts['vat_rate']] += $BskProducts['prd_basket_value_system_gross'] ;
            }

            $productSum += $BskProducts['prd_basket_value'];
            $productSumGross += $BskProducts['prd_basket_value_gross'];

            $productSystemSum += $BskProducts['prd_basket_value_system'];
            $productSumSystemGross += $BskProducts['prd_basket_value_system_gross'];
        }

        // uslugi dodatkowe
        if ($aBasketService !== null) {
            $oPriceParse = product_Model_PriceParse::getInstance();
            foreach ($aBasketService as $aService) {
                if (isset($zeroVatRate)) {
                    $aService['service_vat_rate'] = $zeroVatRate;
                    $aService['service_vat_id'] = EisVat::ALTM_EXPORT_ZERO_VAT_ID;
                    $aService['service_price_gross'] = $aService['service_price_net'];
                }

                if (!isset($arrVatSummary[$aService['service_vat_rate']])) {
                    $arrVatSummary[$aService['service_vat_rate']] = 0.00;
                }

                if (!isset($arrVatSummarySystemCurr[$aService['service_vat_rate']])) {
                    $arrVatSummarySystemCurr[$aService['service_vat_rate']] = 0.00;
                }

                // zamówienia na fakturê lub w hurtowni
                if ($fromNetto) {
                    $aService['service_value_net'] = round($oPriceParse->parseExactPrice($aService['service_value_net']), 2);
                    $aService['service_value_gross'] = round($oPriceParse->parseExactPrice($aService['service_value_gross']) * (1 + $aService['service_vat_rate']), 2);

                    $aService['service_value_system_net'] = round($aService['service_price_system_net'], 2);
                    $aService['service_value_system_gross'] = round($aService['service_price_system_gross'] * (1 + $aService['service_vat_rate']), 2);

                    $arrVatSummary[$aService['service_vat_rate']] += $aService['service_value_net'] ;
                    $arrVatSummarySystemCurr[$aService['service_vat_rate']] += $aService['service_value_system_net'];
                // zamówienia na paragon lub w hurtowni zagranicznej
                } else {
                    $aService['service_value_gross'] = round($oPriceParse->parseExactPrice($aService['service_value_gross']), 2);                   
                    $aService['service_value_net'] = round($oPriceParse->parseExactPrice($aService['service_value_gross']) / (1 + $aService['service_vat_rate']), 2);

                    $aService['service_value_system_gross'] = round($aService['service_value_gross'],2);
                    $aService['service_value_system_net'] = round($aService['service_value_gross'] / (1 + $aService['service_vat_rate']), 2);                   
                    $arrVatSummary[$aService['service_vat_rate']] += $aService['service_value_system_gross'] ;
                    $arrVatSummarySystemCurr[$aService['service_vat_rate']] += $aService['service_value_system_gross'] ;
                }

                $productSum += $aService['service_value_net'];
                $productSumGross += $aService['service_value_gross'];
                $productSystemSum += $aService['service_value_system_net'];
                $productSumSystemGross += $aService['service_value_system_gross'];
            }
        }

        $oModelBasket = new basket_Model_Default();
        $arrCurrencySum = $oModelBasket->_processShortVatArray($arrVatSummary, $fromNetto);        
        $productSum = $aNotifyInformation['summary'] = $arrCurrencySum['net'];  //wartoœæ zamówienia netto
        $productSumGross = $arrCurrencySum['gross'];  //wartoœæ zamówienia brutto    

        $arrCurrencySumSystem = $oModelBasket->_processShortVatArray($arrVatSummarySystemCurr, $fromNetto);        
        $productSystemSum = $arrCurrencySumSystem['net'];  //wartoœæ zamówienia netto - w walucie systemtowej
        $productSumSystemGross = $arrCurrencySumSystem['gross'];  //wartoœæ zamówienia brutto - w walucie systemtowej

        $oPriceParse = product_Model_PriceParse::getInstance();
        $oCurrency = $oPriceParse->getCurrencyObj();

        $vatRate = basket_Model_Shipping::getVatRateForShippingId($filteredData['fk_osh_id']);
        $arrShippingSum = $oModelBasket->_processShortVatArray(array( (string)$vatRate => $shippingCost), $fromNetto);
        $shippingCostSumGross = $arrShippingSum['gross'];
        $shippingCostSum = $arrShippingSum['net'];

        if (isset($zeroVatRate)) {
            $shippingCostSumGross = $shippingCostSum;
        }

        // Obsluga upustow
        $discount = basket_Model_Discount::getInstance();
        $productSum = $discount->calculatePrice($productSum, false, 'product_sum_value');
        $productSumGross = $discount->calculatePrice($productSumGross, true, 'product_sum_gross');
        $productSystemSum = $discount->calculatePrice($productSystemSum, false, 'product_sum_system');
        $productSumSystemGross = $discount->calculatePrice($productSumSystemGross, true, 'product_sum_system_gross');

        $orderPrice      = $productSum + $oPriceParse->parseExactPrice($shippingCostSum);
        $orderPriceGross = $productSumGross + $oPriceParse->parseExactPrice($shippingCostSumGross);
        
        // --- OBSLUGA TRUSTED SHOPS ---
        // Jezeli usluga Trusted Shops ochrona kupujacego jest aktywna to doliczamy
        // do kosztow jej wartosc
        $tsProduct = false;
        if (EisTsBasketProtection::isActive($currentBasket['bsk_id'])) {
            $tsProduct = EisTsProduct::getSelectedProduct($currentBasket['bsk_id']);
            if ($tsProduct !== false) {
                $orderPrice += $tsProduct['tsp_price'];
                $orderPriceGross += $tsProduct['tsp_price_gross'];
                $productSum  += $tsProduct['tsp_price'];
                $productSumGross += $tsProduct['tsp_price_gross'];;
                $productSystemSum += $tsProduct['tsp_price'];
                $productSumSystemGross += $tsProduct['tsp_price_gross'];
            }
        }
        // --- [END] OBSLUGA TRUSTED SHOPS ---

        $personOrderData = $this->_preparePersonDataForOrder($personData);
        
        $orderId = false;
        $conn = Doctrine_Manager::connection();

        try {
            $conn->beginTransaction(); 

            // GET BASKET RECORD
            $basketRecord = Doctrine::getTable('EisBasket')->find($basketId);
            if (false == $basketRecord || true == $basketRecord->bsk_is_ordered){
                 $conn->rollback();
                 return false;
            }

            $basketRecord->bsk_is_ordered = true;
            $basketRecord->save();            

            $basketOrderData = array(
                'fk_bsk_id' => $basketRecord->bsk_id,
            );

			
			/*********************************************************************************************/
			
            if ($filteredData['fk_orp_id'] == EisOrderPayment::getIdForBehavior(EisOrderPayment::BEHAVE_AS_ZAGIEL)) {
                $filteredData['zagiel_order'] = true;
            } else {
                $filteredData['zagiel_order'] = false;
            }
            
			/*********************************************************************************************/
			
			
            $orderPostData = array(
                'fk_shd_id'         => (isset($filteredData[basket_Form_Checkout_Shipping::ADDRESS_DATA]['fk_shd_id'])) ? $filteredData[basket_Form_Checkout_Shipping::ADDRESS_DATA]['fk_shd_id'] : null,
                'fk_orp_id'         => $filteredData['fk_orp_id'],
                'fk_osh_id'         => $filteredData['fk_osh_id'],
                'ord_note'          => isset($filteredData['ord_note']) ? $filteredData['ord_note'] : '',
                'ord_riv_factor'    => $max_execution_time,
                'ord_source'        => isset($filteredData['ord_source'])? $filteredData['ord_source'] : '',
                
                'ord_price'         => $orderPrice,//$productSum + $oPriceParse->parseExactPrice($shippingCostSum),
                'ord_shipping_price'=> $oPriceParse->parseExactPrice($shippingCostSum),
                'ord_products_price'=> $productSum,

                'ord_eraty'         => $filteredData['zagiel_order'],

                'ord_discount_value'=> (float)$discount->getDiscountValue(),
                'ord_discount_type' => $discount->getType(),
                'ord_loyalty_program_points' => $discount->getPoints(),

                'ord_price_gross'         => $orderPriceGross, //$productSumGross + $oPriceParse->parseExactPrice($shippingCostSumGross),
                'ord_shipping_price_gross'=> $oPriceParse->parseExactPrice($shippingCostSumGross),
                'ord_products_price_gross'=> $productSumGross,

                'ord_shipping_vat_rate'=> $vatRate,

                'ord_system_currency_price' => ($productSystemSum + $shippingCostSum),
                'ord_system_currency_price_gross' => ($productSumSystemGross + $shippingCostSumGross),

                'ord_currency_exchange' => $oPriceParse->getCurrencyExchangeRatio(),
                'ord_add_date'      => date('Y-m-d H:i:s'),
            );
            
            // zapisanie informacji o walucie
            $aNotifyInformation['currency'] = $oCurrency->cur_code;

            $orderPostData['ord_realization_date'] = $this->getRealizationDate($orderPostData);           
            $orderPostData['altm_withheld'] = 'NIE';

            $orderDataArr = array_merge($personOrderData, $basketOrderData);
            $orderDataArr = array_merge($orderPostData, $orderDataArr);

            $orderModel = new EisOrder();
            $orderModel->fromArray($orderDataArr);
            $orderModel->fk_ors_id  = EisOrderStatus::DEFAULT_ORS_ID;
            $orderModel->itg_order_state = EisOrderStatus::getDefaultMcStauts();
            $orderModel->itg_currency_code = EisCurrency::getDefaultMcCurrency();
            $orderModel->save();
            
            $oLoyaltyModel = new basket_Model_Loyalty();
            $oLoyaltyModel->performLoyaltyPointsForOrder($personData['prs_id'], $productSumGross);

            $orderId = $orderModel->ord_id;
            $this->_ordId = $orderId;
            
            $numOrderPriceControlDiff = ''; //do kontroli cen 

            // ORDERED PRODUCTS
            $orderedProducts = array();
            $productLp = 0;
            
            $statsPopularProduct = new AdhocPopularForm();

            $orderedServices = array();
            foreach($currentBasketProducts as $basketPrd) {
                if (isset($zeroVatRate)) {
                    $BskProducts['vat_rate'] = $zeroVatRate;
                    $BskProducts['itg_vat_id'] = EisVat::ALTM_EXPORT_ZERO_VAT_ID;
                    $BskProducts['prd_basket_price_gross'] = $BskProducts['prd_basket_price'];
                }

                $orderProduct = new EisOrderProduct();
                $instId = $basketPrd['EisProductInstance']['product_instance_id'];
                $orderProduct->fk_ord_id                = $orderId;
                $orderProduct->fk_product_instance_id   = $instId;

                $oEisProductInstance = Doctrine::getTable('EisProductInstance')->find($orderProduct->fk_product_instance_id);

                $orderProduct->itg_article_id = $oEisProductInstance->itg_article_id;
                $orderProduct->itg_article_code = $oEisProductInstance->itg_article_code;
                $orderProduct->itg_vend_product_id = $oEisProductInstance->itg_vend_product_id;
                $orderProduct->itg_vend_account = $oEisProductInstance->itg_vend_account;
                $orderProduct->opr_amount = $basketPrd['bpr_product_count'];
                $orderProduct->opr_itg_lp = ++$productLp;
                $orderProduct->itg_dot = $oEisProductInstance->itg_dot;

                if ($fromNetto) {
                    $price_debug[] = 'FROM NETTO ';
                } elseif (!$fromNetto) {
                    $price_debug[] = 'FROM BRUTTO ';
                }

                if (!empty($basketPrd['marketingInfo']['price_debug'])) {
                    $price_debug[]= $basketPrd['marketingInfo']['price_debug'];
                }

                $orderProduct->opr_vat_rate = $basketPrd['vat_rate'];
                if (!isset($BskProducts['itg_vat_id'])) {
                    $orderProduct->itg_vat_id = EisVat::getAltmId($basketPrd['prd_vat_itg_id']);
                } else {
                	$orderProduct->itg_vat_id = $BskProducts['itg_vat_id'];
                }

                $orderProduct->opr_price        = $basketPrd['prd_basket_price'];
                $orderProduct->opr_price_gross  = $basketPrd['prd_basket_price_gross'];
                $orderProduct->opr_value        = $basketPrd['prd_basket_value'];
                $orderProduct->opr_value_gross  = $basketPrd['prd_basket_value_gross'];

                if (isset($basketPrd['prd_old_basket_price']))
                    $orderProduct->opr_old_price            = $basketPrd['prd_old_basket_price'];
                if (isset($basketPrd['prd_old_basket_price_gross']))
                    $orderProduct->opr_old_price_gross      = $basketPrd['prd_old_basket_price_gross'];
                if (isset($basketPrd['prd_old_basket_value']))
                    $orderProduct->opr_old_value            = $basketPrd['prd_old_basket_value'];
                if (isset($basketPrd['prd_old_basket_value_gross']))
                    $orderProduct->opr_old_value_gross      = $basketPrd['prd_old_basket_value_gross'];

                $orderProduct->opr_price_debug          = @serialize($price_debug);
                $orderProduct->opr_execution_time       = $basketPrd['execution_time'];

                $arrProductInfo = EisProduct::getBasicData($oEisProductInstance->fk_product_id, $langId);

                if (!empty($basketPrd['prd_name'])) {
                    $orderProduct->opr_prd_name = $basketPrd['prd_name'];
                } else {
                    if (is_object($oEisProductInstance)) {
                        if(isset($arrProductInfo['prod_name'])) {
                           $orderProduct->opr_prd_name = $arrProductInfo['prod_name'];
                        }
                    }
                }

                # uslugi - UWAGA! $basketPrd jest posortowany po bpr_parent_id wiec zakladam ze produkty uslugi sa na koncu listy
                if($basketPrd['bpr_parent_id']){
                    if(isset($orderedServices[$basketPrd['bpr_parent_id']])){
                        $orderProduct->opr_parent_id = $orderedServices[$basketPrd['bpr_parent_id']];
                    }
                }
                
                $orderProduct->save();
                
                $orderProduct->booking_date = date("Y-m-d H:i:s", strtotime(Zend_Date::now()));
                $orderProduct->booking_expire_date = date("Y-m-d H:i:s", strtotime(Zend_Date::now()->add(1, Zend_Date::HOUR)));
                $orderProduct->booking_modify_date = date("Y-m-d H:i:s", strtotime(Zend_Date::now()));
                $orderProduct->itg_booking_id = EisOrderProduct::setBookingId($orderProduct->opr_id);
                
                if($orderProduct->isModified()) {
                    $orderProduct->save();
                }
                
                # uslugi
                if(!$basketPrd['bpr_parent_id']){
                    $orderedServices[$instId] = $orderProduct->opr_id;
                }
                
                $priceDiff = EisProductPriceSell::getPriceControlDiff($instId, $basketPrd['execution_time'], $basketPrd['prd_basket_price']);
                if($priceDiff > 0) {
                    $numOrderPriceControlDiff .= '['.$oEisProductInstance->itg_article_code.']: '.$priceDiff.', ';
                }
                
                $orderedProducts[] = $orderProduct;
                
                $aOrderProduct = $orderProduct->toArray();
                $aOrderProduct['EisProductInstance'] = $oEisProductInstance->toArray();
                $aOrderProduct['EisProductInstance']['desc_value'] = $arrProductInfo['desc_value'];
                
                $aOrderedProducts[] = $aOrderProduct;
                
                $statsPopularProduct->registerStatsProduct($aOrderProduct);
            }

            // dodawanie uslug dodatkowych
            if ($aBasketService !== null) {
                foreach ($aBasketService as $aService) {
                    $oOrderService = new EisOrderService();
                    $oOrderService->fk_service_id = (int)$aService['fk_service_id'];
                    $oOrderService->fk_ord_id = (int)$orderId;
                    $oOrderService->service_name = $aService['service_name'];
                    $oOrderService->service_price_net = (float)$aService['service_price_net'];
                    $oOrderService->service_price_gross = (float)$aService['service_price_gross'];
                    $oOrderService->service_value_net = (float)$aService['service_value_net'];
                    $oOrderService->service_value_gross = (float)$aService['service_value_gross'];
                    $oOrderService->service_amount = (int)$aService['service_amount'];
                    $oOrderService->service_vat_rate = (float)$aService['service_vat_rate'];
                    $oOrderService->save();
                }
            }

            
            // --- OBSLUGA TRUSTED SHOPS ---
            // Jezeli jest wlaczona usluga Trusted Shops ochrona kupujacego w koszyku
            // to dodajemy specialny produkt typu TECHECOMMERCE do zamowienia i
            // dodajemy informacje o ochronie kupujacego.
            $trustedShopsIsActive = EisTsAccessData::hasActiveForlang($langId);
            $protectionForBasketIsActive = EisTsBasketProtection::isActive($currentBasket['bsk_id']);

            if ($trustedShopsIsActive && $protectionForBasketIsActive) {
                $tsAddedProduct = $this->_addTrustedShopsSecureProductToOrder($productLp, $tsProduct);
                
                if($tsAddedProduct) {
                    $aOrderedProducts[] = $tsAddedProduct;
                    // Rejestrowanie zamowienia w usludze
                    EisTsProtection::addForOrder($orderId, $langId,$tsProduct['tsp_price_gross']);
                }
            }
            // --- [END] OBSLUGA TRUSTED SHOPS ---
            
            $orderModel->save();

            /** additionals */
            // SHIPPING ADDRESS
            if(isset($filteredData[basket_Form_Checkout_Shipping::ADDRESS_DATA])) 
            {
                $shippingAddrData = $filteredData[basket_Form_Checkout_Shipping::ADDRESS_DATA];
            }            

            $orderShpAddressMdl = new EisOrderShippingAddress();
            $orderShpAddressMdl->fk_ord_id = $orderId;

            $orderShpAddressMdl->fk_country_id = $shippingAddrData['fk_country_id'];
            $orderShpAddressMdl->fk_province_id = $shippingAddrData['fk_province_id'];
            
            //mprazmowski - bypas (wystepowal blad przy fromArray() !!! )
            foreach($shippingAddrData as $key => $value) {
                if(is_int($key))
                {
                    unset($shippingAddrData[$key]);
                }
            }
            $orderShpAddressMdl->fromArray($shippingAddrData);
            $orderShpAddressMdl->save();        

            // VAT INVOICE                
            if ($oCurrency->cur_id != EisCurrency::getDefault()) { // jesli inna niz polska waluta (systemowa) - wystawiamy fakture i dajemy atrybut
                $filteredData[FooCompany_Form_Dynamic::DYNAMIC_ATTR_KEY][self::ALTM_ORDER_HAS_INVOICE_ATTR_ID] = self::ALTM_ORDER_HAS_INVOICE_ATTR_VALUE; 
                if (isset($filteredData[basket_Form_Checkout_Shipping::VAT_INVOICE_KEY]) && 'true' != $filteredData[basket_Form_Checkout_Shipping::VAT_INVOICE_KEY]) { //jesli user nie chcia³ faktury -wymuszamy dane do faktury
                    $filteredData[basket_Form_Checkout_Shipping::INVOICE_DATA] = basket_Model_Shipping::generateInvoiceAddressFromUserContext($personData);
                    $filteredData[basket_Form_Checkout_Shipping::VAT_INVOICE_KEY] = 'true';
                }
            }
            
            $filteredData[FooCompany_Form_Dynamic::DYNAMIC_ATTR_KEY][self::ALTM_ORDER_ORDER_TYPE_ID] = $this->_getOrderTypeAvail();
            
            $aInvoiceData = array();
            if (isset($filteredData[basket_Form_Checkout_Shipping::VAT_INVOICE_KEY]) && 'true' == $filteredData[basket_Form_Checkout_Shipping::VAT_INVOICE_KEY]) {
                $aInvoiceData = $this->_saveInvoiceAddress($filteredData, $personData);
            }

			/*********************************************************************************************/
			
            {{ # Credit Agricole - Lukas Raty
                if($orderModel->fk_orp_id == EisOrderPayment::getIdForBehavior(EisOrderPayment::BEHAVE_AS_CREDIT_AGRICOLE)){
                    $oCA = new creditagricole_Model_CA();
                    $oCA->prepareXml($aOrderedProducts,$orderModel);
                    $oCA->sendRequest();
                    $oCA->saveApplication();
                }
            }}

			/*********************************************************************************************/
			
            {{ # Zagiel eRaty
                if($orderModel->ord_eraty){
                    $oZagiel = new zagiel_Model_Zagiel();
                    $oZagiel->addForOrder($orderModel);
                    $oZagiel->addIdentifierToSession();
                }
            }}

            {{ //mprazmowski AXAPTA SYNC - PREPARE DATA
                $serviceModelOrderData = $this->_prepareServiceModelDataFirst(
                    $personData,
                    $orderModel, 
                    $orderShpAddressMdl, 
                    $aOrderedProducts,
                    $aInvoiceData
                );
            }}
            
            $mcOrderNumber = sprintf('%s/%s/%s', 'MC', Zend_Registry::get('shop_customer_source_prefix'), $orderModel->ord_id);

            $this->notifyUser($prsId, $orderModel->ord_id, $mcOrderNumber, true);

            /** commit */
            $conn->commit();

            // ustawianie cookie mowiacego o tym, ze koszyk jest pusty
            basket_Model_Default::clearCookieFlag();
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

        {{ //mprazmowski AXAPTA BOOKING SYNC
            try {
//                 $ModelSynchronize->synchronizeBooking($orderModel->ord_id);
                 $serviceModel = new service_Service_Model_AddOrder('webservice', 'outsideService', 'ExecuteQuery');
                 $serviceModel->setConnectionTimeout(service_Service_Model_AddOrder::CONNECTION_TIMEOUT);
                 $serviceModel->setSocketTimeout(service_Service_Model_AddOrder::SOCKET_TIMEOUT);
                 $serviceModel->run($serviceModelOrderData);
            } catch (FooCompany_Soap_Exception $e) {
                FooCompany_Log::exception($e);
            } catch (Exception $e) {
                FooCompany_Log::exception($e);
            }
        }}
        
        //ustawiamy rabat liniowy klienta! jeœli by³a promocja odpowiednia
        $sessionNamespace = new Zend_Session_Namespace(marketing_Model_Default::LINE_DISC_SESSION_NAMESPACE);
        if (is_object($sessionNamespace) && !empty($sessionNamespace->disc) && is_numeric($personData['prs_id']) && $personData['prs_id'] > 0) {
            $oPerson = Doctrine::getTable('EicPerson')->find($personData['prs_id']);
            if(is_object($oPerson)) {
                $disc = ((float)$sessionNamespace->disc > 0) ? (float)$sessionNamespace->disc : null;
                $oPerson->prs_discount = $disc;
                $oPerson->save();
            }
            $sessionNamespace->disc = null;
            unset($oPerson);
        }

        return $orderId;
    }
    
}
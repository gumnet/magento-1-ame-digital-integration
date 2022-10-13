<?php
/**
 * @author Gustavo Ulyssea - gustavo.ulyssea@gmail.com
 * @copyright Copyright (c) 2020-2021 GumNet (https://gum.net.br)
 * @package GumNet AME Magento 1.9
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY GUM Net (https://gum.net.br). AND CONTRIBUTORS
 * ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED
 * TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 * PURPOSE ARE DISCLAIMED.  IN NO EVENT SHALL THE FOUNDATION OR CONTRIBUTORS
 * BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

class Ame_Amepayment_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        $helperDbame = Mage::helper('amepayment/Dbame');
        $helperMailerame = Mage::helper('amepayment/Mailerame');
        $helperApi = Mage::helper('amepayment/Api');
        $helperGumapi = Mage::helper('amepayment/Gumapi');
        $storeid = Mage::app()->getStore()->getStoreId();
        $environment = Mage::getStoreConfig('ame/general/environment', $storeid);
        Mage::log("INFO","AME Callback starting...");
        $json = $this->getRequest()->getRawBody();
        $helperDbame->insertCallback($json);
        if(!$this->isJson($json)){
            Mage::log("ERROR","AME Callback is not json");
            $helperMailerame->mailSender("gustavo@gum.net.br","AME Callback is not json",$json);
            return;
        }
        $input = json_decode($json,true);
        Mage::log("INFO",print_r($input,true));
        // verify if id exists
        if(!array_key_exists('id',$input)){
            Mage::log("ERROR","AME Callback AME ID not found in JSON");
            $helperMailerame->mailSender("gustavo@gum.net.br","AME Callback AME ID not found",$json);
            return;
        }
        $ame_order_id = $input['attributes']['orderId'];

        $incrId = $helperDbame->getOrderIncrementId($ame_order_id);
        if(!$incrId){
            Mage::log("ERROR","AME Callback Increment ID not found in the database");
            $helperMailerame->mailSender("gustavo@gum.net.br","AME Callback Increment ID not found",$json);
            return;
        }

        Mage::log("AME Callback getting Magento Order for ".$incrId);
        $order = Mage::getModel('sales/order')->loadByIncrementId($incrId);

        Mage::log("AME Environment ".$environment);

        if($input['status']=="AUTHORIZED"){
            Mage::log("INFO","AME Callback recording transaction for ".$ame_order_id);
            $helperDbame->insertTransaction($input);
            $helperGumapi->queueTransaction($json);
            Mage::log("INFO","AME Callback Queue transaction");
//            $this->invoiceOrder($order);
//            $helperMailerame->sendDebug("Pagamento AME recebido pedido ".$order->getIncrementId(),"AME ID: ".$ame_order_id);
//            Mage::log("INFO", "AME Callback capturing...");
//            $capture = $helperApi->captureOrder($ame_order_id);
//            $ame_transaction_id = $helperDbame->getTransactionIdByOrderId($ame_order_id);
//            $amount = $helperDbame->getTransactionAmount($ame_transaction_id);
//            $helperGumapi->captureTransaction($ame_transaction_id,$ame_order_id,$amount);
        }
        Mage::log("INFO","AME Callback ended.");
        die();
    }

    public function cancelOrder($order){
        $order->cancel()->save();
    }

    public function invoiceOrder($order)
    {
        if ($order->canInvoice()) {
            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
            $invoice->register();
            $invoice->getOrder()->setIsInProcess(true);
            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $transactionSave->save();
            $order->addStatusHistoryComment('AME payment success - invoice #'.$invoice->getId().'.', $invoice->getId());
            $order->setState('processing')->setStatus('processing')->save();
        }
    }

    public function isJson($string) {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }
    public function captureAction()
    {
        $helperDbame = Mage::helper('amepayment/Dbame');
        $helperMailerame = Mage::helper('amepayment/Mailerame');
        $storeid = Mage::app()->getStore()->getStoreId();
        $helperApi = Mage::helper('amepayment/Api');
        if (Mage::getStoreConfig('ame/general/environment', $storeid) == 3) {
            $helperApi = Mage::helper('amepayment/SensediaApi');
        }
        $helperGumapi = Mage::helper('amepayment/Gumapi');

        $request_transaction_id = $this->getRequest()->getParam('transactionid');
        $request_ame_order_id = $this->getRequest()->getParam('orderid');
        if(!$transaction_id = $helperDbame->getTransactionIdByOrderId($request_ame_order_id)){
            die("ERROR transaction not found");
        }
        if($transaction_id != $request_transaction_id){
            die("ERROR wrong order id");
        }
        $incrId = $helperDbame->getOrderIncrementId($request_ame_order_id);
        $order = Mage::getModel('sales/order')->loadByIncrementId($incrId);

        $this->invoiceOrder($order);
        $amount = $helperDbame->getTransactionAmount($request_transaction_id);
        $helperGumapi->captureTransaction($request_transaction_id,$request_ame_order_id,$amount);
//        $helperMailerame->sendDebug("Pagamento AME recebido pedido ".$order->getIncrementId(),"AME ID: ".$ame_order_id);
        Mage::log("INFO", "AME Callback capturing...");
//        $capture = $helperApi->captureOrder($ame_order_id);
//        $ame_transaction_id = $helperDbame->getTransactionIdByOrderId($ame_order_id);
//        $amount = $helperDbame->getTransactionAmount($ame_transaction_id);
    }
}

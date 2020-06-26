<?php

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
            $helperMailerame->mailSender("gustavo@gumnet.com.br","AME Callback is not json",$json);
            return;
        }
        $input = json_decode($json,true);
        Mage::log("INFO",print_r($input,true));
        // verify if id exists
        if(!array_key_exists('id',$input)){
            Mage::log("ERROR","AME Callback AME ID not found in JSON");
            $helperMailerame->mailSender("gustavo@gumnet.com.br","AME Callback AME ID not found",$json);
            return;
        }
        $ame_order_id = $input['attributes']['orderId'];

        $incrId = $helperDbame->getOrderIncrementId($ame_order_id);
        if(!$incrId){
            Mage::log("ERROR","AME Callback Increment ID not found in the database");
            $helperMailerame->mailSender("gustavo@gumnet.com.br","AME Callback Increment ID not found",$json);
            return;
        }

        Mage::log("AME Callback getting Magento Order for ".$incrId);
        $order = Mage::getModel('sales/order')->loadByIncrementId($incrId);

        Mage::log("AME Environment ".$environment);

        if($input['status']=="AUTHORIZED" && $environment != '1'){
            Mage::log("INFO","AME Callback recording transaction for ".$ame_order_id);
            $helperDbame->insertTransaction($input);
            Mage::log("INFO","AME Callback invoicing Magento order ".$incrId);
            $this->invoiceOrder($order);
            $helperMailerame->sendDebug("Pagamento AME recebido pedido ".$order->getIncrementId(),"AME ID: ".$ame_order_id);
            Mage::log("INFO", "AME Callback capturing...");
            $capture = $helperApi->captureOrder($ame_order_id);
            $ame_transaction_id = $helperDbame->getTransactionIdByOrderId($ame_order_id);
            $amount = $helperDbame->getTransactionAmount($ame_transaction_id);
            $helperGumapi->captureTransaction($ame_transaction_id,$ame_order_id,$amount);
        }
        elseif($input['status']=="AUTHORIZED" && $environment == '1') {
            Mage::log("INFO","AME Callback recording transaction for ".$ame_order_id);
            $helperDbame->insertTransaction($input);
            $order->addStatusHistoryComment('AME payment authorized');
            $order->save();
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
}

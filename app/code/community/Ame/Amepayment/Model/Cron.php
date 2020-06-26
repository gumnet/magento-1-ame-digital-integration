<?php

class Ame_Amepayment_Model_Cron
{
    public function captureOrder(){
        $table_prefix = Mage::getConfig()->getTablePrefix();
        $order_table = $table_prefix.'sales_flat_order';
        $on_condition = "main_table.parent_id = $order_table.entity_id";
        $orderCollection =  Mage::getModel('sales/order_payment')->getCollection()->addFieldToFilter('method',"ame")->addFieldToFilter('status',"pending");
        $orderCollection->getSelect()->join($order_table,$on_condition);
        foreach ($orderCollection as $order){
            $helperApi = Mage::helper('amepayment/Api');
            $helperDbame = Mage::helper('amepayment/Dbame');
            $helperGumapi = Mage::helper('amepayment/Gumapi');
            $capture = $helperApi->captureOrder($helperDbame->getAmeIdByIncrementId($order->getIncrementId()));
            if(!$capture){
                $order->addStatusHistoryComment('AME Cron Capture Fail');
            }else{
                $this->invoiceOrder($order);
                $ame_order_id = $helperDbame->getAmeIdByIncrementId($order->getIncrementId());
                Mage::log("INFO", "AME Cron capturing...");
                $ame_transaction_id = $helperDbame->getTransactionIdByOrderId($ame_order_id);
                $amount = $helperDbame->getTransactionAmount($ame_transaction_id);
                $helperGumapi->captureTransaction($ame_transaction_id,$ame_order_id,$amount);
            }
        }
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
}


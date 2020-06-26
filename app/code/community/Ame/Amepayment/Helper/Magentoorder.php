<?php

class Ame_Amepayment_Helper_Magentoorder extends Mage_Core_Helper_Abstract
{
    public function invoiceOrder($orderId)
    {
        $order = Mage::getModel('sales/order')->load($orderId);
        if($order->canInvoice()) {
            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            $invoice->getOrder()->setIsInProcess(true);
            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $transactionSave->save();
            $order->addStatusHistoryComment('AME payment success - invoice #%1.', $invoice->getId());
            $order->setState('processing')->setStatus('processing')->save();
        }
    }
}

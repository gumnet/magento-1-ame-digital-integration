<?php
/**
 * @author Gustavo Ulyssea - gustavo.ulyssea@gmail.com
 * @copyright Copyright (c) 2020 GumNet (https://gum.net.br)
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

class Ame_Amepayment_Model_Cron
{
    public function captureOrder(){
        $table_prefix = Mage::getConfig()->getTablePrefix();
        $order_table = $table_prefix.'sales_flat_order';
        $on_condition = "main_table.parent_id = $order_table.entity_id";
        $orderCollection =  Mage::getModel('sales/order_payment')->getCollection()->addFieldToFilter('method',"ame")->addFieldToFilter('status',"pending");
        $orderCollection->getSelect()->join($order_table,$on_condition);
        foreach ($orderCollection as $order){
            $order1 = Mage::getModel('sales/order')->load($order->getId());
            $helperApi = Mage::helper('amepayment/Api');
            $helperDbame = Mage::helper('amepayment/Dbame');
            $helperGumapi = Mage::helper('amepayment/Gumapi');
            $capture = $helperApi->captureOrder($helperDbame->getAmeIdByIncrementId($order->getIncrementId()));
            if(!$capture){
                $order1->addStatusHistoryComment('AME Cron Capture Fail');
            }else{
                $this->invoiceOrder($order1);
                $ame_order_id = $helperDbame->getAmeIdByIncrementId($order1->getIncrementId());
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


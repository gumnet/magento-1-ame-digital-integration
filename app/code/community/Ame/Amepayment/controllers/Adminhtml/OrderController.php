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

class Ame_Amepayment_Adminhtml_OrderController extends Mage_Adminhtml_Controller_Action
{

    public function indexAction()
    {
        //todo
    }

    public function CancelAction(){
        $order_id = $this->getRequest()->getParam('order_id');
        $order = Mage::getModel('sales/order')->load($order_id);
        if($order->canCancel()) {
            $helperApi = Mage::helper('amepayment/Api');
            $helperDbame = Mage::helper('amepayment/Dbame');
            $cancel = $helperApi->cancelOrder($helperDbame->getAmeIdByIncrementId($order->getIncrementId()));
            if (!$cancel) {
                Mage::getSingleton('adminhtml/session')->addError('Não foi possível cancelar seu pedido, tente mais tarde.');
            } else {
                $order->cancel()->save();
                Mage::getSingleton('adminhtml/session')->addSuccess('Pedido cancelado com sucesso.');
            }
        }else{
            Mage::getSingleton('adminhtml/session')->addError('Não foi possível cancelar seu pedido.');
        }
        $this->_redirect('adminhtml/sales_order/view', array('order_id' => $order_id));
    }

    public function CaptureAction(){
        $order_id = $this->getRequest()->getParam('order_id');
        $order = Mage::getModel('sales/order')->load($order_id);
        $helperApi = Mage::helper('amepayment/Api');
        $helperDbame = Mage::helper('amepayment/Dbame');
	    $helperGumapi = Mage::helper('amepayment/Gumapi');
        $capture = $helperApi->captureOrder($helperDbame->getAmeIdByIncrementId($order->getIncrementId()));
        if(!$capture){
            Mage::getSingleton('adminhtml/session')->addError('Não foi possível capturar seu pedido, tente mais tarde.');
        }else{
	    $this->invoiceOrder($order);
	    $ame_order_id = $helperDbame->getAmeIdByIncrementId($order->getIncrementId());
            Mage::log("INFO", "AME Admin capturing...");
            $ame_transaction_id = $helperDbame->getTransactionIdByOrderId($ame_order_id);
            $amount = $helperDbame->getTransactionAmount($ame_transaction_id);
            $helperGumapi->captureTransaction($ame_transaction_id,$ame_order_id,$amount);
            Mage::getSingleton('adminhtml/session')->addSuccess('Pedido capturado com sucesso.');
        }
        $this->_redirect('adminhtml/sales_order/view', array('order_id' => $order_id));
    }

    public function RefundAction(){
        $order_id = $this->getRequest()->getParam('order_id');
        $order = Mage::getModel('sales/order')->load($order_id);
        $helperApi = Mage::helper('amepayment/Api');
        $helperDbame = Mage::helper('amepayment/Dbame');
        $cancel = $helperApi->cancelOrder($helperDbame->getAmeIdByIncrementId($order->getIncrementId()));
        $cancel = true;
        if(!$cancel){
            Mage::getSingleton('adminhtml/session')->addError('Não foi possível cancelar seu pedido, tente mais tarde.');
        }else{
            Mage::getSingleton('adminhtml/session')->addSuccess('Pedido cancelado com sucesso.');
        }
        $this->_redirect('adminhtml/sales_order/view', array('order_id' => $order_id));
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

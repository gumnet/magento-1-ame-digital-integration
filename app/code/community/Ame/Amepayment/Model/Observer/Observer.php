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

class Ame_Amepayment_Model_Observer_Observer
{
    public function createOrder($observer)
    {
        $orderIds = $observer->getEvent()->getOrderIds();
        foreach($orderIds as $orderid){
            $order = Mage::getModel('sales/order')->load($orderid);
        }
        $payment = $order->getPayment();
        $method = $payment->getMethod();
        if($method=="ame") {
            //$order->setState('pending_payment')->setStatus('pending_payment');
            $order->save();
            $storeid = Mage::app()->getStore()->getStoreId();
            $helper = Mage::helper('amepayment/Api');
            if (Mage::getStoreConfig('ame/general/environment', $storeid) == 3) {
                $helper = Mage::helper('amepayment/SensediaApi');
            }
            $helper->createOrder($order);
        }
        return $this;
    }
    public function cancelOrder($observer)
    {
        $order = $observer->getOrder();
        if (!$order->isCanceled() || $order->getOrigData('state')=='canceled') {
            return $this;
        }
        $helperDbame = Mage::helper('amepayment/Dbame');
        if ($helperDbame->getOrderStatus($order->getIncrementId())=='canceled') {
            return $this;
        }
        $storeid = Mage::app()->getStore()->getStoreId();
        if (Mage::getStoreConfig('ame/general/environment', $storeid) != 3) {
            return $this;
        }
        $helper = Mage::helper('amepayment/SensediaApi');
        $ameId = $helperDbame->getAmeIdByIncrementId($order->getIncrementId());
        if (!$helper->cancelOrder($ameId)) {
            return $this;
        }
        $helperDbame->cancelOrder($order->getIncrementId());
    }

    public function refundOrder($observer)
    {
        $refund = $observer->getEvent()->getCreditmemo();
        $order = $refund->getOrder();
        $payment = $order->getPayment();
        $method = $payment->getMethod();
        if($method=="ame") {
            $valor = $refund->getGrandTotal();
            $storeid = Mage::app()->getStore()->getStoreId();
            $helperApi = Mage::helper('amepayment/Api');
            if (Mage::getStoreConfig('ame/general/environment', $storeid) == 3) {
                $helperApi = Mage::helper('amepayment/SensediaApi');
            }

            $helperDbame = Mage::helper('amepayment/Dbame');
            $refund = $helperApi->refundOrder($helperDbame->getAmeIdByIncrementId($order->getIncrementId()), $valor);
            if ($refund) {
                $helperDbame->insertRefund($helperDbame->getAmeIdByIncrementId($order->getIncrementId()), $refund[1], $refund[0]['operationId'], $valor, $refund[0]['status']);
            } else {
                Mage::throwException('Não foi possível gerar estorno');
            }
        }
        return $this;
    }

    public function updateOrders(){
        //todo
    }

    public function addAmeButtons($observer){
        $storeid = Mage::app()->getStore()->getStoreId();
        $container = $observer->getBlock();
        $environment = Mage::getStoreConfig('ame/general/environment', $storeid);
        if(null !== $container && $container->getType() == 'adminhtml/sales_order_view') {
            $order = $container->getOrder();
            $incrementId = $order->getIncrementId();
            $status = $order->getStatus();
            $coreHelper = Mage::helper('core');
            $payment = $order->getPayment()->getMethod();
            if ($payment == 'ame' && $status == 'pending') {
                $container->removeButton('order_invoice');
                $container->removeButton('void_payment');
                $helperDbame = Mage::helper('amepayment/Dbame');
                if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/cancel') && $order->canCancel() && $helperDbame->transactionIdExists($incrementId)) {
                    $container->removeButton('order_cancel');
                    $confirmationMessage = $coreHelper->jsQuoteEscape(
                        Mage::helper('sales')->__('Are you sure you want to cancel this order?')
                    );
                    $cancelUrl = Mage::helper('adminhtml')->getUrl('ame_amepayment/adminhtml_order/cancel', array('order_id' => $order->getId()));
                    $container->addButton('ame_order_cancel', array(
                        'label' => Mage::helper('sales')->__('Cancel'),
                        'onclick' => 'deleteConfirm(\'' . $confirmationMessage . '\', \'' . Mage::helper('adminhtml')->getUrl('ame_amepayment/adminhtml_order/cancel', array('order_id' => $order->getId())) . '\')'
                    ));
                }


                if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/invoice') && $order->canInvoice() && $environment == '1') {
                    $container->addButton('order_invoice', array(
                        'label' => 'Invoice',
                        'onclick' => 'setLocation(\'' . Mage::helper('adminhtml')->getUrl('ame_amepayment/adminhtml_order/capture', array('order_id' => $order->getId())) . '\')',
                        'class' => 'go'
                    ));
                }
            }
        }
        return $this;
    }

    public function insertAmeLogo($observer)
    {
        $block = $observer->getBlock();
        if ($block instanceof Mage_Checkout_Block_Onepage_Payment_Methods) {
            $transport = $observer->getTransport();
            $html = $transport->getHtml();
            $logo = Mage::getDesign()->getSkinUrl('images/ame/ame-logo.png', array('_secure'=>true));
            $logo = "<label for=\"p_method_ame\"><img src=$logo ></label>";
            if(strpos($html, 'dt_method_ame') !== false){
                $newHtml = str_replace("<label for=\"p_method_ame\">AME </label>", $logo, $html);
                $transport->setHtml($newHtml);
            }

        }
    }
}



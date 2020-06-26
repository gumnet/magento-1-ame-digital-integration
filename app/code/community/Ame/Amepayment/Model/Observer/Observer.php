<?php

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
            $helper = Mage::helper('amepayment/Api');
            $helper->createOrder($order);
        }
        return $this;
    }

    public function refundOrder($observer)
    {
        $refund = $observer->getEvent()->getCreditmemo();
        $order = $refund->getOrder();
        $payment = $order->getPayment();
        $method = $payment->getMethod();
        if($method=="ame") {
            $valor = $refund->getGrandTotal();
            $helperApi = Mage::helper('amepayment/Api');
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



<?php


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

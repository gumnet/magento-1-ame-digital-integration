<?php

class Ame_Amepayment_Block_Onepage_Success extends Mage_Checkout_Block_Onepage_Success
{
    public function getCashbackValue(){
        $total_discount = 0;
        $items = $this->getOrder()->getAllItems();
        foreach ($items as $item) {
            $total_discount = $total_discount + $item->getDiscountAmount();
        }
        return ($this->getPrice()-abs($total_discount)) * $this->getCashbackPercent() * 0.01;
    }

    public function getCashbackPercent(){
        $helper = Mage::helper('amepayment/Api');
        return $helper->getCashbackPercent();
    }

    public function getPrice(){
        return $this->getOrder()->getGrandTotal();
    }

    public function getOrder()
    {
        $lastOrderId = Mage::getSingleton('checkout/session')->getLastOrderId();
        return Mage::getModel('sales/order')->load($lastOrderId);
    }

    public function getCustomerId()
    {
        return Mage::getSingleton('customer/session')->getCustomerId();
    }

    public function getDeepLink(){

        $increment_id = $this->getOrder()->getIncrementId();
        $sql = "SELECT deep_link FROM ame_order WHERE increment_id = ".$increment_id;
        $resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');
        $qr = $readConnection->fetchOne($sql);
        return $qr;
    }

    public function getQrCodeLink(){
        $increment_id = $this->getOrder()->getIncrementId();
        $sql = "SELECT qr_code_link FROM ame_order WHERE increment_id = ".$increment_id;
        $resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');
        $qr = $readConnection->fetchOne($sql);
        return $qr;
    }

    public function getPaymentMethod(){
        return $this->getOrder()->getPayment()->getMethod();
    }
}

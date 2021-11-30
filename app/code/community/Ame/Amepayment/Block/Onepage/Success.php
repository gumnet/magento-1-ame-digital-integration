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

class Ame_Amepayment_Block_Onepage_Success extends Mage_Checkout_Block_Onepage_Success
{
    public function getCashbackValue(){
        $increment_id = $this->getOrder()->getIncrementId();
        $sql = "SELECT cashback_amount FROM ame_order WHERE increment_id = '".$increment_id."'";
        $resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');
        $value = $readConnection->fetchOne($sql);
        return $value * 0.01;
    }
//    public function getCashbackPercent(){
//        $helper = Mage::helper('amepayment/Api');
//        return $helper->getCashbackPercent();
//    }
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
        $sql = "SELECT deep_link FROM ame_order WHERE increment_id = '".$increment_id."'";
        $resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');
        $qr = $readConnection->fetchOne($sql);
        return $qr;
    }

    public function getQrCodeLink(){
        $increment_id = $this->getOrder()->getIncrementId();
        $sql = "SELECT qr_code_link FROM ame_order WHERE increment_id = '".$increment_id."'";
        $resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');
        $qr = $readConnection->fetchOne($sql);
        return $qr;
    }

    public function getPaymentMethod(){
        return $this->getOrder()->getPayment()->getMethod();
    }
}

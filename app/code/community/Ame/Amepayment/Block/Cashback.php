<?php

class Ame_Amepayment_Block_Cashback extends Mage_Core_Block_Template
{
    public function getCashbackValue($product){
        $value = $product->getFinalPrice() * $this->getCashbackPercent() * 0.01;
        return $value;
    }

    public function getCashbackPercent(){
        $storeid = Mage::app()->getStore()->getStoreId();
        $percent = Mage::getStoreConfig('ame/general/cashback_value', $storeid);
        return $percent;
    }



}

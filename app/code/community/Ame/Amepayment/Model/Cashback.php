<?php

class Ame_Amepayment_Model_Cashback extends Mage_Core_Model_Config_Data
{
    public function save()
    {
        $cashback = $this->getValue();
        if(empty($cashback)){
            Mage::throwException('Você precisa preencher o campo com o Cashback');
        }
        if(is_numeric($cashback)){
            if($cashback < 0 || $cashback > 50) {
                Mage::throwException('Formato inválido do campo Cashback');
            }
        }else{
            Mage::throwException('Formato inválido do campo Cashback');
        }
        return parent::save();
    }
}


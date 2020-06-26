<?php

class Ame_Amepayment_Model_Apipassword extends Mage_Core_Model_Config_Data
{
    public function save()
    {
        $apipass = $this->getValue();
        if(empty($apipass)){
            Mage::throwException('Você precisa preencher o campo com a API Password');
        }
        return parent::save();
    }
}


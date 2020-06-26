<?php

class Ame_Amepayment_Model_Apiuser extends Mage_Core_Model_Config_Data
{
    public function save()
    {
        $apiuser = $this->getValue(); //get the value from our config
        if(empty($apiuser)){
            Mage::throwException('Você precisa preencher o campo com a API User');
        }
        return parent::save();
    }
}


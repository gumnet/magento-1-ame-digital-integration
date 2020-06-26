<?php

class Ame_Amepayment_Helper_Mailerame extends Mage_Core_Helper_Abstract
{
    public function sendDebug($subject,$message){
        $storeid = Mage::app()->getStore()->getStoreId();
        if(!Mage::getStoreConfig('ame/debug/debug_email_addresses', $storeid)) return;

        $emails = Mage::getStoreConfig('ame/debug/debug_email_addresses', $storeid);
        $emails_array = explode(",",$emails);
        foreach($emails_array as $email){
            if (\Zend_Validate::is(trim($email), 'EmailAddress')) {
                $this->mailSender(trim($email),$subject,$message);
            }
        }
    }
    public function mailSender($to,$subject,$message)
    {
        $headers = "From: GumNet <contato@gumnet.com.br>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        mail($to,$subject,$message,$headers);
        return true;
    }
}

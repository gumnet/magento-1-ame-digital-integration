<?php
//TODO - TESTAR AS CHAMADAS DAS INFORMAÇÕES DO DASHBOARD

class Ame_Amepayment_Helper_Gumapi extends Mage_Core_Helper_Abstract
{

    public function getApiUrl(){
        return "https://apiame.gum.net.br";
    }

    public function captureTransaction($ame_transaction_id,$ame_order_id,$amount)
    {
        $result = $ame_transaction_id . "|".$amount;
        return $this->gumRequest("capturetransaction",$result,$ame_order_id);
    }
    public function createOrder($input,$result)
    {
        $this->gumRequest("createorder",$result,$input);
        return true;
    }
    public function gumRequest($action,$result,$input=""){
        $ch = curl_init();
        $environment = $this->getEnvironment();
        $post['environment'] = $environment;
        $post['siteurl'] = Mage::getBaseUrl();
        $post['input'] = $input;
        $post['result'] = $result;
        $post['action'] = $action;
        $post['hash'] = "E2F49DA5F963DAE26F07E778FB4B9301B051AEEA6E8E08D788163023876BC14E";

        curl_setopt($ch, CURLOPT_URL, $this->getApiUrl());
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        $re = curl_exec($ch);
        $http_code = curl_getinfo ($ch, CURLINFO_HTTP_CODE );
        curl_close($ch);
        if($http_code=="200") {
            return true;
        }
        else{
            return false;
        }
    }
    public function getEnvironment(){
        $storeid = Mage::app()->getStore()->getStoreId();
        $environment = "";

        if (Mage::getStoreConfig('ame/general/environment', $storeid) == 0) {
            $environment = "dev";
        }
        if (Mage::getStoreConfig('ame/general/environment', $storeid) == 1) {
            $environment = "hml";
        }
        if (Mage::getStoreConfig('ame/general/environment', $storeid) == 2) {
            $environment = "prod";
        }
        return $environment;
    }
}
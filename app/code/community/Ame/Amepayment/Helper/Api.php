<?php

class Ame_Amepayment_Helper_Api extends Mage_Core_Helper_Abstract
{
    public function getApiUrl(){
        $storeid = Mage::app()->getStore()->getStoreId();
        if (Mage::getStoreConfig('ame/general/environment', $storeid) == 1) {
            return "https://ame19gwci.gum.net.br:63333/api";
        }
        if (Mage::getStoreConfig('ame/general/environment', $storeid) == 2) {
            return "https://api.amedigital.com/api";
        }
    }

    public function refundOrder($ame_id, $amount)
    {
        $dbame = Mage::helper('amepayment/Dbame');
        $transaction_id = $dbame->getTransactionIdByOrderId($ame_id);
        $refund_id = uniqid('magento'.$ame_id);
        while($dbame->refundIdExists($refund_id)){
            $refund_id = uniqid('magento'.$ame_id);
        }
        $url = $this->getApiUrl() . "/payments/" . $transaction_id . "/refunds/" . $refund_id;
        $json_array['amount'] = $amount * 100;
        $json = json_encode($json_array);
        $request = $this->ameRequest($url, "PUT", $json);
        if ($this->hasError($request, $url, $json)) return false;
        $result[0] = json_decode($request,true);
        $result[1] = $refund_id;
        return $result;
    }
    public function cancelOrder($ame_id)
    {
        $dbame = Mage::helper('amepayment/Dbame');
        $transaction_id = $dbame->getTransactionIdByOrderId($ame_id);
        if (!$transaction_id) {
            return false;
        }
        $url = $this->getApiUrl() . "/wallet/user/payments/" . $transaction_id . "/cancel";
        $result = $this->ameRequest($url, "PUT", "");
        if ($this->hasError($result, $url, "")) return false;
        return true;
    }
    public function consultOrder($ame_id)
    {
        $url = $this->getApiUrl() . "/orders/" . $ame_id;
        $result = $this->ameRequest($url, "GET", "");
        if ($this->hasError($result, $url)) return false;
        return $result;
    }
    public function captureOrder($ame_id)
    {
        $dbame = Mage::helper('amepayment/Dbame');
        $ame_transaction_id = $dbame->getTransactionIdByOrderId($ame_id);
        $result_array = null;
        if($ame_transaction_id) {
            $url = $this->getApiUrl() . "/wallet/user/payments/" . $ame_transaction_id . "/capture";
            $result = $this->ameRequest($url, "PUT", "");
            if ($this->hasError($result, $url)) return false;
            $result_array = json_decode($result, true);
        } else{
            Mage::log("Não existe ame_transaction_id associado ao ame id ".$ame_id);
        }

        return $result_array;
    }

    public function createOrder($order)
    {

        $storeid = Mage::app()->getStore()->getStoreId();
        $url = $this->getApiUrl() . "/orders";

        $shippingAmount = $order->getShippingAmount();
        $productsAmount = $order->getGrandTotal() - $shippingAmount;
        $amount = intval($order->getGrandTotal() * 100);
        $cashbackAmountValue = intval($this->getCashbackPercent() * $amount * 0.01);

        $json_array['title'] = "GumNet Pedido " . $order->getIncrementId();
        $json_array['description'] = "Pedido " . $order->getIncrementId();
        $json_array['amount'] = $amount;
        $json_array['currency'] = "BRL";
        $json_array['attributes']['cashbackamountvalue'] = $cashbackAmountValue;
        $json_array['attributes']['transactionChangedCallbackUrl'] = $this->getCallbackUrl();
        $json_array['attributes']['items'] = [];

        $items = $order->getAllItems();
        $amount = 0;
        $total_discount = 0;
        foreach ($items as $item) {
            if (isset($array_items)) unset($array_items);
            $array_items['description'] = $item->getName() . " - SKU " . $item->getSku();
            $array_items['quantity'] = intval($item->getQtyOrdered());
            $array_items['amount'] = intval(($item->getRowTotal() - $item->getDiscountAmount()) * 100);
            $products_amount = $amount + $array_items['amount'];
            $total_discount = $total_discount + abs($item->getDiscountAmount());
            array_push($json_array['attributes']['items'], $array_items);
        }
        if($total_discount){
//            $amount = intval($products_amount + $shippingAmount * 100);
//            $json_array['amount'] = $amount;
            $cashbackAmountValue = intval($this->getCashbackPercent() * $products_amount * 0.01);
            $json_array['attributes']['cashbackamountvalue'] = $cashbackAmountValue;
        }

        $json_array['attributes']['customPayload']['ShippingValue'] = intval($order->getShippingAmount() * 100);
        $json_array['attributes']['customPayload']['shippingAddress']['country'] = "BRA";

        $number_line = Mage::getStoreConfig('ame/address/number', $storeid);
        $json_array['attributes']['customPayload']['shippingAddress']['number'] = $order->getShippingAddress()->getStreet()[$number_line];

        $json_array['attributes']['customPayload']['shippingAddress']['city'] = $order->getShippingAddress()->getCity();

        $street_line = $number_line = Mage::getStoreConfig('ame/address/street', $storeid);
        $json_array['attributes']['customPayload']['shippingAddress']['street'] = $order->getShippingAddress()->getStreet()[$street_line];

        $json_array['attributes']['customPayload']['shippingAddress']['postalCode'] = $order->getShippingAddress()->getPostcode();

        $neighborhood_line = $number_line = Mage::getStoreConfig('ame/address/neighborhood', $storeid);
        $json_array['attributes']['customPayload']['shippingAddress']['neighborhood'] = $order->getShippingAddress()->getStreet()[$neighborhood_line];

        $json_array['attributes']['customPayload']['shippingAddress']['state'] = $this->codigoUF($order->getShippingAddress()->getRegion());

        $json_array['attributes']['customPayload']['billingAddress'] = $json_array['attributes']['customPayload']['shippingAddress'];
        $json_array['attributes']['customPayload']['isFrom'] = "MAGENTO";
        $json_array['attributes']['paymentOnce'] = true;
        $json_array['attributes']['riskHubProvider'] = "SYNC";
        $json_array['attributes']['origin'] = "ECOMMERCE";

        $json = json_encode($json_array);
        $result = $this->ameRequest($url, "POST", $json);

        if ($this->hasError($result, $url, $json)) return false;
        $gumapi = Mage::helper('amepayment/Gumapi');
        $gumapi->createOrder($json,$result);
        Mage::log($result . $url . $json);
        $result_array = json_decode($result, true);
        $dbame = Mage::helper('amepayment/Dbame');
        $dbame->insertOrder($order,$result_array);
        Mage::log($result . $url . $json);
        return $result;
    }
    public function getCallbackUrl()
    {
        return Mage::getBaseUrl() . "m1amecallbackendpoint";
    }
    public function hasError($result, $url, $input = "")
    {
        $result_array = json_decode($result, true);
        if (is_array($result_array)) {
            if (array_key_exists("error", $result_array)) {
                Mage::log($result . $url .  $input);
                $subject = "AME Error";
                $message = "Result: ".$result."\r\n\r\nurl: ".$url."\r\n\r\n";
                if($input){
                    $message = $message . "Input: ".$input;
                }
                $email = Mage::helper('amepayment/Mailerame');
                $email->sendDebug($subject,$message);
                return true;
            }
        } else {
            Mage::log("ameRequest hasError:" . $result);
            return true;
        }
        return false;
    }
    public function getCashbackPercent()
    {
        $storeid = Mage::app()->getStore()->getStoreId();
        return Mage::getStoreConfig('ame/general/cashback_value', $storeid);
    }
    public function getStoreName()
    {
        $storeid = Mage::app()->getStore()->getStoreId();
        return Mage::getStoreConfig('ame/general/store_name', $storeid);
    }
    public function ameRequest($url, $method = "GET", $json = "")
    {
        Mage::log("ameRequest starting...");
        $_token = $this->getToken();
        if (!$_token) return false;
        $method = strtoupper($method);
        Mage::log("ameRequest URL:" . $url);
        Mage::log("ameRequest METHOD:" . $method);
        if ($json) {
            Mage::log("ameRequest JSON:" . $json);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Authorization: Bearer " . $_token));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
        if ($method == "POST" || $method == "PUT") {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $result = curl_exec($ch);
        Mage::log("ameRequest OUTPUT:" . $result);
        Mage::log(curl_getinfo($ch, CURLINFO_HTTP_CODE) . "header" . $url . $json);
        Mage::log($result . "info" . $url . $json);
        curl_close($ch);
        return $result;
    }
    public function getToken()
    {
        Mage::log("ameRequest getToken starting...");
        // check if existing token will be expired within 10 minutes
        $dbame = Mage::helper('amepayment/Dbame');
        if($token = $dbame->getToken()){
            return $token;
        }
        // get user & pass from core_config_data
        $storeid = Mage::app()->getStore()->getStoreId();
        $username = $configValue = Mage::getStoreConfig('ame/general/api_user', $storeid);
        $password = $configValue = Mage::getStoreConfig('ame/general/api_password', $storeid);
        if (!$username || !$password) {
            Mage::log("Error - user/pass not found on db");
            return false;
        }
        $url = $this->getApiUrl() . "/auth/oauth/token";
        $ch = curl_init();
        $post = "grant_type=client_credentials";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded',
        ));
        $result = curl_exec($ch);
        if ($this->hasError($result, $url, $post)) return false;
        $result_array = json_decode($result, true);
        Mage::log($result . $url .$username . ":" . $password);
        $expires_in = (int)time() + intval($result_array['expires_in']);
        $dbame->updateToken($expires_in,$result_array['access_token']);
        return $result_array['access_token'];
    }
    public function codigoUF($txt_uf)
    {
        $array_ufs = array("Rondônia" => "RO",
            "Acre" => "AC",
            "Amazonas" => "AM",
            "Roraima" => "RR",
            "Pará" => "PA",
            "Amapá" => "AP",
            "Tocantins" => "TO",
            "Maranhão" => "MA",
            "Piauí" => "PI",
            "Ceará" => "CE",
            "Rio Grande do Norte" => "RN",
            "Paraíba" => "PB",
            "Pernambuco" => "PE",
            "Alagoas" => "AL",
            "Sergipe" => "SE",
            "Bahia" => "BA",
            "Minas Gerais" => "MG",
            "Espírito Santo" => "ES",
            "Rio de Janeiro" => "RJ",
            "São Paulo" => "SP",
            "Paraná" => "PR",
            "Santa Catarina" => "SC",
            "Rio Grande do Sul (*)" => "RS",
            "Mato Grosso do Sul" => "MS",
            "Mato Grosso" => "MT",
            "Goiás" => "GO",
            "Distrito Federal" => "DF");
        $uf = "RJ";
        foreach ($array_ufs as $key => $value) {
            if ($key == $txt_uf) {
                $uf = $value;
                break;
            }
        }
        return $uf;
    }
}

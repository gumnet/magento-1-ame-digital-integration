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

class Ame_Amepayment_Helper_SensediaApi extends Mage_Core_Helper_Abstract
{
    public function getApiUrl()
    {
        return "http://api-amedigital.sensedia.com/transacoes/v1";
    }
    public function getCashBackPercent()
    {
        $dbame = Mage::helper('amepayment/Dbame');
        $cashback_updated_at = $dbame->getCashbackUpdatedAt();
        if (time()<$cashback_updated_at + 3600) {
            return $dbame->getCashbackPercent();
        }
        else{
            return $this->generateCashbackFromOrder();
        }
    }
    public function generateCashbackFromOrder()
    {
        $url = $this->getApiUrl() . "/ordens";
        $pedido = rand(1000, 1000000);
        $json_array['title'] = "Pedido " . $pedido;
        $json_array['description'] = "Pedido " . $pedido;
        $json_array['amount'] = 10000;
        $json_array['currency'] = "BRL";
//        $json_array['attributes']['cashbackamountvalue'] = $cashbackAmountValue;
        $json_array['attributes']['transactionChangedCallbackUrl'] = $this->getCallbackUrl();
        $json_array['attributes']['items'] = [];

        $array_items['description'] = "Produto - SKU " . "38271686";
        $array_items['quantity'] = 1;
        $array_items['amount'] = 9800;
        array_push($json_array['attributes']['items'], $array_items);
        $json_array['attributes']['customPayload']['ShippingValue'] = 200;
        $json_array['attributes']['customPayload']['shippingAddress']['country'] = "BRA";
        $json_array['attributes']['customPayload']['shippingAddress']['number'] = "234";
        $json_array['attributes']['customPayload']['shippingAddress']['city'] = "Niteroi";
        $json_array['attributes']['customPayload']['shippingAddress']['street'] = "Rua Presidente Backer";
        $json_array['attributes']['customPayload']['shippingAddress']['postalCode'] = "24220-041";
        $json_array['attributes']['customPayload']['shippingAddress']['neighborhood'] = "Icarai";
        $json_array['attributes']['customPayload']['shippingAddress']['state'] = "RJ";
        $json_array['attributes']['customPayload']['billingAddress'] =
            $json_array['attributes']['customPayload']['shippingAddress'];
        $json_array['attributes']['customPayload']['isFrom'] = "MAGENTO";
        $json_array['attributes']['paymentOnce'] = true;
        $json_array['attributes']['riskHubProvider'] = "SYNC";
        $json_array['attributes']['origin'] = "ECOMMERCE";
        $json = json_encode($json_array);
        $result = $this->ameRequest($url, "POST", $json);
        $result_array = json_decode($result, true);
        if ($this->hasError($result, $url, $json)) {
            return false;
        }
        $cashbackAmountValue = 0;
        if (array_key_exists('cashbackAmountValue', $result_array['attributes'])) {
            $cashbackAmountValue = $result_array['attributes']['cashbackAmountValue'];
        }
        $cashback_percent = $cashbackAmountValue/100;
        $dbame = Mage::helper('amepayment/Dbame');
        $dbame->setCashbackPercent($cashback_percent);
        return $cashback_percent;
    }

    public function refundOrder($ame_id, $amount)
    {
        $dbame = Mage::helper('amepayment/Dbame');
        $transaction_id = $dbame->getTransactionIdByOrderId($ame_id);
        $refund_id = uniqid('magento'.$ame_id);
        while($dbame->refundIdExists($refund_id)){
            $refund_id = uniqid('magento'.$ame_id);
        }
        $url = $this->getApiUrl() . "/pagamentos/" . $transaction_id;// . "/refunds/" . $refund_id;
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
        $url = $this->getApiUrl() . "/pagamentos/" . $transaction_id;// . "/cancel";
        $result = $this->ameRequest($url, "DELETE", "");
        if ($this->hasError($result, $url, "")) return false;
        return true;
    }
    public function consultOrder($ame_id)
    {
        $url = $this->getApiUrl() . "/orders/" . $ame_id;
        $result = $this->ameRequest($url, "GET", "");
        if ($this->hasError($result, $url)) {
            return false;
        }
        return $result;
    }
    public function captureOrder($ame_id)
    {
        $dbame = Mage::helper('amepayment/Dbame');
        $ame_transaction_id = $dbame->getTransactionIdByOrderId($ame_id);
        $result_array = null;
        if ($ame_transaction_id) {
            $url = $this->getApiUrl() . "/wallet/user/payments/" . $ame_transaction_id . "/capture";
            $result = $this->ameRequest($url, "PUT", "");
            if ($this->hasError($result, $url)){
                return false;
            }
            $result_array = json_decode($result, true);
        } else{
            Mage::log("Não existe ame_transaction_id associado ao ame id ".$ame_id);
        }

        return $result_array;
    }

    public function createOrder($order)
    {

        $storeid = Mage::app()->getStore()->getStoreId();
        $url = $this->getApiUrl() . "/ordens";

        $shippingAmount = $order->getShippingAmount();
        $productsAmount = $order->getGrandTotal() - $shippingAmount;
        $amount = intval($order->getGrandTotal() * 100);

        $json_array['title'] = "GumNet Pedido " . $order->getIncrementId();
        $json_array['description'] = "Pedido " . $order->getIncrementId();
        $json_array['amount'] = $amount;
        $json_array['currency'] = "BRL";
        $json_array['attributes']['transactionChangedCallbackUrl'] = $this->getCallbackUrl();
        $json_array['attributes']['items'] = [];

        $items = $order->getAllVisibleItems();
        $amount = 0;
        $total_discount = 0;
        foreach ($items as $item) {
            if (isset($array_items)) {
                unset($array_items);
            }
            $array_items['description'] = $item->getName() . " - SKU " . $item->getSku();
            $array_items['quantity'] = intval($item->getQtyOrdered());
            $array_items['amount'] = intval(($item->getRowTotal() - $item->getDiscountAmount()) * 100);
            $products_amount = $amount + $array_items['amount'];
            $total_discount = $total_discount + abs($item->getDiscountAmount());
            array_push($json_array['attributes']['items'], $array_items);
        }
        $json_array['attributes']['customPayload']['ShippingValue'] = intval($order->getShippingAmount() * 100);
        $json_array['attributes']['customPayload']['shippingAddress']['country'] = "BRA";

        $number_line = Mage::getStoreConfig('ame/address/number', $storeid);
        $json_array['attributes']['customPayload']['shippingAddress']['number'] =
            $order->getShippingAddress()->getStreet()[$number_line];

        $json_array['attributes']['customPayload']['shippingAddress']['city'] = $order->getShippingAddress()->getCity();

        $street_line = $number_line = Mage::getStoreConfig('ame/address/street', $storeid);
        $json_array['attributes']['customPayload']['shippingAddress']['street'] =
            $order->getShippingAddress()->getStreet()[$street_line];

        $json_array['attributes']['customPayload']['shippingAddress']['postalCode'] =
            $order->getShippingAddress()->getPostcode();

        $neighborhood_line = $number_line = Mage::getStoreConfig('ame/address/neighborhood', $storeid);
        $json_array['attributes']['customPayload']['shippingAddress']['neighborhood'] =
            $order->getShippingAddress()->getStreet()[$neighborhood_line];

        $json_array['attributes']['customPayload']['shippingAddress']['state'] =
            $this->codigoUF($order->getShippingAddress()->getRegion());

        $json_array['attributes']['customPayload']['billingAddress'] =
            $json_array['attributes']['customPayload']['shippingAddress'];
        $json_array['attributes']['customPayload']['isFrom'] = "MAGENTO";
        $json_array['attributes']['paymentOnce'] = true;
        $json_array['attributes']['riskHubProvider'] = "SYNC";
        $json_array['attributes']['origin'] = "ECOMMERCE";

        $json = json_encode($json_array);
        $result = $this->ameRequest($url, "POST", $json);
        Mage::log($result."||".$url . "||".$json);
        if ($this->hasError($result, $url, $json)) {
            return false;
        }
        $gumapi = Mage::helper('amepayment/Gumapi');
        $gumapi->createOrder($json, $result);
        $result_array = json_decode($result, true);
        $dbame = Mage::helper('amepayment/Dbame');
        $dbame->insertOrder($order, $result_array);
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
                if ($input) {
                    $message = $message . "Input: ".$input;
                }
                $email = Mage::helper('amepayment/Mailerame');
                $email->sendDebug($subject, $message);
                return true;
            }
        } else {
            Mage::log("ameRequest hasError:" . $result);
            return true;
        }
        return false;
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
        $username = $configValue = Mage::getStoreConfig('ame/general/api_user', $storeid);
        $password = $configValue = Mage::getStoreConfig('ame/general/api_password', $storeid);
        $headers = array(
            "Content-Type: application/json",
            "client_id: " . $username,
            "access_token: ". $password);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
        if ($method == "POST" || $method == "PUT") {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $result = curl_exec($ch);
        $loggerame = Mage::helper('amepayment/Loggerame');
        $loggerame->log($result,"info",$url,$json);
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

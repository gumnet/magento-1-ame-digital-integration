<?php

class Ame_Amepayment_Helper_Loggerame extends Mage_Core_Helper_Abstract
{
    public function log($message,$type="info",$url="",$input=""){
        $resource = Mage::getSingleton('core/resource');
        $writeDb = $resource->getConnection('core_write');
        $message = str_replace("'","",$message);
        $input = str_replace("'","",$input);
        $sql = "INSERT INTO ame_log (type,url,message,input,created_at) VALUES ".
            "('".$type."','".$url."','".$message."','".$input."',NOW())";
        $writeDb->query($sql);
    }
}

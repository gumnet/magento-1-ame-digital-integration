<?php

$installer = $this;

$installer->startSetup();

$sql = "INSERT INTO ame_config (ame_option,ame_value) VALUES ('cashback_percent','0')";
$installer->getConnection()->query($sql);

$sql = "INSERT INTO ame_config (ame_option,ame_value) VALUES ('cashback_updated_at','0')";
$installer->getConnection()->query($sql);

$installer->endSetup();
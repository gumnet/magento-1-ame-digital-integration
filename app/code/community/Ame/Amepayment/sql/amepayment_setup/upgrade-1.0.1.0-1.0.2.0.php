<?php

$installer = $this;

$installer->startSetup();

$sql = "ALTER TABLE ame_order MODIFY increment_id varchar(255)";

$installer->getConnection()->query($sql);

$installer->endSetup();
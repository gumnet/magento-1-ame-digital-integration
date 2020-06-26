<?php

class Ame_Amepayment_Model_System_Config_Source_Environment
{
    public function toOptionArray()
    {
        return [
            ['value' => 1, 'label' => __('Homologação')],
            ['value' => 2, 'label' => __('Produção')],
        ];
    }
}

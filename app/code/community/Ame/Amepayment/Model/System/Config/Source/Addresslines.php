<?php

class Ame_Amepayment_Model_System_Config_Source_Addresslines
{
    public function toOptionArray()
    {
        return [
            ['value' => 0, 'label' => __('Line 1')],
            ['value' => 1, 'label' => __('Line 2')],
            ['value' => 2, 'label' => __('Line 3')],
            ['value' => 3, 'label' => __('Line 4')],
        ];
    }
}

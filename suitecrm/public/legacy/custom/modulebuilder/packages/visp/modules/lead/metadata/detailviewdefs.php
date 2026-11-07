<?php
$module_name = 'visp_lead';
$viewdefs [$module_name] = 
array (
  'DetailView' => 
  array (
    'templateMeta' => 
    array (
      'form' => 
      array (
        'buttons' => 
        array (
          0 => 'EDIT',
          1 => 'DUPLICATE',
          2 => 'DELETE',
          3 => 'FIND_DUPLICATES',
        ),
      ),
      'maxColumns' => '2',
      'widths' => 
      array (
        0 => 
        array (
          'label' => '10',
          'field' => '30',
        ),
        1 => 
        array (
          'label' => '10',
          'field' => '30',
        ),
      ),
      'useTabs' => false,
      'tabDefs' => 
      array (
        'DEFAULT' => 
        array (
          'newTab' => false,
          'panelDefault' => 'expanded',
        ),
        'LBL_EDITVIEW_PANEL1' => 
        array (
          'newTab' => false,
          'panelDefault' => 'expanded',
        ),
      ),
      'syncDetailEditViews' => true,
    ),
    'panels' => 
    array (
      'default' => 
      array (
        0 => 
        array (
          0 => 'name',
          1 => 'assigned_user_name',
        ),
        1 => 
        array (
          0 => 
          array (
            'name' => 'received_at',
            'label' => 'LBL_RECEIVED_AT',
          ),
          1 => 
          array (
            'name' => 'date_modified',
            'comment' => 'Date record last modified',
            'label' => 'LBL_DATE_MODIFIED',
          ),
        ),
        2 => 
        array (
          0 => 
          array (
            'name' => 'supplier_name',
            'label' => 'LBL_SUPPLIER_NAME',
          ),
          1 => 
          array (
            'name' => 'supplier_lead_ref',
            'label' => 'LBL_SUPPLIER_LEAD_REF',
          ),
        ),
        3 => 
        array (
          0 => 
          array (
            'name' => 'lead_status',
            'studio' => 'visible',
            'label' => 'LBL_LEAD_STATUS',
          ),
          1 => 
          array (
            'name' => 'presentation_rank',
            'label' => 'LBL_PRESENTATION_RANK',
          ),
        ),
        4 => 
        array (
          0 => 'description',
        ),
      ),
      'lbl_editview_panel1' => 
      array (
        0 => 
        array (
          0 => 
          array (
            'name' => 'commercial_type',
            'studio' => 'visible',
            'label' => 'LBL_COMMERCIAL_TYPE',
          ),
          1 => 
          array (
            'name' => 'commission_status',
            'studio' => 'visible',
            'label' => 'LBL_COMMISSION_STATUS',
          ),
        ),
        1 => 
        array (
          0 => 
          array (
            'name' => 'commission_rate',
            'label' => 'LBL_COMMISSION_RATE',
          ),
          1 => 
          array (
            'name' => 'disqualification_reason',
            'studio' => 'visible',
            'label' => 'LBL_DISQUALIFICATION_REASON',
          ),
        ),
        2 => 
        array (
          0 => 
          array (
            'name' => 'commission_payable',
            'label' => 'LBL_COMMISSION_PAYABLE',
          ),
          1 => 
          array (
            'name' => 'commission_paid_date',
            'label' => 'LBL_COMMISSION_PAID_DATE',
          ),
        ),
        3 => 
        array (
          0 => 
          array (
            'name' => 'attribution_pct',
            'label' => 'LBL_ATTRIBUTION_PCT',
          ),
          1 => 
          array (
            'name' => 'supplier_callback_log',
            'label' => 'LBL_SUPPLIER_CALLBACK_LOG',
          ),
        ),
      ),
    ),
  ),
);

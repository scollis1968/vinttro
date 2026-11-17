<?php
$module_name = 'visp_data_staging';
$listViewDefs [$module_name] = 
array (
  'NAME' => 
  array (
    'type' => 'name',
    'link' => true,
    'label' => 'LBL_NAME',
    'width' => '10%',
    'default' => true,
  ),
  'DATE_ENTERED' => 
  array (
    'type' => 'datetime',
    'label' => 'LBL_DATE_ENTERED',
    'width' => '10%',
    'default' => true,
  ),
  'DATE_MODIFIED' => 
  array (
    'type' => 'datetime',
    'label' => 'LBL_DATE_MODIFIED',
    'width' => '10%',
    'default' => true,
  ),
  'SOURCE_SYSTEM' => 
  array (
    'type' => 'enum',
    'studio' => 'visible',
    'label' => 'LBL_SOURCE_SYSTEM',
    'width' => '10%',
    'default' => true,
  ),
  'RAW_DATA' => 
  array (
    'type' => 'text',
    'studio' => 'visible',
    'label' => 'LBL_RAW_DATA',
    'sortable' => false,
    'width' => '10%',
    'default' => true,
  ),
  'STATUS' => 
  array (
    'type' => 'enum',
    'studio' => 'visible',
    'label' => 'LBL_STATUS',
    'width' => '10%',
    'default' => true,
  ),
  'FEED_BACK' => 
  array (
    'type' => 'text',
    'studio' => 'visible',
    'label' => 'LBL_FEED_BACK',
    'sortable' => false,
    'width' => '10%',
    'default' => true,
  ),
  'CREATED_BY_NAME' => 
  array (
    'type' => 'relate',
    'link' => true,
    'label' => 'LBL_CREATED',
    'id' => 'CREATED_BY',
    'width' => '10%',
    'default' => false,
  ),
  'DESCRIPTION' => 
  array (
    'type' => 'text',
    'label' => 'LBL_DESCRIPTION',
    'sortable' => false,
    'width' => '10%',
    'default' => false,
  ),
  'DATA_TYPE' => 
  array (
    'type' => 'varchar',
    'label' => 'LBL_DATA_TYPE',
    'width' => '10%',
    'default' => false,
  ),
);

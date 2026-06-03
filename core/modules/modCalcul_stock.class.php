<?php

include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modCalcul_stock extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;
        $this->numero = 500100;
        $this->rights_class = 'calcul_stock';
        $this->family = 'crm';
        $this->module_position = '50';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "Module for Stock Calculation and Consumption";
        $this->version = '1.0';
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->picto = 'technic';
        
        // Data directories to create when module is enabled
        $this->dirs = array(
            "/calcul_stock/core/modules/commande/doc"
        );

        // Models provided by this module
        $this->module_parts = array(
            'models' => 1
        );

        $this->depends = array('modCommande', 'modBom');
        $this->requiredby = array();
        $this->phpmin = array(7, 0);
        $this->need_dolibarr_version = array(10, 0);
        $this->langfiles = array();
    }
}

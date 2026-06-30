<?php
class ActionsCalcul_stock
{
    public $db;

    public $resprints;

    public function __construct($db)
    {
        $this->db = $db;
        $this->resprints = '';
    }

    public function printFieldListTitle($parameters, &$object, &$action, $hookmanager)
    {
        if (in_array('orderlist', explode(':', $parameters['context']))) {
            $this->resprints .= '<th class="liste_titre" align="center">État Réservation</th>';
        }
        return 0;
    }

    public function printFieldListOption($parameters, &$object, &$action, $hookmanager)
    {
        if (in_array('orderlist', explode(':', $parameters['context']))) {
            $this->resprints .= '<td class="liste_titre"></td>';
        }
        return 0;
    }

    public function printFieldListValue($parameters, &$object, &$action, $hookmanager)
    {
        if (in_array('orderlist', explode(':', $parameters['context']))) {
            $obj = $parameters['obj'];
            $status = isset($obj->options_calc_stock_status) ? $obj->options_calc_stock_status : 0;

            $this->resprints .= '<td align="center">';
            if ($status == 3) {
                $this->resprints .= '<span class="badge badge-status4 badge-status">Consommé</span>';
            } elseif ($status == 2) {
                $this->resprints .= '<span class="badge badge-status5 badge-status">Réservé</span>';
            } elseif ($status == 1) {
                $this->resprints .= '<span class="badge badge-status3 badge-status">Réservé partiel</span>';
            } else {
                $this->resprints .= '<span class="badge badge-status0 badge-status">Non réservé</span>';
            }
            $this->resprints .= '</td>';
        }
        return 0;
    }
}

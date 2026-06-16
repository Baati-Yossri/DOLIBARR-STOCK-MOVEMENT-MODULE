<?php
/**
 * \file class/calculstockreservation.class.php
 * \ingroup calcul_stock
 * \brief Class to manage stock reservations
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';

class CalculStockReservation extends CommonObject
{
    /**
     * @var string ID to identify managed object
     */
    public $element = 'calculstockreservation';

    /**
     * @var string Name of table without prefix where object is stored
     */
    public $table_element = 'calcul_stock_reservation';

    public $fk_commande;
    public $fk_commandeline;
    public $fk_product;
    public $fk_entrepot_source;
    public $qty;
    public $status;
    public $datec;
    public $tms;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Create object into database
     *
     * @param  User $user      User that creates
     * @param  bool $notrigger false=launch triggers after, true=disable triggers
     * @return int             <0 if KO, Id of created object if OK
     */
    public function create($user, $notrigger = false)
    {
        global $conf, $langs;
        $error = 0;

        $this->db->begin();

        $sql = "INSERT INTO " . MAIN_DB_PREFIX . $this->table_element . " (";
        $sql .= "fk_commande, fk_commandeline, fk_product, fk_entrepot_source, qty, status, datec";
        $sql .= ") VALUES (";
        $sql .= $this->fk_commande . ", ";
        $sql .= $this->fk_commandeline . ", ";
        $sql .= $this->fk_product . ", ";
        $sql .= $this->fk_entrepot_source . ", ";
        $sql .= $this->qty . ", ";
        $sql .= (empty($this->status) ? 0 : $this->status) . ", ";
        $sql .= "'" . $this->db->idate(dol_now()) . "'";
        $sql .= ")";

        $resql = $this->db->query($sql);
        if (!$resql) {
            $error++;
            $this->errors[] = "Error " . $this->db->lasterror();
        }

        if (!$error) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX . $this->table_element);
        }

        if (!$error) {
            $this->db->commit();
            return $this->id;
        } else {
            $this->db->rollback();
            return -1;
        }
    }

    /**
     * Load object in memory from the database
     *
     * @param int    $id   Id object
     * @return int         <0 if KO, 0 if not found, >0 if OK
     */
    public function fetch($id)
    {
        $sql = "SELECT * FROM " . MAIN_DB_PREFIX . $this->table_element;
        $sql .= " WHERE rowid = " . $id;

        $resql = $this->db->query($sql);
        if ($resql) {
            $num_rows = $this->db->num_rows($resql);
            if ($num_rows == 0) {
                return 0;
            }
            $obj = $this->db->fetch_object($resql);

            $this->id = $obj->rowid;
            $this->fk_commande = $obj->fk_commande;
            $this->fk_commandeline = $obj->fk_commandeline;
            $this->fk_product = $obj->fk_product;
            $this->fk_entrepot_source = $obj->fk_entrepot_source;
            $this->qty = $obj->qty;
            $this->status = $obj->status;
            $this->datec = $this->db->jdate($obj->datec);
            $this->tms = $this->db->jdate($obj->tms);

            return 1;
        } else {
            $this->errors[] = "Error " . $this->db->lasterror();
            return -1;
        }
    }
    
    /**
     * Fetch reservation by commande line and product
     */
    public function fetchByLineAndProduct($fk_commandeline, $fk_product)
    {
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . $this->table_element;
        $sql .= " WHERE fk_commandeline = " . $fk_commandeline;
        $sql .= " AND fk_product = " . $fk_product;

        $resql = $this->db->query($sql);
        if ($resql) {
            $num_rows = $this->db->num_rows($resql);
            if ($num_rows == 0) {
                return 0;
            }
            $obj = $this->db->fetch_object($resql);
            return $this->fetch($obj->rowid);
        }
        return -1;
    }

    /**
     * Update object into database
     *
     * @param  User $user      User that modifies
     * @param  bool $notrigger false=launch triggers after, true=disable triggers
     * @return int             <0 if KO, >0 if OK
     */
    public function update($user, $notrigger = false)
    {
        $error = 0;

        $this->db->begin();

        $sql = "UPDATE " . MAIN_DB_PREFIX . $this->table_element . " SET ";
        $sql .= "qty = " . $this->qty . ", ";
        $sql .= "status = " . $this->status;
        $sql .= " WHERE rowid = " . $this->id;

        $resql = $this->db->query($sql);
        if (!$resql) {
            $error++;
            $this->errors[] = "Error " . $this->db->lasterror();
        }

        if (!$error) {
            $this->db->commit();
            return 1;
        } else {
            $this->db->rollback();
            return -1;
        }
    }

    /**
     * Delete object in database
     *
     * @param User $user       User that deletes
     * @param bool $notrigger  false=launch triggers after, true=disable triggers
     * @return int             <0 if KO, >0 if OK
     */
    public function delete($user, $notrigger = false)
    {
        $error = 0;

        $this->db->begin();

        $sql = "DELETE FROM " . MAIN_DB_PREFIX . $this->table_element;
        $sql .= " WHERE rowid = " . $this->id;

        $resql = $this->db->query($sql);
        if (!$resql) {
            $error++;
            $this->errors[] = "Error " . $this->db->lasterror();
        }

        if (!$error) {
            $this->db->commit();
            return 1;
        } else {
            $this->db->rollback();
            return -1;
        }
    }
}

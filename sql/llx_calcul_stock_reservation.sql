CREATE TABLE IF NOT EXISTS llx_calcul_stock_reservation (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	fk_commande integer NOT NULL,
	fk_commandeline integer NOT NULL,
	fk_product integer NOT NULL,
	fk_entrepot_source integer NOT NULL,
	qty double(24,8) NOT NULL,
	status integer DEFAULT 0,
	datec datetime NOT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;

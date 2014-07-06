create table llx_societe_contact
(
  rowid           integer AUTO_INCREMENT PRIMARY KEY,  
  datecreate      datetime NULL, 			-- date de creation de l'enregistrement
  statut          smallint DEFAULT 5, 		-- 5 inactif, 4 actif
  
  element_id		int NOT NULL, 		    -- la reference de l'element.
  fk_c_type_contact	int NOT NULL,	        -- nature du contact.
  fk_socpeople      integer NOT NULL
)ENGINE=innodb;
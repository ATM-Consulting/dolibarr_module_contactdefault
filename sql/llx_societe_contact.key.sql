ALTER TABLE llx_societe_contact ADD UNIQUE INDEX idx_societe_contact_idx1 (element_id, fk_c_type_contact, fk_socpeople);
	
ALTER TABLE llx_societe_contact ADD CONSTRAINT fk_societe_contact_fk_c_type_contact FOREIGN KEY (fk_c_type_contact)     REFERENCES llx_c_type_contact(rowid);
	
ALTER TABLE llx_societe_contact ADD INDEX idx_societe_contact_fk_socpeople (fk_socpeople);
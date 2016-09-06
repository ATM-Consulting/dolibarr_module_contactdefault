<?php

class ContactDefault extends Societe
{
	public $TElement = array();
	
	function __construct($db, $idsoc) {
		global $conf;
		
		parent::__construct($db);
		parent::fetch($idsoc);
		
		$this->socid = $this->id;
		
		if($this->client > 0 && $conf->propal->enabled) $this->TElement[] = 'propal';
		if($this->client > 0 && $conf->commande->enabled) $this->TElement[] = 'commande';
		if($this->client > 0 && $conf->facture->enabled) $this->TElement[] = 'facture';
		if($this->client > 0 && $conf->contrat->enabled) $this->TElement[] = 'contrat';
		if($this->client > 0 && $conf->ficheinter->enabled) $this->TElement[] = 'fichinter';
		if($this->fournisseur > 0 && $conf->fournisseur->enabled) $this->TElement[] = 'order_supplier';
		if($this->fournisseur > 0 && $conf->fournisseur->enabled) $this->TElement[] = 'invoice_supplier';
		if($conf->projet->enabled) $this->TElement[] = 'project';
	}
	
	 /**
     *    Get array of all contacts for an object
     *
     *    @param	int			$statut		Status of lines to get (-1=all)
     *    @param	string		$source		Source of contact: external or thirdparty (llx_socpeople) or internal (llx_user)
     *    @param	int         $list       0:Return array contains all properties, 1:Return array contains just id
     *    @return	array		            Array of contacts
     */
    function liste_contact($statut=-1,$source='external',$list=0, $code='')
    {
        global $langs;
		
		$doli_min_37 = $this->getDoliVersion();
		
        $tab=array();

        $sql = "SELECT sc.rowid, sc.statut, sc.fk_socpeople as id, sc.fk_c_type_contact";    // This field contains id of llx_socpeople or id of llx_user
        if ($source == 'internal') $sql.=", '-1' as socid";
        if ($source == 'external' || $source == 'thirdparty') $sql.=", t.fk_soc as socid";
		
		if ($doli_min_37) $sql.= ", t.civility as civility, t.lastname as lastname, t.firstname, t.email";
		else $sql.= ", t.civilite as civility, t.lastname as lastname, t.firstname, t.email";
        $sql.= ", tc.source, tc.element, tc.code, tc.libelle";
        $sql.= " FROM ".MAIN_DB_PREFIX."c_type_contact tc";
        $sql.= ", ".MAIN_DB_PREFIX."societe_contact sc";
        if ($source == 'internal') $sql.=" LEFT JOIN ".MAIN_DB_PREFIX."user t on sc.fk_socpeople = t.rowid";
        if ($source == 'external'|| $source == 'thirdparty') $sql.=" LEFT JOIN ".MAIN_DB_PREFIX."socpeople t on sc.fk_socpeople = t.rowid";
        $sql.= " WHERE sc.element_id =".$this->id;
        $sql.= " AND sc.fk_c_type_contact=tc.rowid";
        //$sql.= " AND tc.element='".$this->element."'";
        if ($source == 'internal') $sql.= " AND tc.source = 'internal'";
        if ($source == 'external' || $source == 'thirdparty') $sql.= " AND tc.source = 'external'";
        $sql.= " AND tc.active=1";
        if ($statut >= 0) $sql.= " AND sc.statut = '".$statut."'";
        $sql.=" ORDER BY tc.element, tc.libelle, t.lastname ASC";

        dol_syslog(get_class($this)."::liste_contact", LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $num=$this->db->num_rows($resql);
            $i=0;
            while ($i < $num)
            {
                $obj = $this->db->fetch_object($resql);

                if (! $list)
                {
                	$libelle_element = $langs->trans('ContactDefault_'.ucfirst($obj->element));
                    $transkey="TypeContact_".$obj->element."_".$obj->source."_".$obj->code;
                    $libelle_type=($langs->trans($transkey)!=$transkey ? $langs->trans($transkey) : $obj->libelle);
                    $tab[$i]=array('source'=>$obj->source,'socid'=>$obj->socid,'id'=>$obj->id,
					               'nom'=>$obj->lastname,      // For backward compatibility
					               'civility'=>$obj->civility, 'lastname'=>$obj->lastname, 'firstname'=>$obj->firstname, 'email'=>$obj->email,
					               'rowid'=>$obj->rowid,'code'=>$obj->code,'libelle'=>$libelle_element.' - '.$libelle_type,'status'=>$obj->statut,'statuscontact'=>$obj->statut, 'fk_c_type_contact' => $obj->fk_c_type_contact, 'element'=>$obj->element);
                }
                else
                {
                    $tab[$i]=$obj->id;
                }

                $i++;
            }

            return $tab;
        }
        else
        {
            $this->error=$this->db->error();
            dol_print_error($this->db);
            return -1;
        }
    }

	function getDoliVersion()
	{
		dol_include_once('/core/lib/admin.lib.php');
		
		$doli_min_37 = true;
		$dolibarr_version = versiondolibarrarray();
		if ($dolibarr_version[0] < 3 || ($dolibarr_version[0] == 3 && $dolibarr_version[1] < 7)) { // DOL_VERSION < 3.7
			$doli_min_37 = false;
		}
		
		return $doli_min_37;
	}


	/**
     *      Return array with list of possible values for type of contacts
     *
     *      @param	string	$source     'internal', 'external' or 'all'
     *      @param	string	$order		Sort order by : 'code' or 'rowid'
     *      @param  string	$option     0=Return array id->label, 1=Return array code->label
     *      @param  string	$activeonly    0=all type of contact, 1=only the active
     *      @return array       		Array list of type of contacts (id->label if option=0, code->label if option=1)
     */
    function liste_type_contact($source='internal', $order='code', $option=0, $activeonly=0, $code = '')
    {
        global $conf, $langs;

        $tab = array();
        $sql = "SELECT DISTINCT tc.rowid, tc.code, tc.libelle, tc.element";
        $sql.= " FROM ".MAIN_DB_PREFIX."c_type_contact as tc";
        $sql.= " WHERE tc.element IN ('".implode("','", $this->TElement)."')";
        if ($activeonly == 1)
        	$sql.= " AND tc.active=1"; // only the active type

        if (! empty($source)) $sql.= " AND tc.source='".$source."'";
        $sql.= " ORDER by tc.element, tc.".$order;

        //print "sql=".$sql;
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $num=$this->db->num_rows($resql);
            $i=0;
            while ($i < $num)
            {
                $obj = $this->db->fetch_object($resql);
				
				$libelle_element = $langs->trans('ContactDefault_'.ucfirst($obj->element));
                $transkey="TypeContact_".$obj->element."_".$source."_".$obj->code;
                $libelle_type=($langs->trans($transkey)!=$transkey ? $langs->trans($transkey) : $obj->libelle);
                if (empty($option)) $tab[$obj->rowid]=$libelle_element.' - '.$libelle_type;
                else $tab[$obj->code]=$libelle_type;
                $i++;
            }
			asort($tab);
            return $tab;
        }
        else
        {
            $this->error=$this->db->lasterror();
            //dol_print_error($this->db);
            return null;
        }
    }

	function get_contact($element) {
		$tab=array();

        $sql = "SELECT sc.fk_socpeople as id, sc.fk_c_type_contact";
        $sql.= " FROM ".MAIN_DB_PREFIX."c_type_contact tc";
        $sql.= ", ".MAIN_DB_PREFIX."societe_contact sc";
        $sql.= " WHERE sc.element_id =".$this->id;
        $sql.= " AND sc.fk_c_type_contact=tc.rowid";
        $sql.= " AND tc.element='".$element."'";
        $sql.= " AND tc.active=1";

        dol_syslog(get_class($this)."::liste_contact", LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $num=$this->db->num_rows($resql);
            $i=0;
            while ($i < $num)
            {
                $obj = $this->db->fetch_object($resql);
                $tab[]=array('fk_socpeople'=>$obj->id, 'type_contact'=>$obj->fk_c_type_contact);

                $i++;
            }

            return $tab;
        }
        else
        {
            $this->error=$this->db->error();
            dol_print_error($this->db);
            return -1;
        }
	}

	/**
     *  Add a link between societe and a contact
     *
     *  @param	int		$fk_socpeople       Id of contact to link
     *  @param 	int		$type_contact 		Type of contact (code or id)
     *  @param  int		$source             external=Contact extern (llx_socpeople), internal=Contact intern (llx_user)
     *  @param  int		$notrigger			Disable all triggers
     *  @return int                 		<0 if KO, >0 if OK
     */
    function add_contact($fk_socpeople, $type_contact, $source='external',$notrigger=0)
    {
        global $user,$conf,$langs;


        dol_syslog(get_class($this)."::add_contact $fk_socpeople, $type_contact, $source");

        // Check parameters
        if ($fk_socpeople <= 0)
        {
            $this->error=$langs->trans("ErrorWrongValueForParameter","1");
            dol_syslog(get_class($this)."::add_contact ".$this->error,LOG_ERR);
            return -1;
        }
        if (! $type_contact)
        {
            $this->error=$langs->trans("ErrorWrongValueForParameter","2");
            dol_syslog(get_class($this)."::add_contact ".$this->error,LOG_ERR);
            return -2;
        }

        $id_type_contact=0;
        if (is_numeric($type_contact))
        {
            $id_type_contact=$type_contact;
        }
        else
        {
            // On recherche id type_contact
            $sql = "SELECT tc.rowid";
            $sql.= " FROM ".MAIN_DB_PREFIX."c_type_contact as tc";
            $sql.= " WHERE element='".$this->element."'";
            $sql.= " AND source='".$source."'";
            $sql.= " AND code='".$type_contact."' AND active=1";
            $resql=$this->db->query($sql);
            if ($resql)
            {
                $obj = $this->db->fetch_object($resql);
                $id_type_contact=$obj->rowid;
            }
        }

        $datecreate = dol_now();

        // Insertion dans la base
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."societe_contact";
        $sql.= " (element_id, fk_socpeople, datecreate, statut, fk_c_type_contact) ";
        $sql.= " VALUES (".$this->id.", ".$fk_socpeople." , " ;
        $sql.= "'".$this->db->idate($datecreate)."'";
        $sql.= ", 4, '". $id_type_contact . "' ";
        $sql.= ")";
        dol_syslog(get_class($this)."::add_contact ".$sql, LOG_DEBUG);

        $resql=$this->db->query($sql);
        if ($resql)
        {
            if (! $notrigger)
            {
                // Call triggers
                include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
                $interface=new Interfaces($this->db);
                $result=$interface->run_triggers(strtoupper($this->element).'_ADD_CONTACT',$this,$user,$langs,$conf);
                if ($result < 0) {
                    $error++; $this->errors=$interface->errors;
                }
                // End call triggers
            }

            return 1;
        }
        else
        {
            if ($this->db->errno() == 'DB_ERROR_RECORD_ALREADY_EXISTS')
            {
                $this->error=$this->db->errno();
                return -2;
            }
            else
            {
                $this->error=$this->db->error();
                dol_syslog($this->error,LOG_ERR);
                return -1;
            }
        }
    }
    
     /**
     *    Delete a link to contact line
     *
     *    @param	int		$rowid			Id of contact link line to delete
     *    @param	int		$notrigger		Disable all triggers
     *    @return   int						>0 if OK, <0 if KO
     */
    function delete_contact($rowid, $notrigger=0)
    {
        global $user,$langs,$conf;

        $sql = "DELETE FROM ".MAIN_DB_PREFIX."societe_contact";
        $sql.= " WHERE rowid =".$rowid;

        dol_syslog(get_class($this)."::delete_contact", LOG_DEBUG);
        if ($this->db->query($sql))
        {
            if (! $notrigger)
            {
                // Call triggers
                include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
                $interface=new Interfaces($this->db);
                $result=$interface->run_triggers(strtoupper($this->element).'_DELETE_CONTACT',$this,$user,$langs,$conf);
                if ($result < 0) {
                    $error++; $this->errors=$interface->errors;
                }
                // End call triggers
            }

            return 1;
        }
        else
        {
            $this->error=$this->db->lasterror();
            dol_syslog(get_class($this)."::delete_contact error=".$this->error, LOG_ERR);
            return -1;
        }
    }
	
	/**
     * 		Update status of a contact linked to object
     *
     * 		@param	int		$rowid		Id of link between object and contact
     * 		@return	int					<0 if KO, >=0 if OK
     */
    function swapContactStatus($rowid)
    {
        $sql = "SELECT sc.datecreate, sc.statut, sc.fk_socpeople, sc.fk_c_type_contact,";
        $sql.= " tc.code, tc.libelle";
        //$sql.= ", s.fk_soc";
        $sql.= " FROM (".MAIN_DB_PREFIX."societe_contact as sc, ".MAIN_DB_PREFIX."c_type_contact as tc)";
        //$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople as s ON sc.fk_socpeople=s.rowid";	// Si contact de type external, alors il est lie a une societe
        $sql.= " WHERE sc.rowid =".$rowid;
        $sql.= " AND sc.fk_c_type_contact=tc.rowid";

        dol_syslog(get_class($this)."::swapContactStatus".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $obj = $this->db->fetch_object($resql);
            $newstatut = ($obj->statut == 4) ? 5 : 4;
            $result = $this->update_contact($rowid, $newstatut);
            $this->db->free($resql);
            return $result;
        }
        else
        {
            $this->error=$this->db->error();
            dol_print_error($this->db);
            return -1;
        }

    }
    
        /**
     *      Update a link to contact line
     *
     *      @param	int		$rowid              Id of line contact-element
     * 		@param	int		$statut	            New status of link
     *      @param  int		$type_contact_id    Id of contact type (not modified if 0)
     *      @param  int		$fk_socpeople	    Id of soc_people to update (not modified if 0)
     *      @return int                 		<0 if KO, >= 0 if OK
     */
    function update_contact($rowid, $statut, $type_contact_id=0, $fk_socpeople=0)
    {
        // Insertion dans la base
        $sql = "UPDATE ".MAIN_DB_PREFIX."societe_contact set";
        $sql.= " statut = ".$statut;
        if ($type_contact_id) $sql.= ", fk_c_type_contact = '".$type_contact_id ."'";
        if ($fk_socpeople) $sql.= ", fk_socpeople = '".$fk_socpeople ."'";
        $sql.= " where rowid = ".$rowid;
        $resql=$this->db->query($sql);
        if ($resql)
        {
            return 0;
        }
        else
        {
            $this->error=$this->db->lasterror();
            return -1;
        }
    }
}
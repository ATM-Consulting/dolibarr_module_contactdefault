<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file		core/triggers/interface_99_modMyodule_ContactDefaulttrigger.class.php
 * 	\ingroup	contactdefault
 * 	\brief		Sample trigger
 * 	\remarks	You can create other triggers by copying this one
 * 				- File name should be either:
 * 					interface_99_modMymodule_Mytrigger.class.php
 * 					interface_99_all_Mytrigger.class.php
 * 				- The file must stay in core/triggers
 * 				- The class name must be InterfaceMytrigger
 * 				- The constructor method must be named InterfaceMytrigger
 * 				- The name property name must be Mytrigger
 */

/**
 * Trigger class
 */
class InterfaceContactDefaulttrigger
{

    private $db;

    /**
     * Constructor
     *
     * 	@param		DoliDB		$db		Database handler
     */
    public function __construct($db)
    {
        $this->db = &$db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "demo";
        $this->description = "Triggers of this module are empty functions."
            . "They have no effect."
            . "They are provided for tutorial purpose only.";
        // 'development', 'experimental', 'dolibarr' or version
        $this->version = 'development';
        $this->picto = 'contactdefault@contactdefault';
    }

    /**
     * Trigger name
     *
     * 	@return		string	Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * 	@return		string	Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }

    /**
     * Trigger version
     *
     * 	@return		string	Version of trigger file
     */
    public function getVersion()
    {
        global $langs;
        $langs->load("admin");

        if ($this->version == 'development') {
            return $langs->trans("Development");
        } elseif ($this->version == 'experimental')

                return $langs->trans("Experimental");
        elseif ($this->version == 'dolibarr') return DOL_VERSION;
        elseif ($this->version) return $this->version;
        else {
            return $langs->trans("Unknown");
        }
    }

    /**
     * Function called when a Dolibarrr business event is done.
     * All functions "run_trigger" are triggered if file
     * is inside directory core/triggers
     *
     * 	@param		string		$action		Event action code
     * 	@param		Object		$object		Object
     * 	@param		User		$user		Object user
     * 	@param		Translate	$langs		Object langs
     * 	@param		conf		$conf		Object conf
     * 	@return		int						<0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function run_trigger($action, $object, $user, $langs, $conf)
    {
		// Lors de la création d'un document, récupération des contacts et rôle associés à la société et association avec le document
		if ($action === 'PROPAL_CREATE' || $action === 'ORDER_CREATE' || $action === 'BILL_CREATE'	|| $action === 'ORDER_SUPPLIER_CREATE' || $action === 'BILL_SUPPLIER_CREATE'
			|| $action === 'CONTRACT_CREATE' || $action === 'FICHINTER_CREATE' || $action === 'PROJECT_CREATE') {
			
			if(!empty($object->socid) && $object->socid != '-1') {
				global $db, $langs;
				$langs->load('contactdefault@contactdefault');
				dol_include_once('/contactdefault/class/contactdefault.class.php');
				
				$contactdefault = new ContactDefault($this->db, $object->socid);
				$TContact = $contactdefault->get_contact($object->element);
				
				// Le trigger est appelé avant que le core n'ajoute lui-même des contacts (contact propale, clone), il ne faut pas les associer avant sinon bug
				$TContactAlreadyLinked = array();
				$objectid = GETPOST('id');
				if(empty($objectid)) $objectid = GETPOST('facid'); // Gestion du cas de la facture ou l'URL ne contient pas 'id' mais 'facid' lors du clone
				if ($objectid > 0)
				{
					$class = get_class($object);
					$cloneFrom = new $class($db);
					$cloneFrom->fetch($objectid);
					$TContactAlreadyLinked = array_merge($cloneFrom->liste_contact(-1,'external'), $cloneFrom->liste_contact(-1,'internal'));
				}

				foreach($TContact as $i => $infos) {
					// Gestion du cas spécifique de la création de propale avec sélection du contact, cela créé un bug si le contact est ajouté par le module contactdefault avant
					if(GETPOST('contactidp') == $infos['fk_socpeople'] && $infos['type_contact'] == 41) unset($TContact[$i]);
					if(GETPOST('contactid') == $infos['fk_socpeople'] && $infos['type_contact'] == 41) unset($TContact[$i]); // contactid >= 3.7
					// Gestion du cas spécifique de la création de comamnde avec sélection du contact (nouveau 3.7)
					if(GETPOST('contactid') == $infos['fk_socpeople'] && $infos['type_contact'] == 101) unset($TContact[$i]); // contactid >= 3.7
					
					// Gestion du cas du clone
					foreach ($TContactAlreadyLinked as $contactData) {
						if($contactData['id'] == $infos['fk_socpeople'] && $contactData['fk_c_type_contact'] == $infos['type_contact']) unset($TContact[$i]);
					}
				}
				
				/*echo '<pre>';
				print_r($_REQUEST);
				print_r($object);
				print_r($TContact);
				print_r($TContactAlreadyLinked);
				exit;*/
				
				$nb = 0;
				foreach($TContact as $infos) {
					// Gestion du cas spécifique de la création de propale avec sélection du contact, cela créé un bug si le contact est ajouté par le module contactdefault
					if(GETPOST('contactidp') == $infos['fk_socpeople'] && $infos['type_contact'] == 41) continue;
					$res = $object->add_contact($infos['fk_socpeople'], $infos['type_contact']);
					if($res > 0) $nb++;
				}
				
				if($nb > 0) {
					setEventMessage($langs->trans('ContactAddedAutomatically', $nb));
				}
			}
			
			dol_syslog(
				"Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
			);
		}

		return 0;
	}
}

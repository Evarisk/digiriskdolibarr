<?php
/* Copyright (C) 2021 EOXIA <dev@eoxia.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * or see https://www.gnu.org/
 */

/**
 *	\file       htdocs/core/modules/digiriskdolibarr/digiriskdocuments/firepermitdocument/doc_firepermitdocument_odt.modules.php
 *	\ingroup    digiriskdolibarr
 *	\brief      File of class to build ODT documents for digiriskdolibarr
 */

require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/doc.lib.php';

require_once __DIR__ . '/modules_firepermitdocument.php';
require_once __DIR__ . '/mod_firepermitdocument_standard.php';
require_once __DIR__ . '/../../../../../class/evaluator.class.php';
require_once __DIR__ . '/../../../../../class/riskanalysis/risk.class.php';
require_once __DIR__ . '/../../../../../class/riskanalysis/riskassessment.class.php';
require_once __DIR__ . '/../../../../../class/riskanalysis/risksign.class.php';

/**
 *	Class to build documents using ODF templates generator
 */
class doc_firepermitdocument_odt extends ModeleODTFirePermitDocument
{
	/**
	 * Issuer
	 * @var Societe
	 */
	public $emetteur;

	/**
	 * @var array Minimum version of PHP required by module.
	 * e.g.: PHP ≥ 5.5 = array(5, 5)
	 */
	public $phpmin = array(5, 5);

	/**
	 * @var string Dolibarr version of the loaded document
	 */
	public $version = 'dolibarr';

	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		global $langs, $mysoc;

		// Load translation files required by the page
		$langs->loadLangs(array("main", "companies"));

		$this->db = $db;
		$this->name = $langs->trans('FirePermitDocumentDigiriskTemplate');
		$this->description = $langs->trans("DocumentModelOdt");
		$this->scandir = 'DIGIRISKDOLIBARR_FIREPERMITDOCUMENT_ADDON_ODT_PATH'; // Name of constant that is used to save list of directories to scan

		// Page size for A4 format
		$this->type = 'odt';
		$this->page_largeur = 0;
		$this->page_hauteur = 0;
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = 0;
		$this->marge_droite = 0;
		$this->marge_haute = 0;
		$this->marge_basse = 0;

		// Recupere emetteur
		$this->emetteur = $mysoc;
		if (!$this->emetteur->country_code) $this->emetteur->country_code = substr($langs->defaultlang, -2); // By default if not defined
	}

	/**
	 *	Return description of a module
	 *
	 *	@param	Translate	$langs      Lang object to use for output
	 *	@return string       			Description
	 */
	public function info($langs)
	{
		global $conf, $langs;

		// Load translation files required by the page
		$langs->loadLangs(array("errors", "companies"));

		$form = new Form($this->db);
		$texte = $this->description.".<br>\n";
		$texte .= '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
		$texte .= '<input type="hidden" name="token" value="'.newToken().'">';
		$texte .= '<input type="hidden" name="action" value="setModuleOptions">';
		$texte .= '<input type="hidden" name="param1" value="DIGIRISKDOLIBARR_FIREPERMITDOCUMENT_ADDON_ODT_PATH">';
		$texte .= '<table class="nobordernopadding" width="100%">';

		// List of directories area
		$texte .= '<tr><td>';
		$texttitle = $langs->trans("ListOfDirectories");
		$listofdir = explode(',', preg_replace('/[\r\n]+/', ',', trim($conf->global->DIGIRISKDOLIBARR_FIREPERMITDOCUMENT_ADDON_ODT_PATH)));
		$listoffiles = array();
		foreach ($listofdir as $key=>$tmpdir)
		{
			$tmpdir = trim($tmpdir);
			$tmpdir = preg_replace('/DOL_DATA_ROOT/', DOL_DATA_ROOT, $tmpdir);
			if (!$tmpdir) {
				unset($listofdir[$key]); continue;
			}
			if (!is_dir($tmpdir)) $texttitle .= img_warning($langs->trans("ErrorDirNotFound", $tmpdir), 0);
			else
			{
				$tmpfiles = dol_dir_list($tmpdir, 'files', 0, '\.(ods|odt)');
				if (count($tmpfiles)) $listoffiles = array_merge($listoffiles, $tmpfiles);
			}
		}

		// Scan directories
		$nbofiles = count($listoffiles);
		if (!empty($conf->global->DIGIRISKDOLIBARR_FIREPERMITDOCUMENT_ADDON_ODT_PATH))
		{
			$texte .= $langs->trans("DigiriskNumberOfModelFilesFound").': <b>';
			//$texte.=$nbofiles?'<a id="a_'.get_class($this).'" href="#">':'';
			$texte .= count($listoffiles);
			//$texte.=$nbofiles?'</a>':'';
			$texte .= '</b>';
		}

		if ($nbofiles)
		{
			$texte .= '<div id="div_'.get_class($this).'" class="hidden">';
			foreach ($listoffiles as $file)
			{
				$texte .= $file['name'].'<br>';
			}
			$texte .= '</div>';
		}

		$texte .= '</td>';
		$texte .= '</table>';
		$texte .= '</form>';

		return $texte;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Function to build a document on disk using the generic odt module.
	 *
	 *	@param		FirePermitDocument	$object				Object source to build document
	 *	@param		Translate	$outputlangs		Lang output object
	 * 	@param		string		$srctemplatepath	Full path of source filename for generator using a template file
	 *  @param		int			$hidedetails		Do not show line details
	 *  @param		int			$hidedesc			Do not show desc
	 *  @param		int			$hideref			Do not show ref
	 *	@return		int         					1 if OK, <=0 if KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath, $hidedetails = 0, $hidedesc = 0, $hideref = 0, $firepermit)
	{
		// phpcs:enable
		global $user, $langs, $conf, $hookmanager, $action;

		if (empty($srctemplatepath))
		{
			dol_syslog("doc_firepermitdocument_odt::write_file parameter srctemplatepath empty", LOG_WARNING);
			return -1;
		}

		// Add odtgeneration hook
		if (!is_object($hookmanager))
		{
			include_once DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php';
			$hookmanager = new HookManager($this->db);
		}
		$hookmanager->initHooks(array('odtgeneration'));

		if (!is_object($outputlangs)) $outputlangs = $langs;
		$outputlangs->charset_output = 'UTF-8';

		$outputlangs->loadLangs(array("main", "dict", "companies", "digiriskdolibarr@digiriskdolibarr"));

		$mod = new $conf->global->DIGIRISKDOLIBARR_FIREPERMITDOCUMENT_ADDON($this->db);
		$ref = $mod->getNextValue($object);

		$object->ref = $ref;
		$id = $object->create($user, true, $firepermit);

		$object->fetch($id);

		$dir = $conf->digiriskdolibarr->multidir_output[isset($object->entity) ? $object->entity : 1] . '/firepermitdocument/'. $firepermit->ref;
		$objectref = dol_sanitizeFileName($ref);
		if (preg_match('/specimen/i', $objectref)) $dir .= '/specimen';

		if (!file_exists($dir))
		{
			if (dol_mkdir($dir) < 0)
			{
				$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
				return -1;
			}
		}

		if (file_exists($dir))
		{
			$filename = preg_split('/firepermitdocument\//' , $srctemplatepath);
			$filename = preg_replace('/template_/','', $filename[1]);

			$filename = $objectref . '_'. $filename;

			$object->last_main_doc = $filename;

			$sql = "UPDATE ".MAIN_DB_PREFIX."digiriskdolibarr_digiriskdocuments";
			$sql .= " SET last_main_doc =" .(!empty($filename) ? "'".$this->db->escape($filename)."'" : 'null');
			$sql .= " WHERE rowid = ".$object->id;

			dol_syslog("admin.lib::Insert last main doc", LOG_DEBUG);
			$this->db->query($sql);
			$file = $dir.'/'.$filename;

			dol_mkdir($conf->digiriskdolibarr->dir_temp);

			// Make substitution
			$substitutionarray = array();
			complete_substitutions_array($substitutionarray, $langs, $firepermit);
			// Call the ODTSubstitution hook
			$parameters = array('file'=>$file, 'object'=>$firepermit, 'outputlangs'=>$outputlangs, 'substitutionarray'=>&$substitutionarray);
			$reshook = $hookmanager->executeHooks('ODTSubstitution', $parameters, $this, $action); // Note that $action and $firepermit may have been modified by some hooks

			// Open and load template
			require_once ODTPHP_PATH.'odf.php';
			try {
				$odfHandler = new odf(
					$srctemplatepath,
					array(
						'PATH_TO_TMP'	  => $conf->digiriskdolibarr->dir_temp,
						'ZIP_PROXY'		  => 'PclZipProxy', // PhpZipProxy or PclZipProxy. Got "bad compression method" error when using PhpZipProxy.
						'DELIMITER_LEFT'  => '{',
						'DELIMITER_RIGHT' => '}'
					)
				);
			}
			catch (Exception $e)
			{
				$this->error = $e->getMessage();
				dol_syslog($e->getMessage(), LOG_INFO);
				return -1;
			}

			$tmparray = $substitutionarray;

			$filearray = dol_dir_list($conf->digiriskdolibarr->multidir_output[$conf->entity] . '/' . $firepermit->element_type . '/' . $firepermit->ref, "files", 0, '', '(\.odt|_preview.*\.png)$', 'position_name', 'desc', 1);
			if (count($filearray)) {
				$image = array_shift($filearray);
				$tmparray['photoDefault'] = $image['fullname'];
			}

			$resources          = new DigiriskResources($this->db);
			$societe            = new Societe($this->db);
			$firepermitline = new FirePermitLine($this->db);
			$risk               = new Risk($this->db);

			$firepermitlines = $firepermitline->fetchAll(GETPOST('id'));

			$digirisk_resources      = $resources->digirisk_dolibarr_fetch_resources();
			$extsociety              = $resources->fetchResourcesFromObject('PP_EXT_SOCIETY', $firepermit);
			$extsocietyintervenants  = $resources->fetchResourcesFromObject('PP_EXT_SOCIETY_INTERVENANTS', $firepermit);
			$maitreoeuvre            = $resources->fetchResourcesFromObject('PP_MAITRE_OEUVRE', $firepermit);
			$extsocietyresponsible   = $resources->fetchResourcesFromObject('PP_EXT_SOCIETY_RESPONSIBLE', $firepermit);

			$tmparray['titre_prevention']             = $firepermit->ref;
			$tmparray['unique_identifier']            = $firepermit->label;
			$tmparray['raison_du_plan_de_prevention'] = $firepermit->raison;

			$tmparray['moyen_generaux_mis_disposition'] = $conf->global->DIGIRISK_GENERAL_MEANS;
			$tmparray['consigne_generale']              = $conf->global->DIGIRISK_GENERAL_RULES;
			$tmparray['premiers_secours']               = $conf->global->DIGIRISK_FIRST_AID;

			$tmparray['date_start_intervention_PPP'] = $firepermit->date_start;
			$tmparray['date_end_intervention_PPP'] = $firepermit->date_end;
			$tmparray['interventions_info'] = count($firepermitlines) . " " . $langs->trans('FirePermitLine');


			$openinghours = new Openinghours($this->db);

			$morewhere = ' AND element_id = ' . $firepermit->id;
			$morewhere .= ' AND element_type = ' . "'" . $firepermit->element . "'";
			$morewhere .= ' AND status = 1';

			$openinghours->fetch(0, '', $morewhere);

			$opening_hours_monday    = explode(' ', $openinghours->monday);
			$opening_hours_tuesday   = explode(' ', $openinghours->tuesday);
			$opening_hours_wednesday = explode(' ', $openinghours->wednesday);
			$opening_hours_thursday  = explode(' ', $openinghours->thursday);
			$opening_hours_friday    = explode(' ', $openinghours->friday);
			$opening_hours_saturday  = explode(' ', $openinghours->saturday);
			$opening_hours_sunday    = explode(' ', $openinghours->sunday);

			$tmparray['lundi_matin']    = $opening_hours_monday[0];
			$tmparray['lundi_aprem']    = $opening_hours_monday[1];
			$tmparray['mardi_matin']    = $opening_hours_tuesday[0];
			$tmparray['mardi_aprem']    = $opening_hours_tuesday[1];
			$tmparray['mercredi_matin'] = $opening_hours_wednesday[0];
			$tmparray['mercredi_aprem'] = $opening_hours_wednesday[1];
			$tmparray['jeudi_matin']    = $opening_hours_thursday[0];
			$tmparray['jeudi_aprem']    = $opening_hours_thursday[1];
			$tmparray['vendredi_matin'] = $opening_hours_friday[0];
			$tmparray['vendredi_aprem'] = $opening_hours_friday[1];
			$tmparray['samedi_matin']   = $opening_hours_saturday[0];
			$tmparray['samedi_aprem']   = $opening_hours_saturday[1];
			$tmparray['dimanche_matin'] = $opening_hours_sunday[0];
			$tmparray['dimanche_aprem'] = $opening_hours_sunday[1];

			if (!empty ($digirisk_resources )) {
				$societe->fetch($digirisk_resources['Pompiers']->id[0]);
				$tmparray['pompier_number'] = $societe->phone;

				$societe->fetch($digirisk_resources['SAMU']->id[0]);
				$tmparray['samu_number'] = $societe->phone;

				$societe->fetch($digirisk_resources['AllEmergencies']->id[0]);
				$tmparray['emergency_number'] = $societe->phone;

				$societe->fetch($digirisk_resources['Police']->id[0]);
				$tmparray['police_number'] = $societe->phone;
			}

			//Informations entreprise extérieure

			if (!empty( $extsociety) && $extsociety > 0) {
				$tmparray['society_title']    = $extsociety->name;
				$tmparray['society_siret_id'] = $extsociety->siret;
				$tmparray['society_address']  = $extsociety->address;
				$tmparray['society_postcode'] = $extsociety->zip;
				$tmparray['society_town']     = $extsociety->town;
			}

			if (!empty( $extsocietyintervenants) && $extsocietyintervenants > 0) {
				$extsocietyintervenants = array_shift($extsocietyintervenants);
				$tmparray['intervenants_info'] = count($extsocietyintervenants);
			}

			//Signatures
			if (!empty( $maitreoeuvre) && $maitreoeuvre > 0) {
				$tmparray['maitre_oeuvre_lname'] = $maitreoeuvre->lastname;
				$tmparray['maitre_oeuvre_fname'] = $maitreoeuvre->firstname;
				$tmparray['maitre_oeuvre_email'] = $maitreoeuvre->email;
				$tmparray['maitre_oeuvre_phone'] = $maitreoeuvre->phone;

				//@todo when attendance will be created
				$tmparray['maitre_oeuvre_signature_date'] = '';
				$tmparray['maitre_oeuvre_signature']      = '';
			}

			if (!empty( $extsocietyresponsible) && $extsocietyresponsible > 0) {
				$tmparray['intervenant_exterieur_lname'] = $extsocietyresponsible->lastname;
				$tmparray['intervenant_exterieur_fname'] = $extsocietyresponsible->firstname;
				$tmparray['intervenant_exterieur_email'] = $extsocietyresponsible->email;
				$tmparray['intervenant_exterieur_phone'] = $extsocietyresponsible->phone;

				//@todo when attendance will be created
				$tmparray['intervenant_exterieur_signature_date'] = '';
				$tmparray['intervenant_exterieur_signature']      = '';
			}

			foreach ($tmparray as $key=>$value)
			{
				try {
					if ($key == 'photoDefault') // Image
					{
						$list = getimagesize($value);
						$newWidth = 350;
						if ($list[0]) {
							$ratio = $newWidth / $list[0];
							$newHeight = $ratio * $list[1];
							dol_imageResizeOrCrop($value, 0, $newWidth, $newHeight);
						}
						$odfHandler->setImage($key, $value);
					}
					else    // Text
					{
						$odfHandler->setVars($key, $value, true, 'UTF-8');
					}
				}
				catch (OdfException $e)
				{
					dol_syslog($e->getMessage(), LOG_INFO);
				}
			}
			// Replace tags of lines
			try {
				$foundtagforlines = 1;
				if ($foundtagforlines) {

					if (!empty($firepermitlines) && $firepermitlines > 0) {
						$listlines = $odfHandler->setSegment('interventions');

						foreach ($firepermitlines as $line) {

							$tmparray['key_unique']    = $line->ref;
							$tmparray['unite_travail'] = $line->location;
							$tmparray['action']        = $line->description;
							$tmparray['risk']          = DOL_DOCUMENT_ROOT . '/custom/digiriskdolibarr/img/categorieDangers/' . $risk->get_danger_category($line) . '.png';
							$tmparray['prevention']    = $line->prevention_method;

							foreach ($tmparray as $key => $val) {
								try {
									if ($val == $tmparray['risk']) {
										$list = getimagesize($tmparray['risk']);
										$newWidth = 50;
										if ($list[0]) {
											$ratio = $newWidth / $list[0];
											$newHeight = $ratio * $list[1];
											dol_imageResizeOrCrop($val, 0, $newWidth, $newHeight);
										}
										$listlines->setImage($key, $val);
									} else {
										$listlines->setVars($key, $val, true, 'UTF-8');
									}
								} catch (OdfException $e) {
									dol_syslog($e->getMessage(), LOG_INFO);
								} catch (SegmentException $e) {
									dol_syslog($e->getMessage(), LOG_INFO);
								}
							}
							$listlines->merge();
						}
						$odfHandler->mergeSegment($listlines);
					}

					if (!empty($extsocietyintervenants) && $extsocietyintervenants > 0) {
						$listlines = $odfHandler->setSegment('intervenants');

						foreach ($extsocietyintervenants as $line) {

							$tmparray['id']       = $line->id;
							$tmparray['name']     = $line->firstname;
							$tmparray['lastname'] = $line->lastname;
							$tmparray['phone']    = $line->phone;
							$tmparray['mail']     = $line->mail;

							foreach ($tmparray as $key => $val) {
								try {
									$listlines->setVars($key, $val, true, 'UTF-8');
								} catch (OdfException $e) {
									dol_syslog($e->getMessage(), LOG_INFO);
								} catch (SegmentException $e) {
									dol_syslog($e->getMessage(), LOG_INFO);
								}
							}
							$listlines->merge();
						}
						$odfHandler->mergeSegment($listlines);
					}
				}
			}
			catch (OdfException $e)
			{
				$this->error = $e->getMessage();
				dol_syslog($this->error, LOG_WARNING);
				return -1;
			}

			// Replace labels translated
			$tmparray = $outputlangs->get_translations_for_substitutions();
			foreach ($tmparray as $key=>$value)
			{
				try {
					$odfHandler->setVars($key, $value, true, 'UTF-8');
				}
				catch (OdfException $e)
				{
					dol_syslog($e->getMessage(), LOG_INFO);
				}
			}

			// Call the beforeODTSave hook
			$parameters = array('odfHandler'=>&$odfHandler, 'file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs, 'substitutionarray'=>&$tmparray);
			$reshook = $hookmanager->executeHooks('beforeODTSave', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks

			// Write new file
			if (!empty($conf->global->MAIN_ODT_AS_PDF)) {
				try {
					$odfHandler->exportAsAttachedPDF($file);
				} catch (Exception $e) {
					$this->error = $e->getMessage();
					dol_syslog($e->getMessage(), LOG_INFO);
					return -1;
				}
			}
			else {
				try {
					$odfHandler->saveToDisk($file);
				} catch (Exception $e) {
					$this->error = $e->getMessage();
					dol_syslog($e->getMessage(), LOG_INFO);
					return -1;
				}
			}

			$parameters = array('odfHandler'=>&$odfHandler, 'file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs, 'substitutionarray'=>&$tmparray);
			$reshook = $hookmanager->executeHooks('afterODTCreation', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks

			if (!empty($conf->global->MAIN_UMASK))
				@chmod($file, octdec($conf->global->MAIN_UMASK));

			$odfHandler = null; // Destroy object

			$this->result = array('fullpath'=>$file);

			return 1; // Success
		}
		else
		{
			$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
			return -1;
		}

		return -1;
	}
}
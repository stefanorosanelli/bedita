<?php
/**
 *
 * PHP versions 5
 *
 * CakePHP :  Rapid Development Framework <http://www.cakephp.org/>
 * Copyright (c)	2006, Cake Software Foundation, Inc.
 *								1785 E. Sahara Avenue, Suite 490-204
 *								Las Vegas, Nevada 89104
 *
 * @filesource
 * @copyright		Copyright (c) 2007
 * @link
 * @package
 * @subpackage
 * @since
 * @version
 * @modifiedby
 * @lastmodified
 * @license
 * @author 		giangi giangi@qwerg.com
 *
 * Una comunnity deve essere inserita in un'area o newsletter o sezione.
 * Oltre i dati della community va inserito, nei dati per la creazione di:
 * parent_id
 * ID dell'oggetto contenitore.
 *
*/
class Gallery extends BEAppCollectionModel
{
	var $name 		= 'Gallery';
	var $useTable 	= 'view_galleries' ;
	var $recursive 	= 2 ;

	var $actsAs 	= array(
			'CompactResult' 		=> array(),
			'CreateIndexFields'		=> array(),
			'ForeignDependenceSave' => array('Object', 'Collection'),
			'DeleteObject' 			=> 'objects',
	);


	var $hasOne = array(
			'Object' =>
				array(
					'className'		=> 'BEObject',
					'conditions'   => 'Object.object_type_id = 29',
					'foreignKey'	=> 'id',
					'dependent'		=> true
				),
			'Collection' =>
				array(
					'className'		=> 'Collection',
					'conditions'   => '',
					'foreignKey'	=> 'id',
					'dependent'		=> true
				),
	) ;

	function __construct() {
		parent::__construct() ;
	}

	/**
 	* Sovrascrive completamente il save() l'oggetto non ha una tabella
 	* specifica ma una vista, non deve salvare
 	*/
	function save($data = null, $validate = true, $fieldList = array()) {
		$conf = Configure::getInstance() ;

		if(isset($data['Object']) && !isset($data['Object']['object_type_id'])) {
			$data['Object']['object_type_id'] = $conf->objectTypes[strtolower($this->name)] ;
		} else if(!isset($data['object_type_id'])) {
			$data['object_type_id'] = $conf->objectTypes[strtolower($this->name)] ;
		}

		$this->set($data);

		if ($validate && !$this->validates()) {
			return false;
		}

		if (!empty($this->behaviors)) {
			$behaviors = array_keys($this->behaviors);
			$ct = count($behaviors);
			for ($i = 0; $i < $ct; $i++) {
				if ($this->behaviors[$behaviors[$i]]->beforeSave($this) === false) {
					return false;
				}
			}
		}

		if(empty($this->id)) $created = true ;
		else $created = false ;

		$this->setInsertID($this->Object->id);
		$this->id = $this->Object->id ;

		if (!empty($this->behaviors)) {
			$behaviors = array_keys($this->behaviors);
			$ct = count($behaviors);
			for ($i = 0; $i < $ct; $i++) {
				$this->behaviors[$behaviors[$i]]->afterSave($this, null);
			}
		}

		$this->afterSave($created) ;
		$this->data = false;
		$this->_clearCache();
		$this->validationErrors = array();

		return true ;
	}

	/**
	 * Associa la community ad un contenitore quando viene creata
	 */
	function afterSave($created) {
		if (!$created) return ;

		if(!class_exists('Tree')) loadModel('Tree');
		$tree 	=& new Tree();
		$tree->appendChild($this->id, null) ;
	}
}
?>

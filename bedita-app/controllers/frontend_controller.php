<?php
/*-----8<--------------------------------------------------------------------
 * 
 * BEdita - a semantic content management framework
 * 
 * Copyright 2008 ChannelWeb Srl, Chialab Srl
 * 
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the Affero GNU General Public License as published 
 * by the Free Software Foundation, either version 3 of the License, or 
 * (at your option) any later version.
 * BEdita is distributed WITHOUT ANY WARRANTY; without even the implied 
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the Affero GNU General Public License for more details.
 * You should have received a copy of the Affero GNU General Public License 
 * version 3 along with BEdita (see LICENSE.AGPL).
 * If not, see <http://gnu.org/licenses/agpl-3.0.html>.
 * 
 *------------------------------------------------------------------->8-----
 */

/**
 * Frontend base class (Frontend API)
 * 
 * @link			http://www.bedita.com
 * @version			$Revision$
 * @modifiedby 		$LastChangedBy$
 * @lastmodified	$LastChangedDate$
 * 
 * $Id$
 */
if(defined('BEDITA_CORE_PATH')) {
	require_once (BEDITA_CORE_PATH . DS . 'bedita_exception.php');
}

abstract class FrontendController extends AppController {

	private $status = array('on');
	protected $checkPubDate = true;
	protected $baseLevel = false;
	protected $sectionOptions = array("showAllContents" => true, "itemsByType" => false, "childrenParams" => array());
	protected $xmlFormat = "attributes"; // possible values "tags", "attributes"
	protected $publication = "";
	protected $captchaOptions = array(); // default defined in captcha component
	protected $annotationOptions = array("comment" => array());
	protected $tagOptions = array();

	protected function checkLogin() {
		return false; // every frontend has to implement checkLogin
	}
	
	/**
	 * $uses & $components array don't work... (abstract class ??)
	 */
	final protected function initAttributes() {
		if(!isset($this->BEObject)) {
			$this->BEObject = $this->loadModelByType('BEObject');
		}
		if(!isset($this->Section)) {
			$this->Section = $this->loadModelByType('Section');
		}
		if(!isset($this->Stream)) {
			$this->Stream = $this->loadModelByType('Stream');
		}
		if(!isset($this->BeLangText)) {
			App::import('Component', 'BeLangText');
			$this->BeLangText = new BeLangTextComponent();
		}
		if(!isset($this->Tree)) {
			$this->Tree = $this->loadModelByType('Tree');
		}
		$conf = Configure::getInstance() ;
		if (!empty($conf->draft))
			$this->status[] = "draft";
			
		// check publication status		
		$pubStatus = $this->BEObject->field("status", array("id" => Configure::read("frontendAreaId")));
				
		if ($pubStatus != "on") {
			$this->status = array('on', 'off', 'draft');
			$this->publication = $this->loadObj(Configure::read("frontendAreaId"));
			$this->set('publication', $this->publication);
			throw new BeditaPublicationException($pubStatus);
		} else {
			$this->publication = $this->loadObj(Configure::read("frontendAreaId"));
			// set publication data for template
			$this->set('publication', $this->publication);
		}
		
		// set filterPublicationDate
		$filterPubDate = Configure::read("filterPublicationDate");
		if (isset($filterPubDate)) 
			$this->checkPubDate = $filterPubDate;
	}

	/**
	 * Called in beforefilter...session, cookie, http agent...
	 *
	 */
	protected function setupLocale() {

		$this->currLang = $this->Session->read('Config.language');

		if($this->currLang === null || empty($this->currLang)) {
			$conf = Configure::getInstance();
			if (isset($conf->cookieName["langSelect"])) {
				$lang = $this->Cookie->read($conf->cookieName["langSelect"]);
			}
			if(!empty($lang)) {
				$this->currLang = $lang;
			} else {
				// HTTP autodetect
				$l10n = new L10n();
				$l10n->get();
				$this->currLang = $l10n->lang;
				if(!isset($this->currLang)) {
					$this->currLang = $conf->frontendLang;
				} else if(!array_key_exists($this->currLang, $conf->frontendLangs)) {
					if (isset($conf->frontendLangsMap) && $lang = $conf->frontendLangsMap[$this->currLang]) {
						$this->currLang = (!empty($lang))? $lang : $conf->frontendLang;
					} else {
						$this->currLang = $conf->frontendLang;
					}
				}
			}

			$this->Session->write('Config.language', $this->currLang);
			Configure::write('Config.language', $this->currLang);
		}
		$this->set('currLang', $this->currLang);
	}

	public function changeLang($lang, $forward = null) {

		if (empty($lang)) {
			throw new BeditaException("No lang selected");
		}

		$conf = Configure::getInstance();
		if (!array_key_exists($lang, $conf->frontendLangs)) {
			throw new BeditaException("wrong lang selected: ".$lang);
		}
		$this->Session->write('Config.language', $lang);
		$this->Cookie->write($conf->cookieName["langSelect"], $lang, null, '+350 day'); 
		$this->currLang = $lang;

		if(!empty($forward)) {
			if (substr($forward, 0, 5) != "http:") {
				if (strpos("/", $forward) != 1)
					$forward = "/" . $forward;
					
				if (!empty($this->params["pass"][2])) {
					$forward .= "/" . implode("/", array_slice($this->params["pass"],2));
				}
			}
			
			$this->redirect($forward);
		} else {
			$this->redirect($this->referer());
		}

	}

	
		/**
	 * check if current date is compatible with required pubblication dates (start/end date)
	 *
	 * @param array $obj
	 * @return true if content may be published, false otherwise
	 */
	protected function checkPubblicationDate(array $obj) {
		$currDate = strftime("%Y-%m-%d");
		if(isset($obj["start"])) {
			if(strncmp($currDate, $obj["start"], 10) < 0)
				return false;
		}
		if(isset($obj["end"])) {
			if(strncmp($currDate, $obj["end"], 10) > 0)
				return false;
		}
		return true;
	}
	
	public static function handleExceptions(Exception $ex) {

		if ($ex instanceof BeditaPublicationException) {
			$currentController = AppController::currentController();
			echo $currentController->render(false, $ex->status);
		} else {
			
			if($ex instanceof BeditaException) {
				$errTrace =  $ex->errorTrace();   
			} else {
				$errTrace =  get_class($ex)." -  ". $ex->getMessage().
					"\nFile: ".$ex->getFile()." - line: ".$ex->getLine()."\nTrace:\n".$ex->getTraceAsString();   
			}
			include_once (APP . 'app_error.php');
			return new AppError('handleExceptionFrontend', 
					array('details' => $ex->getDetails(), 'msg' => $ex->getMessage(), 
					'result' => $ex->result), $errTrace);
					
		}
	}
	
	public function handleError($eventMsg, $userMsg, $errTrace) {
		if(Configure::read('debug') > 0) {
			$this->log($errTrace);
		}
	}
	
	/**
	* Get tree starting from specified section or area
	* 
	* @param integer $parentName		parent nickname or id 
	* @param bool $loadContents			if it's true load all contents too. Default false
	* @param array $exclude_nicknames	list exclude sections 
	* @param integer $depth				tree's depth level (default=1000 => all levels)
	* */
	protected function loadSectionsTree($parentName,  $loadContents=false, array $exclude_nicknames=null, $depth=1000) {

		$conf = Configure::getInstance(); 
		$parent_id = is_numeric($parentName) ? $parentName: $this->BEObject->getIdFromNickname($parentName);
		$result = array();
		$filter["object_type_id"] = $conf->objectTypes['section']["id"];
		$sections = $this->BeTree->getChildren($parent_id, $this->status, 
			$filter, "priority") ;

		foreach ($sections['items'] as $s) {
			
			if(!empty($exclude_nicknames) && in_array($s['nickname'], $exclude_nicknames)) 
				continue ;
			
			$sectionObject = $this->loadObj($s['id']);			
			if($loadContents) {
				$sectionObject['objects'] = $this->loadSectionObjects($s['id']);	
			}
			if ($depth > 1)
				$sectionObject['sections'] = $this->loadSectionsTree($s['id'], $loadContents, $exclude_nicknames, $depth-1);
			$result[] = $sectionObject;
		}

		return $result;
	}

	/**
	* Get sections levels
	* 
	* Find all ancestors from secName and build an array of levels 
	* Each key in array returned is a level:
	* 	0 is the first level
	* 	1 is the second level
	* 	etc...
	* 
	* set selected = true in a section if it's an ancestor (parent) of $secName
	* 
	* @param  $secName					nickname or section id
	* @param  bool $loadContents		true meaning it loads all contents of each section 
	* @param array $exclude_nicknames	list exclude sections 
	* 
	* @return array of level selected 
	* 							
	* */
	protected function loadSectionsLevels($secName, $loadContents=false, array $exclude_nicknames=null) {
		$conf = Configure::getInstance(); 
		$result = array();
		
		$section_id = is_numeric($secName) ? $secName : $this->BEObject->getIdFromNickname($secName);
		
		$path = $this->Tree->field("path", array("id" => $section_id));
		$parents = explode("/", trim($path,"/"));
		
		$level = 0;
		$filter["object_type_id"] = $conf->objectTypes['section']["id"];
		foreach ($parents as $p_id) {
			$sections = $this->BeTree->getChildren($p_id, $this->status, 
				$filter, "priority") ;

			foreach ($sections["items"] as $s) {
				
				if(!empty($exclude_nicknames) && in_array($s['nickname'], $exclude_nicknames)) 
					continue ;
				
				$sectionObject = $this->loadObj($s['id']);
				
				if (in_array($s["id"], $parents)) {
				 	$sectionObject["selected"] = true;
				}
				
				if($loadContents) {
					$sectionObject['objects'] = $this->loadSectionObjects($s['id']);	
				}
				$result[$level][] = $sectionObject;
				
			}

			$level++;
		}
		return $result;
	}
	
	public function sitemapXml() {
		$this->sitemap(true);
		$this->layout = NULL;
		$this->view = "Smarty";
		header("Content-type: text/xml; charset=utf-8");
	}

	public function sitemap($xml_out = false) {
		$conf = Configure::getInstance() ;
		$extract_all = (!empty($conf->sitemapAllContent)) ? $conf->sitemapAllContent : false;
		if(!in_array('BeTree', $this->helpers)) {
			$this->helpers[] = 'BeTree';
		}
		$urlset = array();
		if($xml_out) {
			$filter = null;
			if(!$extract_all) {
				$filter = array();
				$filter["object_type_id"] = $conf->objectTypes['section']["id"];
			}
			$sections = $this->BeTree->getDiscendents($conf->frontendAreaId,$this->status,$filter) ;
			$sectionsTree = $sections['items'];
		} else {
			$this->baseLevel = true;
			$itemsByType = $this->sectionOptions["itemsByType"];
			$this->sectionOptions["itemsByType"] = false;
			$sectionsTree = $this->loadSectionsTree($conf->frontendAreaId,$extract_all) ;
			$this->sectionOptions["itemsByType"] = $itemsByType;
			$this->baseLevel = false;
		}
		$i=0;
		$public_url = $this->Area->field('public_url', array('id' => $conf->frontendAreaId));
		foreach($sectionsTree as $k => $v) {
			$urlset[$i] = array();
			$urlset[$i]['loc'] = $public_url."/".$v['nickname'];
			//$urlset['lastmode'] = $this->BeTree->getChildren($id, null, $filter, "title", true, $page, $dim=1);
			//$urlset[$i]['changefreq'] = 'always'; /*always,hourly,daily,weekly,monthly,yearly,never*/
			//$urlset[$i]['priority'] = '0.5';
			$i++;
		}
		$this->set('sections_tree',$sectionsTree);
		$this->set('urlset',$urlset);
		$this->set('public_url',$public_url);
		$this->sitemapBeforeRender();
	}

	protected function sitemapBeforeRender() {}
	
	/**
	 * Publish RSS feed with contents inside section $sectionName
	 *
	 * @param string $sectionName, section's nickname
	 */
	public function rss($sectionName) {
	   $s = $this->loadObjByNick($sectionName);
	   if($s['syndicate'] === "off") {
	   		throw new BeditaException(__("Content not found", true));
	   }
	   
	   $channel = array( 'title' => $this->publication["public_name"] . " - " . $s['title'] , 
        'link' => "/section/".$sectionName,
//        'url' => Router::url("/section/".$sectionName),
        'description' => $s['description'],
        'language' => $s['lang'],
       );
	   $this->set('channelData', $channel);
       $rssItems = array();
	   $items = $this->BeTree->getChildren($s['id'], $this->status, false, "priority", ($s['priority_order']=="asc"));
	   if(!empty($items) && !empty($items['items'])) {
			foreach($items['items'] as $index => $item) {
				$obj = $this->loadObj($item['id']);
	            $rssItems[] = array( 'title' => $obj['title'], 'description' => $obj['description'],
	                'pubDate' => $obj['created'], 'link' => "/section/".$s['nickname']."/".$item['id']);
			}
		}
       $this->set('items', $rssItems);
       $this->view = 'View';
       // add RSS helper if not present
       if(!in_array('Rss', $this->helpers)) {
       		$this->helpers[] = 'Rss';
       }
       $this->layout = NULL;
	}
	
	/**
	 * output a json object of returned array by section or content method
	 * @param $name
	 * @return unknown_type $name, nickname or id
	 */
	public function json($name) {
		$this->route($name);
		header("Content-Type: application/json");
		$this->view = 'View';
		$this->layout = null;
		$this->action = "json";
		$this->set("data", $this->viewVars["section"]);
	}
	
	/**
	 * output an xml of returned array by section or content method
	 *
	 * passing a "format" named parameters in the url obtain an xml "attributes" format or an xml "tags" format
	 * i.e. http://www.example.com/xml/nickname/format:tags output a tag style xml 
	 * default is defined by class attribute xmlFormat
	 * 
	 * @param unknown_type $name, nickname or id
	 */
	public function xml($name) {
		$this->route($name);
		$this->outputXML(array("section" => $this->viewVars["section"]));
	}
	
	/**
	 * output an xml of returned array by loadObj/loadObjByNick method
	 *
	 * passing a "format" named parameters in the url obtain an xml "attributes" format or an xml "tags" format
	 * i.e. http://www.example.com/xmlobject/nickname/format:tags output a tag style xml 
	 * default is defined by class attribute xmlFormat
	 * 
	 * @param unknown_type $name, nickname or id
	 */
	public function xmlobject($name) {
		$object = (is_numeric($name))? $this->loadObj($name) : $this->loadObjByNick($name);
		$this->outputXML(array("object" => $object));
	}
	
	private function outputXML($data) {
		header("content-type: text/xml; charset=utf-8");
		if(!in_array('Xml', $this->helpers)) {
       		$this->helpers[] = 'Xml';
		}
		
		$availableFormat = array("attributes", "tags");
		if (!empty($this->passedArgs["format"]) && in_array($this->passedArgs["format"],$availableFormat)) {
			$options = array("format" => $this->passedArgs["format"]);
		} else {
			$options = array("format" => $this->xmlFormat);
		}
		
		$this->set("options", $options);
		$this->set("data", $data);
		$this->action = "xml";
		$this->view = 'View';
		$this->layout = NULL;
	}
	
	/**
	 * Like loadObj using nickname
	 *
	 * @param string $obj_nick
	 * @return array
	 */
	protected function loadObjByNick($obj_nick) {
		return $this->loadObj($this->BEObject->getIdFromNickname($obj_nick));
	}

	/**
	 * Like loadAndSetObj using nickname
	 *
	 * @param string $obj_nick
	 * @return array
	 */
	protected function loadAndSetObjByNick($obj_nick, $var_name = null) {
		$this->loadAndSetObj($this->BEObject->getIdFromNickname($obj_nick) , $var_name);
	}
	
	/**
	 * Load bedita Object, set view var with $var_name or object type (e.g. "Document", "Event"..)
	 * Returns object loaded
	 * Throws Exception on errors
	 *
	 * @param int $obj_id
	 * @param string $var_name
	 * @return array
	 */
	protected function loadAndSetObj($obj_id, $var_name = null) {
		$obj = $this->loadObj($obj_id);
		$this->set((isset($var_name)? $var_name: $obj['object_type']),$obj);
	}
	
	/**
	 * Returns bedita Object
	 * Throws Exception on errors
	 *
	 * @param int $obj_id
	 * @return array
	 */
	protected function loadObj($obj_id) {
		if($obj_id === null)
			throw new BeditaException(__("Content not found", true));
		
		$modelType = $this->BEObject->getType($obj_id);
		if(!isset($this->{$modelType})) {
			$this->{$modelType} = $this->loadModelByType($modelType);
		}

		if (!$this->baseLevel) {
			$this->modelBindings($this->{$modelType});
		} else {
			$this->{$modelType}->contain(array("BEObject" => array("LangText")));
		}
			
		$obj = $this->{$modelType}->find("first", array(
								"conditions" => array(
									"BEObject.id" => $obj_id,
									"status" => $this->status
									)
								)
							);
		if(empty($obj)) {
			throw new BeditaException(__("Content not found", true));
		}
							
		if($this->checkPubDate && !$this->checkPubblicationDate($obj)) {
			throw new BeditaException(__("Content not found", true));
		}

		$this->BeLangText->setObjectLang($obj, $this->currLang, $this->status);

		if(!empty($obj["RelatedObject"])) {
			$obj['relations'] = $this->objectRelationArray($obj['RelatedObject'], $this->status, array("mainLanguage" => $this->currLang));
			unset($obj["RelatedObject"]);
			$obj['relations_count'] = array();
			foreach ($obj["relations"] as $k=>$v) {
				$obj['relations_count'][$k] = count($v);
			}
		}
		if (!empty($obj['Annotation'])) {
			$this->setupAnnotations($obj, $this->status);
		}
		unset($obj['Annotation']);
		
		$obj['object_type'] = $modelType;
		return $obj;
	}

	/**
	 * Load and set objects in section $parent_id
	 *
	 * @param int $parent_id
	 */
	protected function loadAndSetSectionObjects($parent_id) {
		$sectionItems = $this->loadSectionObjects($parent_id);
		foreach($sectionItems as $key => $objs) {
			$this->set($key, $objs);
		}
	}

	/**
	 * Load and set objects in section $parentNick
	 *
	 * @param string $parentNick
	 */
	protected function loadAndSetSectionObjectsByNick($parentNick, $options=array()) {
		$sectionItems = $this->loadSectionObjectsByNick($parentNick, $options);
		foreach($sectionItems as $key => $objs) {
			$this->set($key, $objs);
		}
	}
	
	protected function loadSectionObjectsByNick($parentNick, $options=array()) {
		return $this->loadSectionObjects($this->BEObject->getIdFromNickname($parentNick), $options);
	}	

	/**
	 * Load objects in section $parent_id
	 *
	 * @param int $parent_id
	 * @return array
	 */
	protected function loadSectionObjects($parent_id, $options=array()) {

		if(empty($parent_id)) {
			throw new BeditaException("Bad data");
		}
		
		$this->checkParentStatus($parent_id);
		
		$priorityOrder = $this->Section->field("priority_order", array("id" => $parent_id));
		if(empty($priorityOrder)) {
			$priorityOrder = "asc";
		}
		$sectionItems = array();
		
		$filter = (!empty($options["filter"]))? $options["filter"] : false;
		$order = (!empty($options["order"]))? $options["order"] : "priority";
		$dir = (isset($options["dir"]))? $options["dir"] : ($priorityOrder == "asc");
		$page = (!empty($options["page"]))? $options["page"] : 1;
		$dim = (!empty($options["dim"]))? $options["dim"] : 100000;
		
		// add rules for start and end pubblication date
		if ($this->checkPubDate == true) {
			if (empty($filter["Content.start"]))
				$filter["Content.start"] = "<= '" . date("Y-m-d") . "' OR `Content`.start IS NULL";
			if (empty($filter["Content.end"]))
				$filter["Content.end"] = ">= '" . date("Y-m-d") . "' OR `Content`.end IS NULL";
		}
		
		$items = $this->BeTree->getChildren($parent_id, $this->status, $filter, $order, $dir, $page, $dim);
		
		if(!empty($items) && !empty($items['items'])) {
			foreach($items['items'] as $index => $item) {
				$obj = $this->loadObj($item['id']);
				if ($this->sectionOptions["itemsByType"]) {
					$sectionItems[$obj['object_type']][] = $obj;
				} else {
					if ($obj["object_type"] == Configure::read("objectTypes.section.model"))
						$sectionItems["childSections"][] = $obj;
					else
						$sectionItems["childContents"][] = $obj;
				}
			}
			$sectionItems["toolbar"] = $items['toolbar'];
		}
		return $sectionItems;
	
	}
	
	public function content($name) {
		if(empty($name))
			throw new BeditaException(__("Content not found", true));
		
		$content_id = is_numeric($name) ? $name : $this->BEObject->getIdFromNickname($name);
		
		// if it's defined frontend publication id then search content inside that publication else in all BEdita
		$conditions = (!empty($this->publication["id"]))? "id = $content_id AND path LIKE '/" . $this->publication["id"] . "/%'" : "id = $content_id" ;
		
		$section_id = $this->Tree->field('parent_id',$conditions, "priority");
		
		if($section_id === false) {
			throw new BeditaException(__("Content not found", true));
		}
		$this->action = 'section';
		$this->section($section_id, $content_id);	
	}

		
	/**
	 * find section and contents from section nick or section id and set template vars
	 * 
	 * Set section and:
	 * if $contentName=null set all contents in section
	 * if $contentName is defined set single content (default)
	 * if $contentName is defined and $this->showAllContents=true set content and other contents too 
	 * 
	 * Execute 'sectionNickname'BeforeFilter and/or 'sectionNickName'BeforeRender 
	 * if they're set in the controller (i.e. pages_controller.php)				
	 *
	 * @param string/int $secName: section nick or section id
	 * @param string/int $contentName: content nick or content id
	 */
	public function section($secName, $contentName = null) {
		
		if (is_numeric($secName)) {
			$sectionId = $secName;
			$secName = $this->BEObject->getNicknameFromId($sectionId);
		} else {
			$sectionId = $this->BEObject->getIdFromNickname($secName);
		}		
		
		$content_id = null;
		if(!empty($contentName)) {
			$content_id = is_numeric($contentName) ? $contentName : $this->BEObject->getIdFromNickname($contentName);
			$contentType = $this->BEObject->getType($content_id);
			if($contentType === "Section") {
				$args = func_get_args();
				array_shift($args);
				return call_user_func_array(array($this, "section"), $args);
			}
		}
		
		$secNameFilter = str_replace("-","_",$secName);
		// section before filter
		if (method_exists($this, $secNameFilter . "BeforeFilter")) {
			$this->{$secNameFilter . "BeforeFilter"}();
		}
		
		$section = $this->loadObj($sectionId);
		
		$section["pathSection"] = $this->getPath($sectionId);
		$this->sectionOptions["childrenParams"] = array_merge($this->sectionOptions["childrenParams"],$this->getPassedArgs());
		
		if(!empty($content_id)) {
			$section['currentContent'] = $this->loadObj($content_id);
			$section["contentRequested"] = true;
			
			if ($this->sectionOptions["showAllContents"]) {
				$this->baseLevel = true;
				$checkPubDate = $this->checkPubDate;
				$this->checkPubDate = false;
				
				$tmp = $this->loadSectionObjects($sectionId, $this->sectionOptions["childrenParams"]);
				if (!$this->sectionOptions["itemsByType"])
					$section = array_merge($section, $tmp);
				else
					$section = array_merge($section, array("children" => $tmp));
				
				$this->baseLevel = false;
				$this->checkPubDate = $checkPubDate;
			}
		} else {
			$tmp = $this->loadSectionObjects($sectionId, $this->sectionOptions["childrenParams"]);
			
			if (!$this->sectionOptions["itemsByType"]) {
				$tmp['currentContent'] = (!empty($tmp['childContents']))? $tmp['childContents'][0] : array();
				$section = array_merge($section, $tmp);
			} else {
				if(empty($tmp)) {
					$section = array_merge($section, array("currentContent" => array(), "children" => array()));
				} else {
					$toolbar = $tmp["toolbar"];
					unset($tmp["toolbar"]);
					$current = current($tmp);
					$section = array_merge($section, array("currentContent" => $current[0], "children" => $tmp, "toolbar" => $toolbar));
				}
			}
		}

		$this->set('section', $section);
		
		// section after filter
		if (method_exists($this, $secNameFilter . "BeforeRender")) {
			$this->{$secNameFilter . "BeforeRender"}();
		}
	}
	
	/**
	 * route to section or content
	 *
	 * @param unknown_type $name, id or nickname
	 */
	public function route() {

		$args = func_get_args();
		if(count($args) === 0 || empty($args[0]))
			throw new BeditaException(__("Content not found", true));

		$name = $args[0];
		// look if reserverd 
		if(in_array($name, Configure::read("defaultReservedWords")) ||
			in_array($name, Configure::read("cfgReservedWords"))) {
			$name = str_replace(".", "_", $name); // example: sitemap.xml => sitemap_xml
			$this->action = $name;
			// load object with nickname $name if exists
			$id = $this->BEObject->getIdFromNickname($name);
			if(!empty($id)) {
				$this->loadAndSetObj($id, "object");
			}
			$methodName = $name[0] . substr(Inflector::camelize($name), 1);
			// check method
			if(method_exists($this, $methodName)) {
				array_shift($args);
				call_user_func_array(array($this, $methodName), $args);
			}
			return;
		}
			
		$id = is_numeric($name) ? $name : $this->BEObject->getIdFromNickname($name);
		$object_type_id = $this->BEObject->findObjectTypeId($id);
		
		if ($object_type_id == Configure::read("objectTypes.section.id") || $object_type_id == Configure::read("objectTypes.area.id")) {
			$this->action = "section";
			call_user_func_array(array($this, "section"), $args);
		} else {
			$this->content($id);
		}
	}
	
	/**
	 * find parent path of $object_id (exclude publication)
	 *
	 * @param int $object_id
	 * @return array (the keys are object's id)
	 */
	protected function getPath($object_id) {
		$pathArr = array();
		$path = $this->Tree->field("parent_path", array("id" => $object_id));
		$parents = explode("/", trim($path,"/"));
		if (!empty($parents[0])) {
			$oldBaseLevel = $this->baseLevel; 
			$this->baseLevel = true;
			foreach ($parents as $p) {
				if ($p != $this->publication["id"])
					$pathArr[$p] = $this->loadObj($p);
			}
			$this->baseLevel = $oldBaseLevel;
		}
		return $pathArr;
	}
	
	/**
	 * build archive tree
	 *
	 * Array(
	 * 		"Document" => Array(
	 * 				"2008" => Array(
	 * 					"01" => Array(
	 * 						0 => document,
	 * 						1 => document,
	 * 						...
	 * 						"monthName" => month name
	 * 						"total" => number of document in january
	 * 						),
	 *	 				"02" => Array(...),
	 * 					....
	 * 					"total" => numeber of document in 2008
	 * 				),
	 * 				"2007" => Array(...),
	 * 		"ShortNews" => ....
	 * 		)
	 * 
	 * @param unknown_type $secName section id or section nickname
	 * @return array
	 */
	public function loadArchiveTree($secName, $options=array()) {
		
		$section_id = (is_numeric($secName))? $secName : $this->BEObject->getIdFromNickname($secName);
		
		$monthName = array("01" => "January", "02" => "February", "03" => "March", "04" => "April", "05" => "May",
						   "06" => "June", "07" => "July", "08" => "August", "09" => "September", "10" => "October",
						   "11" => "November", "12" => "December");
	
		$this->modelBindings['Document'] = array("BEObject" => array("LangText"));
		$this->modelBindings['ShortNews'] = array("BEObject" => array("LangText"));
		$this->modelBindings['Event'] = array("BEObject" => array("LangText"),"DateItem");
		
		$oldItemsByType = $this->sectionOptions['itemsByType'];
		$this->sectionOptions['itemsByType'] = true;
		$items = $this->loadSectionObjects($section_id);
		unset($this->modelBindings);
		$this->sectionOptions['itemsByType'] = $oldItemsByType;
		
		$archive = array();
		
		foreach ($items as $type => $itemGroup) {
		
			if($type != "toolbar") {
			
				foreach ($itemGroup as $item) {
					
					// DateItem, pubblication or creation date
					if(!empty($item["DateItem"][0]["start"]))
						$refDate = $item["DateItem"][0]["start"];
					else
						$refDate = isset($item["start"])? $item["start"] : $item["created"];
					 
					$data = explode("-", $refDate);
					$year = $data[0];
					$id = $item["id"];
					$item["title"] = (!empty($item["LangText"]["title"][$this->currLang]))? $item["LangText"]["title"][$this->currLang] : $item["title"];
					$archive[$type][$year][$data[1]][] = $item;
				}
				
				// sort archive
				$sortFunction = "ksort";
				if (!empty($options["archiveSort"]) && $options["archiveSort"] == "desc")
					$sortFunction = "krsort";
				
				$sortFunction($archive[$type]);
				foreach ($archive[$type] as $year => $month) {
					$sortFunction($archive[$type][$year]);
				}
				
				// add number of items for month and year
				$countYear = 0;
				foreach ($archive[$type] as $year => $month) {
					
					$countYear = 0;
					foreach ($month as $key => $i) {
						$countYear += count($i);
						$archive[$type][$year][$key]["total"] = count($i);
						$archive[$type][$year][$key]["monthName"] = __($monthName[$key],true);
					}
					$archive[$type][$year]["total"] = $countYear;
				}
			}
		}

		return $archive;
	}
	
	
	/**
	 * load all tag
	 *
	 * @param string $tplVar
	 */
	public function loadTags($tplVar=null, $cloud=true) {
		$tplVar = (empty($tplVar))? "listTags" : $tplVar;
		$category = ClassRegistry::init("Category");
		$this->set($tplVar, $category->getTags(false, $this->status, $cloud));
	}
	
	/**
	 * return objects for a specific tag
	 *
	 * @param string $tag tag label
	 * @params array $options search options
	 * 				"section" => name or id section
	 * 				"filter" => particular filter
	 * 				"order", "dir", "dim", "page" used like pagination parameters
	 * @return array
	 */
	protected function loadObjectsByTag($tag, $options=array()) {
		
		$section_id = null;
		if (!empty($options["section"])) {
			$section_id = (is_numeric($options["section"]))? $options["section"] : $this->BEObject->getIdFromNickname($options["section"]);
			$this->checkParentStatus($section_id);
		}
		
		// remove '+' from $tag, if coming from url
		$tag = strtolower(str_replace("+", " ", $tag));
		
		$tagDetail = ClassRegistry::init("Category")->find("first", array(
					"conditions" => array("name" => $tag, "object_type_id IS NULL", "status" => $this->status)
				)
			);
		
		if (empty($tagDetail))
			throw new BeditaException(__("No tag founded", true));
		
		$options = array_merge($this->tagOptions, $options, $this->getPassedArgs());
		$filter = (!empty($options["filter"]))? $options["filter"] : false;
		$filter["tag"] = $tag;
		$order = "";
		if (!empty($options["order"])) {
			$order = $options["order"];
		} elseif (!empty($section_id)) {
			$order = "priority";
		}
		$dir = (isset($options["dir"]))? $options["dir"] : 1;
		$page = (!empty($options["page"]))? $options["page"] : 1;
		$dim = (!empty($options["dim"]))? $options["dim"] : 100000;
		
		// add rules for start and end pubblication date
		if ($this->checkPubDate == true) {
			if (empty($filter["Content.start"]))
				$filter["Content.start"] = "<= '" . date("Y-m-d") . "' OR `Content`.start IS NULL";
			if (empty($filter["Content.end"]))
				$filter["Content.end"] = ">= '" . date("Y-m-d") . "' OR `Content`.end IS NULL";
		}
		
		$contents = $this->BeTree->getChildren($section_id, $this->status, $filter, $order, $dir, $page, $dim);
		
		$result = $tagDetail;

		foreach ($contents["items"] as $c) {
			$object = $this->loadObj($c["id"]);
			if ($this->sectionOptions["itemsByType"])
				$result[$object['object_type']][] = $object;
			else
				$result["items"][] = $object;
		}
		
		return array_merge($result, array("toolbar" => $contents["toolbar"]));
	}
	
	/**
	 * load annotation referenced to some object
	 * 
	 * @param string $annotationType, object type of the annotation i.e. "comment"
	 * @param $objectName, reference object nickname or id 
	 * @param array $options, specific options (pagination, filter) that override annotationOptions attribute
	 * @return array of annotations
	 */
	protected function loadAnnotations($annotationType, $objectName, $options=array()) {
		
		if (empty($annotationType) || empty($objectName))
			throw new BeditaException(__("Annotation type or object_id missing", true));
		
		$object_id = (is_numeric($objectName))? $objectName : $this->BEObject->getIdFromNickname($objectName);
		
		$options = array_merge($this->annotationOptions[$annotationType], $options, $this->getPassedArgs());
		$filter = (!empty($options["filter"]))? $options["filter"] : array();
		$filter["object_type_id"] = Configure::read("objectTypes." . $annotationType . ".id");
		$filter[Configure::read("objectTypes." . $annotationType . ".model") . ".object_id"] = $object_id;
		$order = (!empty($options["order"]))? $options["order"] : "BEObject.created";
		$dir = (isset($options["dir"]))? $options["dir"] : 1;
		$page = (!empty($options["page"]))? $options["page"] : 1;
		$dim = (!empty($options["dim"]))? $options["dim"] : 100000;
		
		$annotations = $this->BeTree->getChildren(null, $this->status, $filter, $order, $dir, $page, $dim);
		$result = array();
		foreach ($annotations["items"] as $a) {
			$object = $this->loadObj($a["id"]);
			$result[Configure::read("objectTypes." . $annotationType . ".model")][] = $object;
		}
		return array_merge($result, array("toolbar" => $annotations["toolbar"]));
	}
	
	public function download($name) {
		if(empty($name))
			throw new BeditaException(__("Content not found", true));
		
		$id = is_numeric($name) ? $name : $this->BEObject->getIdFromNickname($name);
		$object_type_id = $this->BEObject->findObjectTypeId($id);
		// verify type
		$conf = Configure::getInstance() ;
		$types = array($conf->objectTypes['image']['id'], $conf->objectTypes['video']['id'],
			$conf->objectTypes['befile']['id'], $conf->objectTypes['audio']['id']);
		if(($object_type_id === false) || !in_array($object_type_id, $types))
			throw new BeditaException(__("Content not found", true));

		$obj = $this->loadObj($id);
		// check 'download' relation
		// TODO: check relatedObject status????
		$objRel = ClassRegistry::init("ObjectRelation");
		$relatedObjectId = $objRel->find('first', 
				array('conditions' => array("ObjectRelation.id" => $id, 
						"ObjectRelation.switch" => "download"), 'fields' => array('object_id')));
		if($relatedObjectId === false) {
			throw new BeditaException(__("Content not found", true));
		}

		// media with provider or file on filesystem? TODO: use DS?? 
		if(!empty($obj['provider']) || $obj['path'][0] !== "/") {
			$this->redirect($obj['path']);
		}

		// TODO: for some extensions or mime-types redirect to media URL
		if(isset($conf->redirectMimeTypesDownload) && 
			in_array($obj['mime_type'], $conf->redirectMimeTypesDownload)) {
			$this->redirect($conf->mediaUrl.$obj['path']);
		}
			
		$path = ($conf->mediaRoot).$obj['path'];
		$f = new File($path);
		$info = $f->info();
		if(isset($conf->redirectExtensionsDownload) && 
				in_array($info['extension'], $conf->redirectExtensionsDownload)) {
			$this->redirect($conf->mediaUrl.$obj['path']);
		}	

		Configure::write('debug', 0);
		// use readfile
		// TODO: optimizations! use X-Sendfile ? 
		header('Content-Description: File Transfer');
		header('Content-Type: '.$obj['mime_type']);
		header('Content-Disposition: attachment; filename='.$obj['name']);
		header('Content-Transfer-Encoding: binary');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header('Content-Length: ' . $obj['size']);
		ob_clean();
   		flush();
		readfile($path);
		exit();
	}
	
	/**
	 * show image for captch
	 *
	 */
	public function captchaImage() {	
		if(!isset($this->Captcha)) {
			App::import('Component', 'Captcha');
			$this->Captcha = new CaptchaComponent();
			$this->Captcha->startup($this);
		}
		$this->autoRender = false;
		$this->Captcha->image($this->captchaOptions);
	}
	
	public function saveComment() {
		if (!empty($this->data)) {
			if(!isset($this->Comment)) {
				$this->Comment = $this->loadModelByType("Comment");
			}
			$this->data["title"] = substr($this->data["description"],0,30) . "...";
			// for comment status check contents.comments 
			$beObject = ClassRegistry::init("BEObject");
			$commentsFlag = $beObject->field("comments", array("id" => $this->data['object_id']));
			if($commentsFlag == 'moderated') {
				 $this->data["status"] = "draft";
				 $userMsgOK = "Your message has been sent and it's waiting approval.";
			} else if ($commentsFlag == 'on'){
				 $this->data["status"] = 'on';
				 $userMsgOK = "Your message has been saved.";
			} else {
				 throw new BeditaException(__("Post comment disabled", true));
			}

			try {
				// check IP
				$bannedIP = ClassRegistry::init("BannedIp");
        		if($bannedIP->isBanned($_SERVER['REMOTE_ADDR'])) {
					throw new BeditaException(__("Error saving comment", true));
        		}
				
				// check captcha				
				if(!isset($this->Captcha)) {
					App::import('Component', 'Captcha');
					$this->Captcha = new CaptchaComponent();
					$this->Captcha->startup($this);
				}
				$this->Captcha->checkCaptcha();
				
				$this->Transaction->begin();
				if (!$this->Comment->save($this->data)) {
					throw new BeditaException(__("Error saving comment", true), $this->Comment->validationErrors);
				}
				$this->Transaction->commit();
				$this->userInfoMessage(__($userMsgOK, true));
			} catch (BeditaException $ex) {
				$this->Transaction->rollback();
				$this->log($ex->errorTrace());
				$this->userErrorMessage($ex->getMessage());
			}
	
		}
		
		if (!isset($this->RequestHandler)) {
			App::import("Component", "RequestHandler");
			$this->RequestHandler = new RequestHandlerComponent();
			$this->RequestHandler->initialize($this);
			$this->RequestHandler->startup($this);
		}
		// if it's ajax call no redirect by referer
		if($this->RequestHandler->isAjax()) { 
			$this->layout = "ajax";
			if (!empty($this->params["form"]["render"])) { 
				$this->render(null, null, $this->params["form"]["render"] .".tpl");
			}
		} else {
			$urlToRedirect = ($commentsFlag == 'on')? $this->referer() . "/#comment-".$this->Comment->id : $this->referer(); 
			$this->redirect($urlToRedirect);
		}

	}
	
	/**
	 * check parents status of $section_id
	 *	
	 * if one or more parents haven't status IN $this->status array throw a BeditaException
	 * 
	 * @param int $section_id
	 */
	private function checkParentStatus($section_id) {
		$parent_path = $this->Tree->field("parent_path", array("id" => $section_id));
		$parent_array = explode("/", trim($parent_path,"/"));
		if (!empty($parent_array[0])) {
			$countParent = count($parent_array);
			$countParentStatus = $this->BEObject->find("count", array(
					"conditions" => array(
						"status" => $this->status,
						"id" => $parent_array
					),
					"contain" => array()
				)
			);
			
			if ($countParent != $countParentStatus)
				throw new BeditaException(__("Content not found", true));
		}
	}
	
	/**
	 * get passed args by name and return
	 *
	 */
	private function getPassedArgs() {
		$args = array();
		if (!empty($this->passedArgs)) {
			foreach ($this->passedArgs as $key => $val) {
				if (!is_numeric($key)) {
					$args[$key] = $val;
				}
			}
		}
		return $args;
	}
	
	protected function showDraft() {
		$this->status[] = "draft";
	}
	
	public function getStatus() {
		return $this->status;
	}
}


// Exception class
class BeditaPublicationException extends BeditaException {
	
	public $status;
	
	public function __construct($status) {
   		$this->status = $status;
    }
	
}
?>
<?php

/**
 * @defgroup CBPPlatform
 */

/**
 * @file CAPRI.php
 *
 * Distributed under the GNU GPL v2
 *
 * @class CAPRI
 * @ingroup CBPPlatform
 *
 * @brief Operations for CBPPlatform Fedora digital object and REST (Fedora API-M) functionality.
 */

	include('Pest.inc.php');

	class CAPRI {
		
		/**
		 * Constructor.
		 * Set properties
		 */
		function __construct($resultFormat="xml"){
			$this->username = Config::getVar('general', 'repository_username');
			$this->password = Config::getVar('general', 'repository_password');
			$this->server = Config::getVar('general', 'repository_short_url');
			$this->hydraServer = Config::getVar('general', 'hydra_short_url');
			
			$this->rest = new Pest("http://$this->username:$this->password@$this->server");
			$this->resultFormat = $resultFormat;
			$this->primaryFormat = "application/pdf";
		}
		
		/**
		 * Set digital object namespace
		 * @param $ns str namespace
		 */
		function setNamespace($ns=null){
			if ($ns == null) $ns = Config::getVar('general', 'compiled_namespace');
			$this->obj->ns = $ns;
		}
		
		/**
		 * Set digital object pid
		 * @param $pid str pid
		 */
		function setPID($pid=""){
			$this->obj->PID = $pid;	
		}
		
		/**
		 * Set digital object label
		 * @param $label str label
		 */
		function setLabel($label=""){
			$this->obj->label = $label;	
		}
		
		/**
		 * Set digital object datastream
		 * @param $ds array Datastream to set
		 */
		function setDatastream(Array $ds){
			/**
			 * Uses 2D Array
			 *
			 * dsID
			 *   -Multipart File
			 *   -Label
			 * dsID2
			 *   - Multipart File 2
			 *   - Label 2
			 */
			$this->obj->ds[] = $ds;
		}
		
		/**
		 * Set primary format
		 * @param $format str primary format to set
		 */
		function setPrimaryFormat($format) {
			$this->primaryFormat = $format;
		}
		
		/**
		 * A dump of the object's properties
		 */
		function __toString(){
			var_dump($this);
		}
	
		/**
		 * Create Object
		 * @return string
		 */
		function createObject(){
			if (isset($this->obj->PID)) {	//if a PID is specified, use it
				$params = array(
					"label" => $this->obj->label
				);
				$this->rest->post("/" . $this->obj->ns . ":" . $this->obj->PID . "?" . http_build_query($params));
			} else { //otherwise, autogenerate a PID within the namespace
				$params = array(
					"namespace" => $this->obj->ns,
					"label" => $this->obj->label
				);
				$this->obj->PID = $response = $this->rest->post("/new?" . http_build_query($params));
			}
			return $this->obj->PID;
		}
		
		/**
		 * Create Object Datastreams
		 * @return bool
		 */
		function createObjectDatastreams($controlGroup="M", $dsState="A"){
			//expects first content-bearing DS to be PDF content...
			foreach($this->obj->ds as $ds) {
				$id = key($ds);
				if (isset($ds[$id]['id'])) {
					$dsId = $ds[$id]['id'];
				} else {
					$dsId = "content";
					if (isset($this->obj->pointer)) {
						$this->obj->pointer++;
						$dsId .= sprintf("%02d", $this->obj->pointer);
					}
					if (!isset($this->obj->pointer)) {
						$this->obj->pointer = 1;
					}
				}
				isset($ds[$id]['mimetype']) ? $mimetype = $ds[$id]['mimetype'] : $mimetype = mime_content_type($ds[$id]['file']);
				if ($mimetype == $this->primaryFormat) {
					$this->obj->hydra['filesize'] = filesize($ds[$id]['file']);
					$this->obj->hydra['mimetype'] = $mimetype;
					$this->obj->hydra['dsId'] = $dsId;
				}
				$properties = array(
					'size' => filesize($ds[$id]['file']),
					'mimetype' => $mimetype,
					'dsId' => $dsId
				);
				if (isset($ds[$id]['filetype'])) $properties['filetype'] = $ds[$id]['filetype'];
				$this->obj->hydra['obj'][] = $properties;
				$file = array(
						"file" => "@" . $ds[$id]['file'] . ";type=" . $mimetype,
					);
				$params = array(
						"dsLabel" => $ds[$id]['label'],
						"controlGroup" => $controlGroup,
						"dsState" => $dsState,
					);
					//'checksumType' => 'MD5'
				$response = $this->rest->post("/" . $this->obj->ns . ":" . $this->obj->PID . "/datastreams/$dsId?" . http_build_query($params), $file);
			}
			return $response;
		}
		
		/**
		 * Read Object Properties
		 * @return string
		 */
		function readObjectProperties(){
			return $response = $this->rest->get("?terms=" . $this->obj->ns . ":" . $this->obj->PID . "&resultFormat=" . $this->resultFormat . "&pid=true&subject=true&label=true");
		}
		
		/**
		 * Read Object Datastreams
		 * @return string
		 */
		function readObjectDatastreams(){
			return $response = $this->rest->get("/$this->obj->ns:$this->obj->PID/datastreams?format=$this->resultFormat");
		}
		
		/**
		 * Update Object Properties
		 * @return string
		 */
		function updateObjectProperties(){
			$params = array(
						"label" => $this->obj->label
					);
			return $response = $this->rest->put("/$this->obj->ns:$this->obj->PID?" . http_build_query($params));
		}
		
		/**
		 * Update Object Datastreams
		 * @return array
		 */
		 function updateObjectDatastreams($controlGroup="M", $dsState="A"){
		 	foreach($this->obj->ds as $ds) {
				$dsId = key($ds);				
				$file = array(
							"file" => "@" . $filename = $ds[$dsId]['file'] . ";type=" . mime_content_type($filename),
						);
				$params = array(
						"dsLabel" => $label = $ds[$dsId]['label'],
						"controlGroup" => $controlGroup,
						"dsState" => $dsState
					);
				$response = $this->rest->put("/$this->obj->ns:$this->obj->PID/datastreams/$dsId?" . http_build_query($params), $file);
			}
			return $response;
		 }
		
		/**
		 * Delete Object
		 * @return string
		 */
		function deleteObject(){
			return $response = $this->rest->delete("/$this->obj->ns:$this->obj->PID");
		}
		
		/**
		 * Delete Datastream
		 * @return array
		 */
		 function deleteDatastreams(){
		 	foreach($this->obj->ds as $ds) {
		 		$dsId = key($ds);
		 		$response[] = $this->rest->delete("/" . $this->obj->ns . ":" . $this->obj->PID . "/datastreams/$dsId");
		 	}
		 	return $response;
		 }
		 
	}
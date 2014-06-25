<?php

	class Boxalino_Cem_Helper_Data extends Mage_Core_Helper_Data{
		protected $client = null;

		protected $seoBase = '/';

		protected $seoSuffix = '';

		protected $pagesEnabled = null;

		protected $pagesCache = array();

		protected $lastUri = null;

		public function __construct(){
			spl_autoload_register(array('Boxalino_Cem_Helper_Data', '__loadClass'), TRUE, TRUE);
		}


		public function loadPage($uri, $parameters = array()){
			if(!$this->isEnabled()){
				return new CEM_ApiPage();
			}
			if(strlen($uri) > 0){
				$uri .= $this->seoSuffix;
				$this->lastUri = $uri;
			}else{
				$uri = $this->lastUri;
			}
			if(!array_key_exists($uri, $this->pagesCache)){
				$parameters['baseUri'] = $this->_getUrl('boxalinocem/search');
				if(strlen($parameters['baseUri']) > 0 && strrpos($parameters['baseUri'], '/') == strlen($parameters['baseUri']) - 1){
					$parameters['baseUri'] = substr($parameters['baseUri'], 0, strlen($parameters['baseUri']) - 1);
				}
				$parameters['baseUri'] .= $this->seoBase;

				try{
					$this->pagesCache[$uri] = $this->getApiClient()->loadPage($uri, $parameters);
				}catch(Exception $e){
					if($this->isDebugEnabled()){
						echo($e);
						exit;
					}
				}
			}
			return $this->pagesCache[$uri];
		}

		public function isPageLoaded($uri = ''){
			if(strlen($uri) == 0){
				$uri = $this->lastUri;
			}
			if(isset($this->pagesCache[$uri])){
				return $this->pagesCache[$uri]->getStatus();
			}
			return false;
		}

		public function hasPageBlock($block, $uri = ''){
			if(strlen($uri) == 0){
				$uri = $this->lastUri;
			}
			if(!array_key_exists($uri, $this->pagesCache)){
				return ($this->isEnabled() ? ($uri . ':' . $block) : '');
			}
			return $this->pagesCache[$uri]->hasBlock($block);
		}

		public function getPageBlock($block, $uri = ''){
			if(strlen($uri) == 0){
				$uri = $this->lastUri;
			}
			if(!array_key_exists($uri, $this->pagesCache)){
				return ($this->isEnabled() ? ($uri . ':' . $block) : '');
			}
			return $this->pagesCache[$uri]->getBlock($block);
		}

		public function getPageQuery($uri = ''){
			if(strlen($uri) == 0){
				$uri = $this->lastUri;
			}
			if(!array_key_exists($uri, $this->pagesCache)){
				return '';
			}
			return $this->pagesCache[$uri]->getQuery();
		}

		public function suggest(){
			if($this->isEnabled()){
				try{
					$this->getApiClient()->proxy('/ajax/suggest');
				}catch(Exception $e){
					if($this->isDebugEnabled()){
						echo($e);
						exit;
					}
				}
				return TRUE;
			}
			return FALSE;
		}

		public function getSuggestUrl(){
			$baseUrl = $this->getApiClient()->getRemoteUrl();
			if($baseUrl){
				return ($baseUrl . '/ajax/suggest');
			}
			return $this->_getUrl('boxalinocem/search/suggest');
		}

		public function getSuggestParameters(){
			return "{}";
		}


		public static function __loadClass($name){
			if(strpos($name, 'CEM_') === 0){
				include_once(Mage::getModuleDir('', 'Boxalino_CemSearch') . '/Lib/' . $name . '.class.php');
			}
		}

	}

<?php

class Boxalino_Export_Model_Product_Image extends Mage_Catalog_Model_Product_Image {
	public function setSourceFile($file, $placeholder, $design) {
		$path = Mage::getSingleton('catalog/product_media_config')->getBaseMediaPath();
		if ((!$file) || (!file_exists($path.$file))) {
			$file = '/placeholder/'.$placeholder;
			$paths = array(
				$path,
				$design->getSkinBaseDir(),
				$design->getSkinBaseDir(array('_theme' => 'default')),
				$design->getSkinBaseDir(array('_theme' => 'default', '_package' => 'base')),
			);
			foreach ($paths as $other) {
				if (file_exists($other.$file)) {
					$path = $other;
					break;
				}
			}
			$this->_isBaseFilePlaceholder = true;
		}
		if (!file_exists($path.$file)) {
			throw new Exception(Mage::helper('catalog')->__('Image file not found: %s', $path.$file));
		}
		$this->_baseFile = $path.$file;

		// build new filename (most important params)
		$path = array(
			Mage::getSingleton('catalog/product_media_config')->getBaseMediaPath(),
			'cache',
			Mage::app()->getStore()->getId(),
			$this->getDestinationSubdir()
		);
		if((!empty($this->_width)) || (!empty($this->_height)))
			$path[] = "{$this->_width}x{$this->_height}";

		// add misk params as a hash
		$miscParams = array(
				($this->_keepAspectRatio  ? '' : 'non') . 'proportional',
				($this->_keepFrame        ? '' : 'no')  . 'frame',
				($this->_keepTransparency ? '' : 'no')  . 'transparency',
				($this->_constrainOnly ? 'do' : 'not')  . 'constrainonly',
				$this->_rgbToString($this->_backgroundColor),
				'angle' . $this->_angle,
				'quality' . $this->_quality
		);

		// if has watermark add watermark params to hash
		if ($this->getWatermarkFile()) {
			$miscParams[] = $this->getWatermarkFile();
			$miscParams[] = $this->getWatermarkImageOpacity();
			$miscParams[] = $this->getWatermarkPosition();
			$miscParams[] = $this->getWatermarkWidth();
			$miscParams[] = $this->getWatermarkHeigth();
		}

		$path[] = md5(implode('_', $miscParams));

		// append prepared filename
		$this->_newFile = implode('/', $path) . $file; // the $file contains heading slash

		return $this;
	}

	protected function _rgbToString($rgbArray)
	{
		$result = array();
		foreach ($rgbArray as $value) {
			if (null === $value) {
				$result[] = 'null';
			}
			else {
				$result[] = sprintf('%02s', dechex($value));
			}
		}
		return implode($result);
	}
}

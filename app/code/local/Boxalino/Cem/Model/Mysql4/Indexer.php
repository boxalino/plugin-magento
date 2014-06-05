<?php

/**
 * Abstract Boxalino Cem Indexer
 *
 * @author nitro@boxalino.com
 */
abstract class Boxalino_Cem_Model_Mysql4_Indexer extends Mage_Core_Model_Mysql4_Abstract {
	private $_additionalAttributes = array();

	private $_searchableAttributes = null;

	private $_allAttributes = null;

	private $_productTypes = array();

	private $_storeDateFormats = array();


	protected abstract function _beginSync($categories, $tags, $fields);

	protected abstract function _beginStoreSync($store);

	protected abstract function _processStoreSync($store, $categories, $tags, $fields, $productCategories, $productTags, $productAttributes, $productCombinations, $productSubProducts);

	protected abstract function _endStoreSync($store);

	protected abstract function _endSync();

	protected abstract function _clearSync();


	protected function getStores() {
		$stores = array();
		foreach (Mage::app()->getStores(false) as $store) {
			if (!$store->getIsActive()) {
				continue;
			}
			$stores[] = $store;
		}
		return $stores;
	}

	protected function getAttributeType($attribute) {
		$type = 'text';
		if (!$attribute->usesSource()) {
			if ($attribute->is_global || $attribute->getAttributeCode() == 'entity_id') {
				$type = 'string';
			}
			switch ($attribute->getFrontend()->getInputType()) {
			case 'date':
				$type = 'date';
				break;

			case 'price':
				$type = 'number';
				break;
			}
		}
		return $type;
	}

	protected function normalizeText($value) {
		return strtr(
			$value,
			array(
				"\x00" => '',
				"\x01" => '',
				"\x02" => '',
				"\x03" => '',
				"\x04" => '',
				"\x05" => '',
				"\x06" => '',
				"\x07" => '',
				"\x08" => '',
				"\x0b" => '',
				"\x0c" => '',
				"\x0d" => '',
				"\x0e" => '',
				"\x0f" => '',
				"\x10" => '',
				"\x11" => '',
				"\x12" => '',
				"\x13" => '',
				"\x14" => '',
				"\x15" => '',
				"\x16" => '',
				"\x17" => '',
				"\x18" => '',
				"\x19" => '',
				"\x1a" => '',
				"\x1b" => '',
				"\x1c" => '',
				"\x1d" => '',
				"\x1e" => '',
				"\x1f" => ''
			)
		);
	}


	public function reindexAll() {
		// initialize class loader
		Mage::helper('boxalinocem');

		// export each store
		$lastException = null;
		try {
			// find additional attributes
			$this->_additionalAttributes = array();
			foreach (explode(',', Mage::getStoreConfig('boxalinocem/synchronization/additional_attributes')) as $attributeCode) {
				$attributeCode = trim($attributeCode);
				if (strlen($attributeCode) == 0 || in_array($attributeCode, $this->_additionalAttributes)) {
					continue;
				}
				$this->_additionalAttributes[] = $attributeCode;
			}

			// find categories
			if (Mage::getStoreConfig('boxalinocem/synchronization/export_categories') == 1) {
				$categories = $this->_getCategories();
			} else {
				$categories = array();
			}

			// find tags
			if (Mage::getStoreConfig('boxalinocem/synchronization/export_tags') == 1) {
				$tags = $this->_getTags();
			} else {
				$tags = array();
			}

			// find fields
			$fields = array();
			$fields['entity_id'] = $this->_getSearchableAttribute('entity_id');
			$fields['_url'] = $this->_getSearchableAttribute('url_key');
			$fields['_url_wishlist'] = $this->_getSearchableAttribute('url_key');
			$fields['_url_comparator'] = $this->_getSearchableAttribute('url_key');
			$fields['_url_basket'] = $this->_getSearchableAttribute('url_key');
			$fields['_image'] = $this->_getSearchableAttribute('image');
			foreach (explode(',', Mage::getStoreConfig('boxalinocem/synchronization/image_sizes')) as $entry) {
				$parts = explode(':', trim($entry));
				if (sizeof($parts) == 2) {
					$id = trim($parts[0]);
					if (strlen($id) > 0) {
						$fields['_image_'.$id] = $this->_getSearchableAttribute('image');
					}
				}
			}
			$fields['_thumbnail'] = $this->_getSearchableAttribute('small_image');
			foreach (explode(',', Mage::getStoreConfig('boxalinocem/synchronization/small_image_sizes')) as $entry) {
				$parts = explode(':', trim($entry));
				if (sizeof($parts) == 2) {
					$id = trim($parts[0]);
					if (strlen($id) > 0) {
						$fields['_thumbnail_'.$id] = $this->_getSearchableAttribute('small_image');
					}
				}
			}
			foreach ($this->_getSearchableAttributes() as $attribute) {
				switch ($attribute->getAttributeCode()) {
				case 'visibility':
				case 'status':
				case 'name':
				case 'short_description':
				case 'description':
				case 'in_depth':
				case 'meta_title':
				case 'meta_description':
				case 'meta_keyword':
				case 'price':
				case 'image':
				case 'small_image':
				case 'url_path':
				case 'url_key':
					if (!in_array($attribute->getAttributeCode(), $this->_additionalAttributes)) {
						// ignore system attributes
						break;
					}

				default:
					$fields[$attribute->getAttributeCode()] = $attribute;
					break;
				}
			}

			// notify: begin sync
			if (!$this->_beginSync($categories, $tags, $fields)) {
				Mage::throwException(Mage::helper('boxalinocem')->__('Synchronization failed (code %d)!', 1));
			}

			// sync stores
			foreach ($this->getStores() as $store) {
				$this->_reindexStore($store, $categories, $tags, $fields);
			}

			// notify: end sync
			if (!$this->_endSync()) {
				Mage::throwException(Mage::helper('boxalinocem')->__('Synchronization failed (code %d)!', 2));
			}
		} catch (Exception $e) {
			Mage::logException($e);

			$lastException = $e;
		}

		// notify: clear sync
		try {
			$this->_clearSync();
		} catch (Exception $e) {
			Mage::logException($e);
		}

		// throw exception if any error occured
		if ($lastException != null) {
			Mage::throwException(Mage::helper('boxalinocem')->__('Synchronization failed (%s)!', $lastException->getMessage()));
		}
	}


	private function _reindexStore($store, $categories, $tags, $fields) {
		// find static fields
		$staticFields = array();
		$staticFields[] = 'entity_id';
		$staticFields[] = 'type_id';
		$staticFields[] = 'created_at';
		$staticFields[] = 'updated_at';
		foreach ($this->_getSearchableAttributes('static') as $attribute) {
			if (!in_array($attribute->getAttributeCode(), $staticFields)) {
				$staticFields[] = $attribute->getAttributeCode();
			}
		}

		// find dynamic fields
		$dynamicFields = array(
			'datetime'	=> array_keys($this->_getSearchableAttributes('datetime')),
			'decimal'	=> array_keys($this->_getSearchableAttributes('decimal')),
			'int'		=> array_keys($this->_getSearchableAttributes('int')),
			'text'		=> array_keys($this->_getSearchableAttributes('text')),
			'varchar'	=> array_keys($this->_getSearchableAttributes('varchar'))
		);

		// find visibility filter
		$visibility = $this->_getSearchableAttribute('visibility');
		$visibilityValues = Mage::getSingleton('catalog/product_visibility')->getVisibleInSearchIds();

		// find status filter
		$status = $this->_getSearchableAttribute('status');
		$statusValues = Mage::getSingleton('catalog/product_status')->getVisibleStatusIds();

		// notify: begin store sync
		if (!$this->_beginStoreSync($store)) {
			Mage::throwException(Mage::helper('boxalinocem')->__('Synchronization failed (code %d)!', 10));
		}

		// find image configurations
		$imageBasePath = Mage::getSingleton('catalog/product_media_config')->getBaseMediaPath();
		$images = array(
			'image' => array(
				'list' => array(),
				'placeholder' => $store->getConfig("catalog/placeholder/image_placeholder"),
				'watermark' => array(
					'file' => $store->getConfig("design/watermark/image_image"),
					'position' => $store->getConfig("design/watermark/image_position"),
					'size' => $this->_parseImageSize($store->getConfig("design/watermark/image_size")),
					'opacity' => $store->getConfig("design/watermark/image_imageOpacity")
				)
			),
			'small_image' => array(
				'list' => array(),
				'placeholder' => $store->getConfig("catalog/placeholder/small_image_placeholder"),
				'watermark' => array(
					'file' => $store->getConfig("design/watermark/small_image_image"),
					'position' => $store->getConfig("design/watermark/small_image_position"),
					'size' => $this->_parseImageSize($store->getConfig("design/watermark/small_image_size")),
					'opacity' => $store->getConfig("design/watermark/small_image_imageOpacity")
				)
			)
		);
		foreach (explode(',', Mage::getStoreConfig('boxalinocem/synchronization/image_sizes')) as $entry) {
			$parts = explode(':', trim($entry));
			if (sizeof($parts) == 2) {
				$id = trim($parts[0]);
				$size = $this->_parseImageSize($parts[1]);
				if (strlen($id) > 0 && $size) {
					$images['image']['list'][$id] = $size;
				}
			}
		}
		foreach (explode(',', Mage::getStoreConfig('boxalinocem/synchronization/small_image_sizes')) as $entry) {
			$parts = explode(':', trim($entry));
			if (sizeof($parts) == 2) {
				$id = trim($parts[0]);
				$size = $this->_parseImageSize($parts[1]);
				if (strlen($id) > 0 && $size) {
					$images['small_image']['list'][$id] = $size;
				}
			}
		}

		// find design package
		$design = Mage::getModel('core/design_package');
		$design->setStore($store);

		// export products
		$maximumProducts = $store->getConfig('boxalinocem/synchronization/maximum_population');
		$totalProducts = 0;
		$nextProductId = 0;
		while (($maximumProducts <= 0 || $totalProducts < $maximumProducts) && $products = $this->_getProducts($store, $staticFields, $nextProductId)) {
			// load sub-products
			$productIds = array();
			$productsRelations = array();
			$productSubProducts = array();
			foreach ($products as $product) {
				$nextProductId = $product['entity_id'];
				$productIds[$product['entity_id']] = $product['entity_id'];

				$productChildren = $this->_getProductChildren($product['entity_id'], $product['type_id']);
				if ($productChildren) {
					$productSubProducts[$product['entity_id']] = $this->_getSubProductValues($store, $product['entity_id'], $productChildren, $dynamicFields, $categories, $tags);

					foreach ($productChildren as $productChild) {
						$productIds[$productChild] = $productChild;
					}
					$productsRelations[$product['entity_id']] = $productChildren;
				}
			}

			// load urls
			$productsUrls = $this->_getProductUrls($store, $productIds);

			// load categories
			if (sizeof($categories) > 0) {
				$productsCategories = $this->_getProductCategories($store, $productIds);
			} else {
				$productsCategories = array();
			}

			// load tags
			if (sizeof($tags) > 0) {
				$productsTags = $this->_getProductTags($store, $productIds);
			} else {
				$productsTags = array();
			}

			// load stocks
			$productsStock = $this->_getProductStock($store, $productIds);

			// load sales
			$productsSales = $this->_getProductSales($store, $productIds);

			// load views
			$productsViews = $this->_getProductViews($store, $productIds);

			// load dynamic attributes
			$productsAttributes = $this->_getProductAttributes($store, $productIds, $dynamicFields);

			// export complete product
			foreach ($products as $product) {
				// check status/visibility
				if (!isset($productsAttributes[$product['entity_id']])) {
					continue;
				}
				if (!isset($productsAttributes[$product['entity_id']][$status->getId()]) || !in_array($productsAttributes[$product['entity_id']][$status->getId()], $statusValues)) {
					continue;
				}
				if (!isset($productsAttributes[$product['entity_id']][$visibility->getId()]) || !in_array($productsAttributes[$product['entity_id']][$visibility->getId()], $visibilityValues)) {
					continue;
				}

				// wrap product object
				$productItem = Mage::getModel('boxalinocem/product');
				$product['store_id'] = $store->getId();
				$this->_mergeProductValues($store, $productsAttributes[$product['entity_id']], $product);
				if (isset($productsUrls[$product['entity_id']])) {
					$product['request_path'] = $productsUrls[$product['entity_id']];
				}
				$productItem->fromArray($product);

				// fetch target url
				$product['_url'] = $productItem->getProductUrl();
				$product['_url_wishlist'] = $productItem->getAddToWishlistUrl();
				$product['_url_comparator'] = $productItem->getAddToCompareUrl();
				$product['_url_basket'] = $productItem->getAddToCartUrl();

				// fetch image urls
				try {
					$productItemImage = DIRECTORY_SEPARATOR.ltrim($productItem->getData('image'), DIRECTORY_SEPARATOR);
					$product['_image'] = is_file($imageBasePath.$productItemImage) ? 'true' : 'false';
					foreach ($images['image']['list'] as $key => $imageSize) {
						/** @var Boxalino_Cem_Model_Product_Image $productImage */
						$productImage = Mage::getModel('boxalinocem/product_image')
							->setDestinationSubdir('image')
							->setWidth($imageSize['width'])
							->setHeight($imageSize['height'])
							->setWatermarkFile($images['image']['watermark']['file'])
							->setWatermarkPosition($images['image']['watermark']['position'])
							->setWatermarkSize($images['image']['watermark']['size'])
							->setWatermarkImageOpacity($images['image']['watermark']['opacity']);

						if ($imageSize['rotate'] != 0) {
							$productImage->setAngle($imageSize['rotate']);
						}

						$productImage->setSourceFile($productItemImage, $images['image']['placeholder'], $design);
						if (!$productImage->isCached()) {
							if ($imageSize['rotate'] != 0) {
								$productImage->rotate($imageSize['rotate']);
							}
							$productImage
								->resize()
								->setWatermark($images['image']['watermark']['file'])
								->saveFile();
						}
						$product['_image_'.$key] = $productImage->getUrl();
					}
				} catch (Exception $e) {
					$product['_image'] = 'false';
				}

				// fetch thumbnail urls
				try {
					$productItemSmallImage = DIRECTORY_SEPARATOR.ltrim($productItem->getData('small_image'), DIRECTORY_SEPARATOR);
					$product['_thumbnail'] = is_file($imageBasePath.$productItemSmallImage) ? 'true' : 'false';
					foreach ($images['small_image']['list'] as $key => $imageSize) {
						/** @var Boxalino_Cem_Model_Product_Image $productImage */
						$productImage = Mage::getModel('boxalinocem/product_image')
							->setDestinationSubdir('small_image')
							->setWidth($imageSize['width'])
							->setHeight($imageSize['height'])
							->setWatermarkFile($images['small_image']['watermark']['file'])
							->setWatermarkPosition($images['small_image']['watermark']['position'])
							->setWatermarkSize($images['small_image']['watermark']['size'])
							->setWatermarkImageOpacity($images['small_image']['watermark']['opacity']);

						if ($imageSize['rotate'] != 0) {
							$productImage->setAngle($imageSize['rotate']);
						}

						$productImage->setSourceFile($productItemSmallImage, $images['small_image']['placeholder'], $design);
						if (!$productImage->isCached()) {
							if ($imageSize['rotate'] != 0) {
								$productImage->rotate($imageSize['rotate']);
							}
							$productImage
								->resize()
								->setWatermark($images['small_image']['watermark']['file'])
								->saveFile();
						}
						$product['_thumbnail_'.$key] = $productImage->getUrl();
					}
				} catch (Exception $e) {
					$product['_thumbnail'] = 'false';
				}

				// fetch access stats
				$product['_stock'] = isset($productsStock[$product['entity_id']]) ? ($productsStock[$product['entity_id']]['status'] ? $productsStock[$product['entity_id']]['quantity'] : 0) : 0;
				$product['_views'] = isset($productsViews[$product['entity_id']]) ? $productsViews[$product['entity_id']] : 0;
				$product['_sales'] = isset($productsSales[$product['entity_id']]) ? $productsSales[$product['entity_id']] : 0;

				// fetch categories
				if (isset($productsCategories[$product['entity_id']])) {
					$productCategories = $productsCategories[$product['entity_id']];
				} else {
					$productCategories = array();
				}

				// fetch tags
				if (isset($productsTags[$product['entity_id']])) {
					$productTags = $productsTags[$product['entity_id']];
				} else {
					$productTags = array();
				}

				// fetch attributes
				$productAttributes = array();
				foreach ($product as $attributeCode => $attributeValue) {
					$attribute = $this->_getSearchableAttribute($attributeCode);
					if (!$attribute) {
						continue;
					}
					$attributeValue = $this->_convertProductAttributeValue($store, $attribute, $attributeValue);
					if (strlen($attributeValue) > 0 && (!isset($productAttributes[$attributeCode]) || !in_array($attributeValue, $productAttributes[$attributeCode]))) {
						if (!isset($productAttributes[$attributeCode])) {
							$productAttributes[$attributeCode] = array();
						}
						$productAttributes[$attributeCode][] = $attributeValue;
					}
				}
				$this->_mergeProductAttributeValues($store, $productsAttributes[$product['entity_id']], $productAttributes);
				if (isset($productsRelations[$product['entity_id']])) {
					foreach ($productsRelations[$product['entity_id']] as $productRelation) {
						// check status/visibility
						if (!isset($productsAttributes[$productRelation])) {
							continue;
						}
						if (!isset($productsAttributes[$productRelation][$status->getId()]) || !in_array($productsAttributes[$productRelation][$status->getId()], $statusValues)) {
							continue;
						}
/*						if (!isset($productsAttributes[$productRelation][$visibility->getId()]) || !in_array($productsAttributes[$productRelation][$visibility->getId()], $visibilityValues)) {
							continue;
						}*/

/*						if (!isset($productAttributes['entity_id'])) {
							$productAttributes['entity_id'] = array();
						}
						if (!in_array($productRelation, $productAttributes['entity_id'])) {
							$productAttributes['entity_id'][] = $productRelation;
						}*/
						$this->_mergeProductAttributeValues($store, $productsAttributes[$productRelation], $productAttributes);
					}
				}

				// fetch combinations
				$productCombinations = array(
					'attributes' => array(),
					'products' => array()
				);
				if ($product['type_id'] == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
					$typeInstance = $this->_getProductTypeInstance($product['type_id']);
					$attributes = $this->_getAllAttributes();
					foreach ($typeInstance->getConfigurableAttributes($productItem) as $attribute) {
						if (!isset($attributes[$attribute->getAttributeId()])) {
							continue;
						}
						$attribute = $attributes[$attribute->getAttributeId()];
						$productCombinations['attributes'][] = $attribute->getAttributeCode();
					}
					if (sizeof($productCombinations['attributes']) > 0 && isset($productsRelations[$product['entity_id']])) {
						foreach ($productsRelations[$product['entity_id']] as $productRelation) {
							// check status/visibility
							if (!isset($productsAttributes[$productRelation])) {
								continue;
							}
							if (!isset($productsAttributes[$productRelation][$status->getId()]) || !in_array($productsAttributes[$productRelation][$status->getId()], $statusValues)) {
								continue;
							}
/*							if (!isset($productsAttributes[$productRelation][$visibility->getId()]) || !in_array($productsAttributes[$productRelation][$visibility->getId()], $visibilityValues)) {
								continue;
							}*/

							$productRelationAttributes = array();
							$productRelationAttributes['entity_id'] = array($productRelation);
							$productRelationAttributes['_stock'] = array(isset($productsStock[$product['entity_id']]) ? ($productsStock[$product['entity_id']]['status'] ? $productsStock[$product['entity_id']]['quantity'] : 0) : 0);
							$productRelationAttributes['_views'] = array(isset($productsSales[$product['entity_id']]) ? $productsSales[$product['entity_id']] + 1 : 1);
							$productRelationAttributes['_sales'] = array(isset($productsSales[$product['entity_id']]) ? $productsSales[$product['entity_id']] : 0);
							$this->_mergeProductAttributeValues($store, $productsAttributes[$productRelation], $productRelationAttributes);
							$productCombinations['products'][] = $productRelationAttributes;
						}
					}
				}

				// notify: product write
				$productSubProduct = array();
				if (isset($productSubProducts[$product['entity_id']])) {
					$productSubProduct = $productSubProducts[$product['entity_id']];
				}
				if (!$this->_processStoreSync($store, $categories, $tags, $fields, $productCategories, $productTags, $productAttributes, $productCombinations, $productSubProduct)) {
					Mage::throwException(Mage::helper('boxalinocem')->__('Synchronization failed (code %d)!', 11));
				}
				$totalProducts++;
			}
		}

		// notify: end store sync
		if (!$this->_endStoreSync($store)) {
			Mage::throwException(Mage::helper('boxalinocem')->__('Synchronization failed (code %d)!', 12));
		}
	}


	private function _getCategories() {
		$categories = array();
		$disabled = array();
		foreach ($this->getStores() as $store) {
			$language = $store->getConfig('boxalinocem/service/language');

			$collection = Mage::getModel('catalog/category')->getCollection();
			$collection->setStoreId($store->getId());
			$attributes = Mage::getConfig()->getNode('frontend/category/collection/attributes');
			if ($attributes) {
				$collection->addAttributeToSelect(array_keys($attributes->asArray()));
			}

			foreach ($collection as $item) {
				if ($item->level < 2) {
					continue;
				}
				if (!$item->is_active) {
					$disabled[] = $item->entity_id;
					continue;
				}
				if (!isset($categories[$item->entity_id])) {
					//$path = explode('/', $item->path);
					$categories[$item->entity_id] = array(
						'id' => $item->entity_id,
						'position' => $item->position,
						'names' => array(),
						'parent' => $item->parent_id
					);
				}
				$categories[$item->entity_id]['names'][$language] = $item->name;
			}
		}

		$list = array();
		foreach ($categories as $id => $category) {
			if (in_array($category['parent'], $disabled)) {
				continue;
			}
			if (!isset($categories[$category['parent']])) {
				$category['parent'] = 0;
			}
			$list[$id] = $category;
		}
		return $list;
	}


	private function _getTags() {
		$tags = array();
		foreach ($this->getStores() as $store) {
			$language = $store->getConfig('boxalinocem/service/language');

			$select = $this->_getReadAdapter()->select()
				->from(array('t' => $this->getTable('tag/tag')))
				->join(
					array('r' => $this->getTable('tag/relation')),
					$this->_getReadAdapter()->quoteInto("r.tag_id = t.tag_id AND r.active = TRUE AND r.store_id = ?", $store->getId()),
					array()
				)
				->where('t.status = ?', Mage_Tag_Model_Tag::STATUS_APPROVED);

			$query = $this->_getReadAdapter()->query($select);
			while ($row = $query->fetch()) {
				if (!isset($tags[$row['tag_id']])) {
					$tags[$row['tag_id']] = array(
						'id' => $row['tag_id'],
						'names' => array()
					);
				}
				$tags[$row['tag_id']]['names'][$language] = $row['name'];
			}
		}
		return $tags;
	}


	private function _getSearchableAttribute($code) {
		$attributes = $this->_getSearchableAttributes();
		foreach ($attributes as $attribute) {
			if ($attribute->getAttributeCode() == $code) {
				return $attribute;
			}
		}
		return Mage::getSingleton('eav/config')->getAttribute('catalog_product', $code);
	}

	private function _getSearchableAttributes($backendType = null) {
		if (is_null($this->_searchableAttributes)) {
			$eavConfig = Mage::getSingleton('eav/config');
			$entityType = $eavConfig->getEntityType('catalog_product');
			$entity = $entityType->getEntity();

			$select = $this->_getReadAdapter()->select()
				->distinct()
				->from(
					array('t' => $this->getTable('catalog/product_super_attribute')),
					array('attribute_id')
				);
			$combinationAttributes = array();
			foreach ($this->_getReadAdapter()->fetchAll($select) as $row) {
				$combinationAttributes[] = $row['attribute_id'];
			}

			$additionalAttributes = array(
				'entity_id',
				'status',
				'visibility',
				'image',
				'small_image',
				'special_price',
				'special_from_date',
				'special_to_date',
				'url_key',
				'url_path'
			);
			foreach ($this->_additionalAttributes as $attributeCode) {
				if (!in_array($attributeCode, $additionalAttributes)) {
					$additionalAttributes[] = $attributeCode;
				}
			}
//			print_r($additionalAttributes); exit;

			$select = $this->_getReadAdapter()->select()
				->from(array('main_table' => $this->getTable('eav/attribute')))
				->join(
					array('additional_table' => $this->getTable('catalog/eav_attribute')),
					'additional_table.attribute_id = main_table.attribute_id'
				)
				->where('main_table.entity_type_id = ?', $entityType->getEntityTypeId())
				->where(
					$this->_getReadAdapter()->quoteInto('additional_table.is_searchable = ?', 1).
					' OR '.
					$this->_getReadAdapter()->quoteInto('main_table.attribute_id IN (?)', $combinationAttributes).
					' OR '.
					$this->_getReadAdapter()->quoteInto('main_table.attribute_code IN (?)', $additionalAttributes)
				);
			$attributesData = $this->_getReadAdapter()->fetchAll($select);
			$eavConfig->importAttributesData($entityType, $attributesData);

			$this->_searchableAttributes = array();
			foreach ($attributesData as $attributeData) {
				$attributeCode = $attributeData['attribute_code'];
				$attribute = $eavConfig->getAttribute($entityType, $attributeCode);
				$attribute->setEntity($entity);
				$this->_searchableAttributes[$attribute->getId()] = $attribute;
			}
			unset($attributesData);
		}
		if (!is_null($backendType)) {
			$attributes = array();
			foreach ($this->_searchableAttributes as $attribute) {
				if ($attribute->getBackendType() == $backendType) {
					$attributes[$attribute->getId()] = $attribute;
				}
			}
			return $attributes;
		}
		return $this->_searchableAttributes;
	}

	private function _getAllAttributes($backendType = null) {
		if (is_null($this->_allAttributes)) {
			$eavConfig = Mage::getSingleton('eav/config');
			$entityType = $eavConfig->getEntityType('catalog_product');
			$entity = $entityType->getEntity();

			$select = $this->_getReadAdapter()->select()
				->from(array('main_table' => $this->getTable('eav/attribute')))
				->join(
					array('additional_table' => $this->getTable('catalog/eav_attribute')),
					'additional_table.attribute_id = main_table.attribute_id'
				)
				->where('main_table.entity_type_id = ?', $entityType->getEntityTypeId());
			$attributesData = $this->_getReadAdapter()->fetchAll($select);
			$eavConfig->importAttributesData($entityType, $attributesData);

			$this->_allAttributes = array();
			foreach ($attributesData as $attributeData) {
				$attributeCode = $attributeData['attribute_code'];
				$attribute = $eavConfig->getAttribute($entityType, $attributeCode);
				$attribute->setEntity($entity);
				$this->_allAttributes[$attribute->getId()] = $attribute;
			}
			unset($attributesData);
		}
		if (!is_null($backendType)) {
			$attributes = array();
			foreach ($this->_allAttributes as $attribute) {
				if ($attribute->getBackendType() == $backendType) {
					$attributes[$attribute->getId()] = $attribute;
				}
			}
			return $attributes;
		}
		return $this->_allAttributes;
	}


	private function _getProductTypeInstance($typeId) {
		if (!isset($this->_productTypes[$typeId])) {
			$productEmulator = new Varien_Object();
			$productEmulator->setIdFieldName('entity_id');
			$productEmulator->setTypeId($typeId);
			$this->_productTypes[$typeId] = Mage::getSingleton('catalog/product_type')->factory($productEmulator);
		}
		return $this->_productTypes[$typeId];
	}

	private function _getProducts($store, $staticFields, $nextProductId = 0, $limit = 100) {
		$select = $this->_getReadAdapter()->select()
			->useStraightJoin(true)
			->from(
				array('e' => $this->getTable('catalog/product')),
				$staticFields
			)
			->join(
				array('website' => $this->getTable('catalog/product_website')),
				$this->_getReadAdapter()->quoteInto('website.product_id = e.entity_id AND website.website_id = ?', $store->getWebsiteId()),
				array()
			)
			->where('e.entity_id > ?', $nextProductId)
			->limit($limit)
			->order('e.entity_id');

		return $this->_getReadAdapter()->fetchAll($select);
	}

	private function _getProductChildren($productId, $typeId) {
		$typeInstance = $this->_getProductTypeInstance($typeId);
		if (!$typeInstance->isComposite()) {
			return null;
		}

		$relation = $typeInstance->getRelationInfo();
		if (!$relation || !$relation->getTable() || !$relation->getParentFieldName() || !$relation->getChildFieldName()) {
			return null;
		}

		$select = $this->_getReadAdapter()->select()
			->from(
				array('main' => $this->getTable($relation->getTable())),
				array($relation->getChildFieldName()))
			->where("{$relation->getParentFieldName()} = ?", $productId);
		if (!is_null($relation->getWhere())) {
			$select->where($relation->getWhere());
		}
		return $this->_getReadAdapter()->fetchCol($select);
	}

	private function _getProductUrls($store, $productIds) {
		$select = $this->_getReadAdapter()->select()
			->from(
				$this->getTable('core/url_rewrite'),
				array('product_id', 'request_path')
			)
			->where('store_id = ?', $store->getId())
			->where('is_system = ?', 1)
			->where('product_id IN (?)', $productIds)
			->order('category_id DESC');

		$result = array();
		$query = $this->_getReadAdapter()->query($select);
		while ($row = $query->fetch()) {
			$result[$row['product_id']] = $row['request_path'];
		}
		return $result;
	}

	private function _getProductCategories($store, $productIds) {
		$select = $this->_getReadAdapter()->select()
			->from(
				array('e' => $this->getTable('catalog/product')),
				array('entity_id' => 'e.entity_id')
			)
			->join(
				array('w' => $this->getTable('catalog/product_website')),
				$this->_getReadAdapter()->quoteInto('w.product_id = e.entity_id AND w.website_id = ?', $store->getWebsiteId()),
				array()
			)
			->join(
				array('c' => $this->getTable('catalog/category_product')),
				'e.entity_id = c.product_id',
				array('category_id' => 'c.category_id')
			)
			->where('e.entity_id IN (?)', $productIds);

		$result = array();
		$query = $this->_getReadAdapter()->query($select);
		while ($row = $query->fetch()) {
			if (!isset($result[$row['entity_id']])) {
				$result[$row['entity_id']] = array();
			}
			$result[$row['entity_id']][] = $row['category_id'];
		}
		return $result;
	}

	private function _getProductTags($store, $productIds) {
		$select = $this->_getReadAdapter()->select()
			->from(
				array('e' => $this->getTable('catalog/product')),
				array('entity_id' => 'e.entity_id')
			)
			->join(
				array('website' => $this->getTable('catalog/product_website')),
				$this->_getReadAdapter()->quoteInto('website.product_id = e.entity_id AND website.website_id = ?', $store->getWebsiteId()),
				array()
			)
			->join(
				array('t' => $this->getTable('tag/relation')),
				'e.entity_id = t.product_id',
				array('tag_id' => 't.tag_id')
			)
			->where('e.entity_id IN (?)', $productIds);

		$result = array();
		$query = $this->_getReadAdapter()->query($select);
		while ($row = $query->fetch()) {
			if (!isset($result[$row['entity_id']])) {
				$result[$row['entity_id']] = array();
			}
			if (in_array($row['tag_id'], $result[$row['entity_id']])) {
				continue;
			}
			$result[$row['entity_id']][] = $row['tag_id'];
		}
		return $result;
	}

	private function _getProductStock($store, $productIds) {
		$select = $this->_getReadAdapter()->select()
			->from(
				$this->getTable('cataloginventory/stock_item'),
				array('product_id' => 'product_id', 'qty' => 'SUM(qty)', 'is_in_stock' => 'SUM(is_in_stock)')
			)
			->where('product_id IN (?)', $productIds)
			->group('product_id');

		$result = array();
		$query = $this->_getReadAdapter()->query($select);
		while ($row = $query->fetch()) {
			$result[$row['product_id']] = array(
				'quantity' => intval($row['qty']),
				'status' => intval($row['is_in_stock']) > 0
			);
		}
		return $result;
	}

	private function _getProductSales($store, $productIds) {
		$select = $this->_getReadAdapter()->select()
			->from(
				$this->getTable('sales/order_item'),
				array('product_id' => 'product_id', 'qty_ordered' => 'SUM(qty_ordered)')
			)
			->where('store_id = ?', $store->getId())
			->where('product_id IN (?)', $productIds)
			->group('product_id');

		$result = array();
		$query = $this->_getReadAdapter()->query($select);
		while ($row = $query->fetch()) {
			$result[$row['product_id']] = intval($row['qty_ordered']);
		}
		return $result;
	}

	private function _getProductViews($store, $productIds)
	{
		$resource = Mage::getResourceModel('reports/event');
		$select = $resource->getReadConnection()->select()
			->from(array('ev' => $resource->getMainTable()), array(
				'product_id' => 'object_id',
				'view_count' => new Zend_Db_Expr('COUNT(*)')
			))
			->join(
				array('et' => $resource->getTable('reports/event_type')),
				"ev.event_type_id=et.event_type_id AND et.event_name='catalog_product_view'",
				''
			)
			->group('ev.object_id')
			->where('ev.object_id IN(?)', $productIds)
			->where('ev.store_id = ?', $store->getId());

		return $resource->getReadConnection()->fetchPairs($select);
	}

	private function _getProductAttributes($store, $productIds, $attributeTypes) {
		$selects = array();
		foreach ($attributeTypes as $backendType => $attributeIds) {
			if (sizeof($attributeIds) == 0) {
				continue;
			}
			$tableName = $this->getTable('catalog/product') . '_' . $backendType;
			$selects[] = $this->_getReadAdapter()->select()
				->from(
					array('t_default' => $tableName),
					array('entity_id', 'attribute_id')
				)
				->joinLeft(
					array('t_store' => $tableName),
					$this->_getReadAdapter()->quoteInto("t_default.entity_id = t_store.entity_id AND t_default.attribute_id = t_store.attribute_id AND t_store.store_id = ?", $store->getId()),
					array('value' => 'IF(t_store.value_id > 0, t_store.value, t_default.value)')
				)
				->where('t_default.store_id = ?', 0)
				->where('t_default.entity_id IN (?)', $productIds)
				->where('t_default.attribute_id IN (?)', $attributeIds);
		}

		$result = array();
		if (sizeof($selects) > 0) {
			$select = '('.join(') UNION (', $selects).')';
			$query = $this->_getReadAdapter()->query($select);
			while ($row = $query->fetch()) {
				$result[$row['entity_id']][$row['attribute_id']] = $row['value'];
			}
		}
		return $result;
	}

	private function _mergeProductValues($store, $attributeValues, &$product) {
		foreach ($attributeValues as $attributeId => $attributeValue) {
			$attribute = $this->_getSearchableAttribute($attributeId);
			$attributeCode = $attribute->getAttributeCode();
			$product[$attributeCode] = $this->_convertProductAttributeValue($store, $attribute, $attributeValue);
		}
	}

	private function _mergeProductAttributeValues($store, $attributeValues, &$productAttributes) {
		$attributes = $this->_getSearchableAttributes();
		foreach ($attributeValues as $attributeId => $attributeValue) {
			if (!isset($attributes[$attributeId])) {
				continue;
			}
			$attribute = $attributes[$attributeId];
			$attributeCode = $attribute->getAttributeCode();
			$attributeValue = $this->_convertProductAttributeValue($store, $attribute, $attributeValue);
			if (strlen($attributeValue) > 0) {
				if (!isset($productAttributes[$attributeCode])) {
					$productAttributes[$attributeCode] = array();
				}
				if (!in_array($attributeValue, $productAttributes[$attributeCode])) {
					$productAttributes[$attributeCode][] = $attributeValue;
				}
			}
		}
	}

	private function _convertProductAttributeValue($store, $attribute, $value) {
		if ($attribute->usesSource()) {
			$attribute->setStoreId($store->getId());
			if($attribute->getFrontendInput() == 'multiselect') {
				$values = explode(',', $value);
				if(count($values) > 1) {
					$result = array();
					foreach ($values as $val) {
						$result[] = $attribute->getSource()->getOptionText($val);
					}
					return implode('<|>', $result);
				}
			}
			return $attribute->getSource()->getOptionText($value);
		}
/*		if ($attribute->getBackendType() == 'datetime') {
			if ($attribute->getCode() == 'created_at' || $attribute->getCode() == 'updated_at') {
				return $value;
			}
			if (is_empty_date($value)) {
				return null;
			}
			if (!isset($this->_storeDateFormats[$store->getId()])) {
				$locale = new Zend_Locale(
					Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $store->getId())
				);
				$date = new Zend_Date(null, null, $locale);
				$date->setTimezone(
					Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, $store->getId())
				);
				$this->_storeDateFormats[$store->getId()] = array($date, $locale->getTranslation(null, 'date', $locale));
			}

			list($date, $format) = $this->_storeDateFormats[$store->getId()];

			$date->setDate($value, Varien_Date::DATETIME_INTERNAL_FORMAT);
			return $date->toString($format);
		}*/
		if ($attribute->getFrontend()->getInputType() == 'price') {
			return $store->roundPrice($value);
		}
		return $value;
	}

	private function _parseImageSize($string) {
		$size = explode('x', strtolower($string));
		if (sizeof($size) == 2) {
			$rotate = explode('r', $size[1]);
			$size[1] = $rotate[0];
			$rotate = (sizeof($rotate) == 2 ? intval($rotate[1]) : null);
			return array(
				'width' => ($size[0] > 0) ? $size[0] : null,
				'height' => ($size[1] > 0) ? $size[1] : null,
				'heigth' => ($size[1] > 0) ? $size[1] : null, // Magento bug in Mage/Catalog/Model/Product/Image.php
				'rotate' => ($rotate != 0) ? $rotate : null,
			);
		} elseif (sizeof($size) == 1) {
			$rotate = explode('r', $size[0]);
			$size[0] = $rotate[0];
			$rotate = (sizeof($rotate) == 2 ? intval($rotate[1]) : null);
			return array(
				'width' => ($size[0] > 0) ? $size[0] : null,
				'height' => null,
				'heigth' => null, // Magento bug in Mage/Catalog/Model/Product/Image.php
				'rotate' => ($rotate != 0) ? $rotate : null,
			);
		}
		return false;
	}

	private function _getSubProductValues($store, $productId, $productChildren, $dynamicFields, $categories, $tags) {
		// load urls
		$productsUrls = $this->_getProductUrls($store, $productChildren);

		// load stocks
		$productsStock = $this->_getProductStock($store, $productChildren);

		// load categories
		if (sizeof($categories) > 0) {
			$productsCategories = $this->_getProductCategories($store, $productChildren);
		} else {
			$productsCategories = array();
		}

		// load tags
		if (sizeof($tags) > 0) {
			$productsTags = $this->_getProductTags($store, $productChildren);
		} else {
			$productsTags = array();
		}

		$productsAttributes = $this->_getProductAttributes($store, $productChildren, $dynamicFields);

		$values = array();
		foreach ($productChildren as $productChild) {
			$product = array();
			$productItem = Mage::getModel('boxalinocem/product');

			$product['entity_id'] = $productChild;
			$product['store_id'] = $store->getId();
			if (isset($productsUrls[$productChild])) {
				$product['request_path'] = $productsUrls[$productChild];
			}
			$this->_mergeProductValues($store, $productsAttributes[$product['entity_id']], $product);
			$productItem->fromArray($product);

			$values[] = array('id' => $productId, 'subProduct_ID' => $productChild, 'subProduct_storeID' => $store->getId(), 'subProduct_lable' => 'quantity',
				'subProduct_value' => isset($productsStock[$productChild]['quantity']) ? $productsStock[$productChild]['quantity'] : 0
			);
			$values[] = array('id' => $productId, 'subProduct_ID' => $productChild, 'subProduct_storeID' => $store->getId(), 'subProduct_lable' => 'status',
				'subProduct_value' => isset($productsStock[$productChild]['status']) ? $productsStock[$productChild]['status'] : false
			);
			$values[] = array('id' => $productId, 'subProduct_ID' => $productChild, 'subProduct_storeID' => $store->getId(), 'subProduct_lable' => 'url',
				'subProduct_value' => $productItem->getProductUrl(),
			);
			$values[] = array('id' => $productId, 'subProduct_ID' => $productChild, 'subProduct_storeID' => $store->getId(), 'subProduct_lable' => 'url_wishlist',
				'subProduct_value' => $productItem->getAddToWishlistUrl()
			);
			$values[] = array('id' => $productId, 'subProduct_ID' => $productChild, 'subProduct_storeID' => $store->getId(), 'subProduct_lable' => 'url_comparator',
				'subProduct_value' => $productItem->getAddToCompareUrl()
			);
			$values[] = array('id' => $productId, 'subProduct_ID' => $productChild, 'subProduct_storeID' => $store->getId(), 'subProduct_lable' => 'url_basket',
				'subProduct_value' => $productItem->getAddToCartUrl()
			);
			if (isset($productsCategories[$productChild])) {
				foreach ($productsCategories[$productChild] as $category) {
					$values[] = array('id' => $productId, 'subProduct_ID' => $productChild, 'subProduct_storeID' => $store->getId(), 'subProduct_lable' => 'categories',
						'subProduct_value' => $category
					);
				}
			}
			if (isset($productsTags[$productChild])) {
				foreach ($productsTags[$productChild] as $tags) {
					$values[] = array('id' => $productId, 'subProduct_ID' => $productChild, 'subProduct_storeID' => $store->getId(), 'subProduct_lable' => 'tags',
						'subProduct_value' => $tags
					);
				}
			}

			foreach ($product as $k => $v) {
				$values[] = array('id' => $productId, 'subProduct_ID' => $productChild, 'subProduct_storeID' => $store->getId(), 'subProduct_lable' => $k,
					'subProduct_value' => $v
				);
			}
		}
		return $values;
	}
}

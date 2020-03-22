<?php
class OcErplyService
{

	private static $debug_enabled = 1;

	private $erplyClient;
	private $erplyHelper;

	private $productModel;
	private $catalogModel;
	private $erplyModel;

	public function __construct($erplyClient, $erplyHelper, $productModel, $catalogModel, $erplyModel)
	{
		$this->$erplyClient = $erplyClient;
		$this->$erplyHelper = $erplyHelper;

		$this->$erplyHelper = $productModel;
		$this->$catalogModel = $catalogModel;
		$this->$erplyModel = $erplyModel;
	}

	private function sync_categories_and_products()
	{
		$categories = $this->erplyClient->get_categories();

		foreach ($categories as $category) {
			$this->sync_category_recursive($category);
		}
	}

	private function sync_category_recursive($erply_category, $parent_oc_category_id = null)
	{
		$this->debug("@sync_category_recursive handling category " . $erply_category['name'] . " with parent " . $parent_oc_category_id);

		$mapping = $this->erplyModel->find_category_mapping_by_erply_id($erply_category['productGroupID']);

		// check if mapping exists and remove it if existing mapping is invalid
		if ($mapping) {
			$oc_category = $this->erplyHelper->erply_to_oc_category($erply_category, $parent_oc_category_id);
			$oc_db_category = $this->categoryModel->getCategory($mapping['oc_category_id']);

			if ($oc_db_category) {
				$category_id = $mapping['oc_category_id'];
			} else {
				// category removed from OC but present in Erply
				$this->erplyModel->remove_category_mapping($erply_category['productGroupID']);
			}
		}

		if (!isset($category_id)) {
			// create new category with mapping
			$oc_category = $this->erplyHelper->erply_to_oc_category($erply_category, $parent_oc_category_id);
			$category_id = $this->categoryModel->addCategory($oc_category);
			$this->erplyModel->add_category_mapping($category_id, $erply_category['productGroupID'], (int)$erply_category['lastModified']);
			$mapping = $this->erplyModel->find_category_mapping_by_erply_id($erply_category['productGroupID']);

			$this->debug("@sync_category_recursive created new category " . $erply_category['name'] . " with id " . $category_id);
		}

		// sync products and sub-categories
		if (isset($category_id)) {
			// update category data if needed
			if ((int)$mapping['timestamp'] < (int)$erply_category['lastModified']) {
				$this->categoryModel->editCategory($category_id, $oc_category);
				$this->erplyModel->update_category_timestamp($category_id, (int)$erply_category['lastModified']);
			}

			$this->sync_products($erply_category['productGroupID'], $category_id);

			if (!empty($erply_category['subGroups'])) {
				foreach ($erply_category['subGroups'] as $erply_sub_category) {
					$this->sync_category_recursive($erply_sub_category, $category_id);
				}
			}
		} else {
			// TODO: oc category id missing from mapping or no mapping and oc category creation failed
		}
	}

	/**
	 * Returns number of products retrieved from Erply (for setting category columns)
	 */
	private function sync_products($erply_category_id, $oc_category_id)
	{
		$this->debug("@sync_products creating products for category with id " . $oc_category_id);

		$offset = 0;
		$erply_products = array();

		$erply_response = $this->erplyClient->get_products($offset, 100, 1, 1, null, $erply_category_id);

		if ($erply_response['status']['responseStatus'] == 'error') {
			throw new Exception("Error in Erply response");
		}

		$erply_products = $erply_response['records'];

		while ($offset < $erply_response['status']['recordsTotal']) {
			$offset += 100;
			$erply_response = $this->erplyClient->get_products($offset, 100, 1, 1, null, $erply_category_id);

			if ($erply_response['status']['responseStatus'] == 'error') {
				throw new Exception("Error in Erply response");
			}

			$erply_products = array_merge($erply_products, $erply_response['records']);
		}

		foreach ($erply_products as $erply_product) {
			$this->sync_product($erply_product, $oc_category_id);
		}

		return sizeof($erply_products);
	}

	private function sync_product($erply_product, $oc_category_id)
	{
		$mapping = $this->erplyModel->find_product_mapping_by_erply_id($erply_product['productID']);

		$oc_product = $this->erplyHelper->erply_to_oc_product($erply_product, $oc_category_id);

		if ($mapping) {
			$oc_db_product = $this->productModel->getProduct($mapping['oc_product_id']);

			if ($oc_db_product) {
				// TODO: replace this with something compatible with OC-s editProduct function

				$this->debug("@sync_product mapping for product " . $erply_product['productID'] . " already exists, updating product!");

				if ((int)$mapping['timestamp'] == (int)$erply_product['lastModified']) {
					$this->debug("@sync_product product " . $oc_db_product['product_id'] . " already up to date!");
					return;
				}

				if ((int)$erply_product['displayedInWebshop'] != (int)$oc_db_product['status']) {
					$this->debug("@sync_product updating product " . $oc_db_product['product_id'] . " status from " . (int)$oc_db_product['status'] . " to " . (int)$erply_product['displayedInWebshop']);
					$this->erplyModel->set_product_status($oc_db_product['product_id'], (int)$erply_product['displayedInWebshop']);
				}

				$oc_product_categories = $this->productModel->getProductCategories($oc_db_product['product_id']);
				if (!in_array($oc_category_id, $oc_product_categories)) {
					// TODO: support multiple categories
					$this->debug("@sync_product updating product " . $oc_db_product['product_id'] . " categories from " . implode(",", $oc_product_categories) . " to " . $oc_category_id);
					$this->erplyModel->set_product_category($oc_db_product['product_id'], $oc_category_id);
				}

				if ($oc_db_product['price'] != $oc_product['price']) {
					$this->debug("@sync_product updating product " . $oc_db_product['product_id'] . " price from " . $oc_db_product['price'] . " to " . $oc_product['price']);
					$this->erplyModel->set_product_price($oc_db_product['product_id'], $oc_product['price']);
				}

				if ($oc_db_product['quantity'] != $oc_product['quantity']) {
					$this->debug("@sync_product updating product " . $oc_db_product['product_id'] . " quantity from " . $oc_db_product['quantity'] . " to " . $oc_product['quantity']);
					$this->erplyModel->set_product_stock($oc_db_product['product_id'], $oc_product['quantity']);
				}

				if ($oc_db_product['stock_status_id'] != $oc_product['stock_status_id']) {
					$this->debug("@sync_product updating product " . $oc_db_product['product_id'] . " stock_status_id from " . $oc_db_product['stock_status_id'] . " to " . $oc_product['stock_status_id']);
					$this->erplyModel->set_product_stock_status($oc_db_product['product_id'], $oc_product['stock_status_id']);
				}

				$this->add_product_images($erply_product, $oc_db_product['product_id']);

				$this->erplyModel->update_product_timestamp($oc_db_product['product_id'], (int)$erply_product['lastModified']);

				return;
			} else {
				$this->debug("@sync_product invalid mapping for product " . $erply_product['productID'] . ", recreating!");
				$this->erplyModel->remove_product_mapping($erply_product['productID']);
			}
		} else {
			if ((int)$erply_product['displayedInWebshop'] == 0) {
				return;
			}
		}

		$this->debug("@sync_product creating product " . $erply_product['name'] . " with model " . $erply_product['code']);

		$oc_product_id = $this->productModel->addProduct($oc_product);

		$this->erplyModel->add_product_mapping($oc_product_id, $erply_product['productID'], (int)$erply_product['lastModified']);
		$this->add_product_images($erply_product, $oc_product_id);
	}

	private function add_product_images($erply_product, $oc_product_id)
	{
		$images = $this->erplyHelper->download_product_images($erply_product);

		if (!isset($images)) {
			return;
		}

		$this->debug("@add_product_images adding " . sizeof($images) . " images for product " . $oc_product_id);

		$this->erplyModel->add_product_images($oc_product_id, $images);
	}

	private function delete_removed_categories()
	{
		$remote_ids = $this->erplyClient->get_category_ids();
		$erply_mappings = $this->erplyModel->get_category_mappings();
		$tracked_erply_ids = array();
		$erply_to_oc_category_map = array();

		// get locally tracked Erply category ids
		foreach ($erply_mappings as $mapping) {
			$tracked_erply_ids[] = $mapping['erply_category_id'];
			$erply_to_oc_category_map[$mapping['erply_category_id']] = $mapping['oc_category_id'];
		}

		// check which local Erply ids are not present in remote ids
		foreach ($tracked_erply_ids as $tracked_erply_id) {

			$oc_id = $erply_to_oc_category_map[$tracked_erply_id];

			if (!in_array($tracked_erply_id, $remote_ids)) {
				$this->debug("@delete_removed_categories missing erply category " . $tracked_erply_id . ", disabling OC category " . $oc_id);
				$this->erplyModel->set_category_status($oc_id, 0);
			} else {
				// check if tracked category is removed locally
				$tracked_oc_category = $this->categoryModel->getCategory($oc_id);
				if (!isset($tracked_oc_category) || !isset($tracked_oc_category['category_id'])) {
					$this->debug("@delete_removed_categories removing mapping for deleted category " . $tracked_erply_id);
					$this->erplyModel->remove_category_mapping($tracked_erply_id);
				}
			}
		}
	}

	private function delete_removed_products()
	{
		$erply_mappings = $this->erplyModel->get_product_mappings();
		$tracked_erply_ids = array();
		$erply_to_oc_product_map = array();

		// get locally tracked Erply product ids
		foreach ($erply_mappings as $mapping) {
			$tracked_erply_ids[] = $mapping['erply_product_id'];
			$erply_to_oc_product_map[$mapping['erply_product_id']] = $mapping['oc_product_id'];
		}

		$erply_ids_chunked = array_chunk($tracked_erply_ids, 1000);
		$remote_erply_ids = array();

		// get remote Erply product ids
		foreach ($erply_ids_chunked as $ids_batch) {
			$erply_response = $this->erplyClient->get_products_simple(0, 1000, $ids_batch);

			if ($erply_response['status']['responseStatus'] == 'error') {
				throw new Exception(" Error in Erply response");
			}

			foreach ($erply_response['records'] as $erply_product) {
				$remote_erply_ids[] = $erply_product['productID'];
			}
		}

		// check which local Erply ids are not present in remote ids
		foreach ($tracked_erply_ids as $tracked_erply_id) {

			$oc_id = $erply_to_oc_product_map[$tracked_erply_id];

			if (!in_array($tracked_erply_id, $remote_erply_ids)) {
				$this->debug("@delete_removed_products missing erply product " . $tracked_erply_id . ", disabling OC product " . $oc_id);
				$this->erplyModel->set_product_status($oc_id, 0);
			} else {
				// check if tracked product is removed locally
				$tracked_oc_product = $this->productModel->getProduct($oc_id);
				if (!isset($tracked_oc_product) || !isset($tracked_oc_product['product_id'])) {
					$this->debug("@delete_removed_products removing mapping for deleted product " . $oc_id);
					$this->erplyModel->remove_product_mapping($tracked_erply_id);
				}
			}
		}
	}

	private function debug($message)
	{
		if (self::$debug_enabled) {
			$this->debug($message);
		}
	}
}

<?php
class ModelExtensionModuleErply extends Model
{

	public function install()
	{
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "erply_oc_product` (
			`oc_product_id` int(11) NOT NULL ,
			`erply_product_id` int(11) NOT NULL ,
			`timestamp` int(11) NOT NULL,
			UNIQUE(`oc_product_id`),
			UNIQUE(`erply_product_id`)
		) DEFAULT COLLATE=utf8_general_ci;");

		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "erply_oc_category` (
			`oc_category_id` int(11) NOT NULL ,
			`erply_category_id` int(11) NOT NULL ,
			`timestamp` int(11) NOT NULL,
			UNIQUE(`oc_category_id`),
			UNIQUE(`erply_category_id`)
		) DEFAULT COLLATE=utf8_general_ci;");
	}

	public function uninstall()
	{
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "erply_oc_product`");
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "erply_oc_category`");

		$this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `code` = 'module_erply'");
	}

	public function get_product_mappings(){
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "erply_oc_product");
		return $query->rows;
	}

	public function add_category_mapping($oc_category_id, $erply_category_id, $timestamp)
	{
		$this->db->query("INSERT INTO " . DB_PREFIX . "erply_oc_category SET oc_category_id = '" . (int)$oc_category_id . "', erply_category_id = '" . (int)$erply_category_id . "', timestamp = '" . (int)$timestamp . "'");
		return $this->db->getLastId();
	}

	public function add_product_mapping($oc_product_id, $erply_product_id, $timestamp)
	{
		$this->db->query("INSERT INTO " . DB_PREFIX . "erply_oc_product SET oc_product_id = '" . (int)$oc_product_id . "', erply_product_id = '" . (int)$erply_product_id . "', timestamp = '" . (int)$timestamp . "'");
		return $this->db->getLastId();
	}

	public function find_product_mapping_by_erply_id($erply_product_id)
	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "erply_oc_product WHERE erply_product_id = '" . (int)$erply_product_id . "'");
		return $query->row;
	}

	public function find_category_mapping_by_erply_id($erply_category_id)
	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "erply_oc_category WHERE erply_category_id = '" . (int)$erply_category_id . "'");
		return $query->row;
	}

	public function remove_category_mapping($erply_category_id)
	{
		$query = $this->db->query("DELETE FROM " . DB_PREFIX . "erply_oc_category WHERE erply_category_id = '" . (int)$erply_category_id . "'");
	}

	public function remove_product_mapping($erply_product_id)
	{
		$query = $this->db->query("DELETE FROM " . DB_PREFIX . "erply_oc_product WHERE erply_product_id = '" . (int)$erply_product_id . "'");
	}

	public function set_product_category($product_id, $category_id){
		if (!isset($product_id) || !isset($category_id)) {
			throw new Exception("Product or category not set.");
		}

		$this->db->query("DELETE FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");
		$this->db->query("INSERT INTO " . DB_PREFIX . "product_to_category SET product_id = '" . (int)$product_id . "', category_id = '" . (int)$category_id . "'");
	}

	public function set_product_price($product_id, $price)
	{
		if (!isset($price) || !isset($product_id)) {
			return;
		}

		$query = $this->db->query("UPDATE " . DB_PREFIX . "product SET price = '" . (float)$price . "' WHERE product_id = '" . (int)$product_id . "'");

		$this->cache->delete('product');
	}

	public function set_product_stock($product_id, $quantity)
	{
		if (!isset($quantity) || !isset($product_id)) {
			return;
		}

		$query = $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = '" . (int)$quantity . "' WHERE product_id = '" . (int)$product_id . "'");

		$this->cache->delete('product');
	}

	public function set_product_stock_status($product_id, $stock_status_id)
	{
		if (!isset($stock_status_id) || !isset($product_id)) {
			return;
		}

		$query = $this->db->query("UPDATE " . DB_PREFIX . "product SET stock_status_id = '" . (int)$stock_status_id . "' WHERE product_id = '" . (int)$product_id . "'");

		$this->cache->delete('product');
	}

	public function add_product_images($product_id, $images)
	{
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int)$product_id . "' ORDER BY sort_order ASC");

		$db_images = $query->rows;

		foreach ($images as $image) {
			// Check if image already exists
			foreach ($db_images as $db_image) {
				if ($db_image['image'] == $image) {
					continue 2;
				}
			}

			$this->db->query("INSERT INTO " . DB_PREFIX . "product_image SET product_id = '" . (int)$product_id . "', image = '" . $this->db->escape($image) . "', sort_order = '" . 0 . "'");
		}

		$this->cache->delete('product');
	}

	public function update_product_timestamp($product_id, $timestamp){
		$query = $this->db->query("UPDATE " . DB_PREFIX . "erply_oc_product SET timestamp = '" . (int)$timestamp . "' WHERE oc_product_id = '" . (int)$product_id . "'");
	}

	public function update_category_timestamp($category_id, $timestamp){
		$query = $this->db->query("UPDATE " . DB_PREFIX . "erply_oc_category SET timestamp = '" . (int)$timestamp . "' WHERE oc_category_id = '" . (int)$category_id . "'");
	}

}

<?php
class OcErplyHelper
{
	private static $file_prefix = "ERPLY_IMAGE";

	public function __construct()
	{

	}

	public function erply_to_oc_category($erply_category, $parent_id)
	{
		$oc_category = array(
			'parent_id' => isset($parent_id) ? $parent_id : 0,
			'top' => isset($parent_id) ? 0 : 1,
			'sort_order' => $erply_category['positionNo'],
			'status' => $erply_category['showInWebshop'],
			'category_store' => array(0),
			'category_layout' => array(),
			'category_seo_url' => array(array(), array())
		);

		if (isset($erply_category['subGroups']) && !empty($erply_category['subGroups'])) {

			$oc_category['column'] = $this->get_category_column_count(sizeof($erply_category['subGroups']));

		} else {

			$oc_category['column'] = 1;
		}

		// TODO: language support
		$oc_category['category_description'] = array(
			1 => array(
				'name' => $erply_category['name'],
				'description' => '',
				'meta_title' => $erply_category['name'],
				'meta_description' => '',
				'meta_keyword' => ''
			),
			2 => array(
				'name' => $erply_category['name'],
				'description' => '',
				'meta_title' => $erply_category['name'],
				'meta_description' => '',
				'meta_keyword' => ''
			)
		);

		return $oc_category;
	}

	public function erply_to_oc_product($erply_product, $oc_category_id)
	{
		$oc_product = array(
			'model' => $erply_product['code'],
			'sku' => $erply_product['unitName'],
			'price' => $erply_product['price'],
			'tax_class_id' => 9,
			'quantity' => (int)$erply_product['warehouses']['1']['free'],
			'date_available' => date('Y-m-d'),
			'stock_status_id' => 5,
			'minimum' => 1,
			'subtract' => 0,
			'shipping' => 1,
			'length_class_id' => 1,
			'weight_class_id' => 1,
			'status' => 1,
			'sort_order' => 1,
			'points' => 0,
			'manufacturer_id' => 0,
			'product_category' => array($oc_category_id),
			'product_store' => array(0),
			'product_layout' => array(),
			'upc' => '',
			'ean' => '',
			'jan' => '',
			'isbn' => '',
			'mpn' => '',
			'location' => '',
			'weight' => '',
			'length' => '',
			'width' => '',
			'height' => '',
			'image' => ''
		);

		$oc_product['product_image'] = array();

		// TODO: language support
		$oc_product['product_description'] = array(
			1 => array(
				'name' => $erply_product['name'],
				'description' => '',
				'meta_title' => $erply_product['name'],
				'meta_description' => '',
				'meta_keyword' => '',
				'tag' => ''
			),
			2 => array(
				'name' => $erply_product['name'],
				'description' => '',
				'meta_title' => $erply_product['name'],
				'meta_description' => '',
				'meta_keyword' => '',
				'tag' => ''
			)
		);

		return $oc_product;
	}

	public function download_product_images($erply_product)
	{
		if (!isset($erply_product['images'])) {
			return array();
		}

		$images = array();

		$erply_dir = 'erply_images';
		$fs_base_dir = DIR_IMAGE . 'catalog/' . $erply_dir;
		$db_base_dir = 'catalog/' . $erply_dir;

		if (!file_exists($fs_base_dir)) {
			mkdir($fs_base_dir, 0777, true);
		}

		foreach ($erply_product['images'] as $erply_image) {
			$url = $erply_image['fullURL'];

			$file_extension = pathinfo(parse_url($url)['path'], PATHINFO_EXTENSION);
			$file_name = self::$file_prefix . '_' . $erply_product['productID'] . '_' . $erply_image['pictureID'] . '.' . $file_extension;
			$file_local_path = $fs_base_dir . '/' . $file_name;

			if (!file_exists($file_name)) {
				$content = file_get_contents($url);
				file_put_contents($file_local_path, $content);
				$images[] = ($db_base_dir . '/' . $file_name);
			}
		}

		return $images;
	}

	public function get_category_column_count($sub_category_count)
	{
		if ($sub_category_count > 30) {
			$columns = 4;
		} else if ($sub_category_count > 20) {
			$columns = 3;
		} else if ($sub_category_count > 10) {
			$columns = 2;
		}
		
		// TODO: add logging support to this helper class
		//$this->debug("@get_category_column_count for " . $sub_category_count . " categories returning " . (isset($columns) ? $columns : 1) . " columns");

		return isset($columns) ? $columns : 1;
	}
}
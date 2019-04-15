<?php
class ControllerExtensionModuleErply extends Controller
{
	/**
	 * property named $error is defined to put errors
	 * @var array
	 */
	private $error = array();
	private $erply;
	private $erplyHelper;

	private static $sync_lock = 0;
	private static $debug_enabled = 0;

	public function install()
	{
		$this->load->model('extension/module/erply');
		$this->load->model('setting/setting');
		$this->load->model('setting/extension');

		$this->model_extension_module_erply->install();
	}

	public function uninstall()
	{
		$this->load->model('extension/module/erply');
		$this->load->model('setting/setting');
		$this->load->model('setting/extension');

		$this->model_extension_module_erply->uninstall();
	}

	public function index()
	{
		$this->load->language('extension/module/erply');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_erply', $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		$this->handle_errors($data);
		$this->add_texts($data);
		$this->add_breadcrumbs($data);
		$this->populate_module_settings($data);
		$this->add_button_links($data);
		$this->load_page_parts($data);

		$this->response->setOutput($this->load->view('extension/module/erply', $data));
	}

	private function load_page_parts(&$data)
	{
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
	}

	private function add_button_links(&$data)
	{
		$data['action'] = $this->url->link('extension/module/erply', 'user_token=' . $this->session->data['user_token'], true);
		$data['sync_url'] = $this->url->link('extension/module/erply/sync', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
	}

	private function populate_module_settings(&$data)
	{
		if (isset($this->request->post['module_erply_status'])) {
			$data['module_erply_status'] = $this->request->post['module_erply_status'];
		} else {
			$data['module_erply_status'] = $this->config->get('module_erply_status');
		}

		if (isset($this->request->post['module_erply_user'])) {
			$data['module_erply_user'] = $this->request->post['module_erply_user'];
		} else {
			$data['module_erply_user'] = $this->config->get('module_erply_user');
		}

		if (isset($this->request->post['module_erply_password'])) {
			$data['module_erply_password'] = $this->request->post['module_erply_password'];
		}

		if (isset($this->request->post['module_erply_client_code'])) {
			$data['module_erply_client_code'] = $this->request->post['module_erply_client_code'];
		} else {
			$data['module_erply_client_code'] = $this->config->get('module_erply_client_code');
		}
	}

	private function add_breadcrumbs(&$data)
	{
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/erply', 'user_token=' . $this->session->data['user_token'], true)
		);
	}

	private function add_texts(&$data)
	{
		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');

		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_user'] = $this->language->get('entry_user');
		$data['entry_password'] = $this->language->get('entry_password');
		$data['entry_client_code'] = $this->language->get('entry_client_code');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		$data['button_add_module'] = $this->language->get('button_add_module');
		$data['button_remove'] = $this->language->get('button_remove');
	}

	private function handle_errors(&$data)
	{
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}
		if (isset($this->error['user'])) {
			$data['error_user'] = $this->error['user'];
		} else {
			$data['error_user'] = '';
		}
		if (isset($this->error['password'])) {
			$data['error_password'] = $this->error['password'];
		} else {
			$data['error_password'] = '';
		}
		if (isset($this->error['client_code'])) {
			$data['error_client_code'] = $this->error['code'];
		} else {
			$data['error_client_code'] = '';
		}
	}

	public function sync()
	{
		if ($this->sync_lock == 0) {
			$this->sync_lock = 1;
		} else {
			die();
		}

		try {
			$this->init_sync();

			$this->delete_removed_categories();
			$this->delete_removed_products();

			$this->sync_categories_and_products();
		} finally {

			$this->sync_lock = 0;
		}
	}

	private function sync_categories_and_products()
	{
		$categories = $this->erply->get_categories();

		foreach ($categories as $category) {
			$this->sync_category_recursive($category);
		}
	}

	private function sync_category_recursive($erply_category, $parent_oc_category_id = null)
	{
		$this->debug("@sync_category_recursive handling category " . $erply_category['name'] . " with parent " . $parent_oc_category_id);

		$mapping = $this->model_extension_module_erply->find_category_mapping_by_erply_id($erply_category['productGroupID']);

		// check if mapping exists and remove it if existing mapping is invalid
		if ($mapping) {
			$oc_category = $this->erplyHelper->erply_to_oc_category($erply_category, $parent_oc_category_id);
			$oc_db_category = $this->model_catalog_category->getCategory($mapping['oc_category_id']);

			if ($oc_db_category) {
				$category_id = $mapping['oc_category_id'];
			} else {
				// category removed from OC but present in Erply
				$this->model_extension_module_erply->remove_category_mapping($erply_category['productGroupID']);
			}
		}

		if (!isset($category_id)) {
			// create new category with mapping
			$oc_category = $this->erplyHelper->erply_to_oc_category($erply_category, $parent_oc_category_id);
			$category_id = $this->model_catalog_category->addCategory($oc_category);
			$this->model_extension_module_erply->add_category_mapping($category_id, $erply_category['productGroupID'], (int)$erply_category['lastModified']);
			$mapping = $this->model_extension_module_erply->find_category_mapping_by_erply_id($erply_category['productGroupID']);

			$this->debug("@sync_category_recursive created new category " . $erply_category['name'] . " with id " . $category_id);
		}

		// sync products and sub-categories
		if (isset($category_id)) {
			// update category data if needed
			if ((int)$mapping['timestamp'] < (int)$erply_category['lastModified']) {
				$this->model_catalog_category->editCategory($category_id, $oc_category);
				$this->model_extension_module_erply->update_category_timestamp($category_id, (int)$erply_category['lastModified']);
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

		$erply_response = $this->erply->get_products($offset, 100, 1, 1, null, $erply_category_id);

		if ($erply_response['status']['responseStatus'] == 'error') {
			throw new Exception("Error in Erply response");
		}

		$erply_products = $erply_response['records'];

		while ($offset < $erply_response['status']['recordsTotal']) {
			$offset += 100;
			$erply_response = $this->erply->get_products($offset, 100, 1, 1, null, $erply_category_id);

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
		$mapping = $this->model_extension_module_erply->find_product_mapping_by_erply_id($erply_product['productID']);

		$oc_product = $this->erplyHelper->erply_to_oc_product($erply_product, $oc_category_id);

		if ($mapping) {
			$oc_db_product = $this->model_catalog_product->getProduct($mapping['oc_product_id']);

			if ($oc_db_product) {
				// TODO: replace this with something compatible with OC-s editProduct function

				$this->debug("@sync_product mapping for product " . $erply_product['productID'] . " already exists, updating product!");

				if ((int)$mapping['timestamp'] == (int)$erply_product['lastModified']) {
					$this->debug("@sync_product product " . $oc_db_product['product_id'] . " already up to date!");
					return;
				}

				if ((int)$erply_product['displayedInWebshop'] != (int)$oc_db_product['status']) {
					$this->debug("@sync_product updating product " . $oc_db_product['product_id'] . " status from " . (int)$oc_db_product['status'] . " to " . (int)$erply_product['displayedInWebshop']);
					$this->model_extension_module_erply->set_product_status($oc_db_product['product_id'], (int)$erply_product['displayedInWebshop']);
				}

				$oc_product_categories = $this->model_catalog_product->getProductCategories($oc_db_product['product_id']);
				if (!in_array($oc_category_id, $oc_product_categories)) {
					// TODO: support multiple categories
					$this->debug("@sync_product updating product " . $oc_db_product['product_id'] . " categories from " . implode(",", $oc_product_categories) . " to " . $oc_category_id);
					$this->model_extension_module_erply->set_product_category($oc_db_product['product_id'], $oc_category_id);
				}

				if ($oc_db_product['price'] != $oc_product['price']) {
					$this->debug("@sync_product updating product " . $oc_db_product['product_id'] . " price from " . $oc_db_product['price'] . " to " . $oc_product['price']);
					$this->model_extension_module_erply->set_product_price($oc_db_product['product_id'], $oc_product['price']);
				}

				if ($oc_db_product['quantity'] != $oc_product['quantity']) {
					$this->debug("@sync_product updating product " . $oc_db_product['product_id'] . " quantity from " . $oc_db_product['quantity'] . " to " . $oc_product['quantity']);
					$this->model_extension_module_erply->set_product_stock($oc_db_product['product_id'], $oc_product['quantity']);
				}

				if ($oc_db_product['stock_status_id'] != $oc_product['stock_status_id']) {
					$this->debug("@sync_product updating product " . $oc_db_product['product_id'] . " stock_status_id from " . $oc_db_product['stock_status_id'] . " to " . $oc_product['stock_status_id']);
					$this->model_extension_module_erply->set_product_stock_status($oc_db_product['product_id'], $oc_product['stock_status_id']);
				}

				$this->add_product_images($erply_product, $oc_db_product['product_id']);

				$this->model_extension_module_erply->update_product_timestamp($oc_db_product['product_id'], (int)$erply_product['lastModified']);

				return;
			} else {
				$this->debug("@sync_product invalid mapping for product " . $erply_product['productID'] . ", recreating!");
				$this->model_extension_module_erply->remove_product_mapping($erply_product['productID']);
			}
		} else {
			if ((int)$erply_product['displayedInWebshop'] == 0) {
				return;
			}
		}

		$this->debug("@sync_product creating product " . $erply_product['name'] . " with model " . $erply_product['code']);

		$oc_product_id = $this->model_catalog_product->addProduct($oc_product);

		$this->model_extension_module_erply->add_product_mapping($oc_product_id, $erply_product['productID'], (int)$erply_product['lastModified']);
		$this->add_product_images($erply_product, $oc_product_id);
	}

	private function add_product_images($erply_product, $oc_product_id)
	{
		$images = $this->erplyHelper->download_product_images($erply_product);

		if (!isset($images)) {
			return;
		}

		$this->debug("@add_product_images adding " . sizeof($images) . " images for product " . $oc_product_id);

		$this->model_extension_module_erply->add_product_images($oc_product_id, $images);
	}

	private function check_permissions()
	{
		if (!$this->is_cli() && !$this->user->hasPermission('modify', 'extension/module/erply')) {
			$this->error['warning'] = $this->language->get('error_permission');
			// TODO: error handling
			die();
		}
	}

	/**
	 * validate function validates the values of the post and also the permission
	 * @return boolean return true if any of the index of $error contains value
	 */
	protected function validate()
	{
		if (!$this->user->hasPermission('modify', 'extension/module/erply')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->request->post['module_erply_status']) {
			if (!$this->request->post['module_erply_user']) {
				$this->error['user'] = $this->language->get('error_user');
			}
			if (!$this->request->post['module_erply_password']) {
				$this->error['password'] = $this->language->get('error_password');
			}
			if (!$this->request->post['module_erply_client_code']) {
				$this->error['client_code'] = $this->language->get('error_client_code');
			}
		}

		return !$this->error;
	}

	private function init_sync()
	{
		$this->load->model('extension/module/erply');
		$this->load->model('setting/setting');
		$this->load->model('catalog/category');
		$this->load->model('catalog/product');

		$this->ensure_module_enabled();
		$this->check_permissions();

		$this->load->library('OcErplyHelper');
		$this->erplyHelper = new OcErplyHelper();

		// Erply
		$this->load->library('ErplyApi');

		$username = $this->config->get('module_erply_user');
		$password = $this->config->get('module_erply_password');
		$client_code = $this->config->get('module_erply_client_code');
		$url = 'https://' . $client_code . '.erply.com/api/';

		$this->erply = new ErplyApi($url, $client_code, $username, $password, null);
	}

	private function delete_removed_categories()
	{
		$remote_ids = $this->erply->get_category_ids();
		$erply_mappings = $this->model_extension_module_erply->get_category_mappings();
		$tracked_erply_ids = array();
		$tracked_oc_ids = array();
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
				$this->log->write("@delete_removed_categories missing erply category " . $tracked_erply_id . ", disabling OC category " . $oc_id);
				$this->model_extension_module_erply->set_category_status($oc_id, 0);
			} else {
				// check if tracked category is removed locally
				$tracked_oc_category = $this->model_catalog_category->getCategory($oc_id);
				if (!isset($tracked_oc_category) || !isset($tracked_oc_category['category_id'])) {
					$this->log->write("@delete_removed_categories removing mapping for deleted category " . $tracked_erply_id);
					$this->model_extension_module_erply->remove_category_mapping($tracked_erply_id);
				}
			}
		}
	}

	private function delete_removed_products()
	{
		$erply_mappings = $this->model_extension_module_erply->get_product_mappings();
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
			$erply_response = $this->erply->get_products_simple(0, 1000, $ids_batch);

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
				$this->log->write("@delete_removed_products missing erply product " . $tracked_erply_id . ", disabling OC product " . $oc_id);
				$this->model_extension_module_erply->set_product_status($oc_id, 0);
			} else {
				// check if tracked product is removed locally
				$tracked_oc_product = $this->model_catalog_product->getProduct($oc_id);
				if (!isset($tracked_oc_product) || !isset($tracked_oc_product['product_id'])) {
					$this->log->write("@delete_removed_products removing mapping for deleted product " . $oc_id);
					$this->model_extension_module_erply->remove_product_mapping($tracked_erply_id);
				}
			}
		}
	}

	private function ensure_module_enabled()
	{
		if (!$this->config->get('module_erply_status')) {
			// TODO: error handling
			die();
		}
	}

	private function is_cli()
	{
		if (defined('STDIN') && php_sapi_name() === 'cli' && empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0 && !array_key_exists('REQUEST_METHOD', $_SERVER)) {
			return true;
		}

		return false;
	}

	private function debug($message)
	{
		if (self::$debug_enabled) {
			$this->log->write($message);
		}
	}
}

<?php
class ControllerExtensionModuleErply extends Controller {
    /**
     * property named $error is defined to put errors
     * @var array
     */
	private static $file_prefix = "ERPLY_IMAGE";
    private $error = array();
	private $erply;
	
	private static $sync_lock = 0;
	private static $debug_enabled = 1;
	
	public function install() {
		$this->load->model('extension/module/erply');
		$this->load->model('setting/setting');
		$this->load->model('setting/extension');

		$this->model_extension_module_erply->install();
	}

	public function uninstall() {
		$this->load->model('extension/module/erply');
		$this->load->model('setting/setting');
		$this->load->model('setting/extension');

		$this->model_extension_module_erply->uninstall();
	}
	
    public function index() {
        $this->load->language('extension/module/erply');
        $this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');
		
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_erply', $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }
		
		/**
		* Language
		*/
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
		
		/**
		* Error messages
		*/
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
		
		/**
		* Breadcrumbs
		*/
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

		/**
		* Button/action URL-s
		*/
		$data['action'] = $this->url->link('extension/module/erply', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
		
		$data['sync_url'] = $this->url->link('extension/module/erply/sync', 'user_token=' . $this->session->data['user_token'], true);
		$data['clean_url'] = $this->url->link('extension/module/erply/sync', 'user_token=' . $this->session->data['user_token'], true);

		/**
		* Erply auth params update
		*/
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

		/**
		* OC templates
		*/
        $data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/erply', $data));
    }
	
	public function sync(){
		$this->load->model('extension/module/erply');
		$this->load->model('setting/setting');
		$this->load->model('catalog/category');
		$this->load->model('catalog/product');
		
		$this->check_permissions();
		$this->ensure_module_enabled();
		
		try {
			if($this->sync_lock == 0){
				$this->sync_lock = 1;
				
				$this->init_erply(
					$this->config->get('module_erply_user'),
					$this->config->get('module_erply_password'),
					$this->config->get('module_erply_client_code')
				);
				
				$categories_response = json_decode($this->erply->sendRequest('getProductGroups', array('displayedInWebshop' => 1)), true);
				$erply_categories = $categories_response['records'];
				
				foreach ($erply_categories as $erply_category){
					$this->sync_category($erply_category, null);
				}
			}
		} finally {
			$this->sync_lock = 0;
		}
	}
	
	private function ensure_module_enabled(){
		if(!$this->config->get('module_erply_status')){
			// TODO: error handling
			die();
		}
	}
	
	private function sync_category($erply_category, $parent_oc_category_id){
		$this->debug("@sync_category handling category " . $erply_category['name'] . " with parent " . $parent_oc_category_id);
		
		$mapping = $this->model_extension_module_erply->find_category_mapping_by_erply_id($erply_category['productGroupID']);
		
		// check if mapping exists and remove it if existing mapping is invalid
		if($mapping){
			$oc_db_category = $this->model_catalog_category->getCategory($mapping['oc_category_id']);
			
			if($oc_db_category){
				$category_id = $mapping['oc_category_id'];
			} else {
				$this->model_extension_module_erply->remove_category_mapping($erply_category['productGroupID']);
			}
		}
		
		// create new category with mapping
		if(!isset($category_id)){
			$oc_category = $this->erply_to_oc_category($erply_category, $parent_oc_category_id);
			$category_id = $this->model_catalog_category->addCategory($oc_category);
			
			$this->model_extension_module_erply->add_category_mapping($category_id, $erply_category['productGroupID'], $erply_category['lastModified']);
			
			$this->debug("@sync_category created new category " . $erply_category['name'] . " with id " . $category_id);
		}
		
		// sync products and sub-categories
		if(isset($category_id)){
			$this->sync_products($erply_category['productGroupID'], $category_id);
			
			if(!empty($erply_category['subGroups'])) {
				foreach ($erply_category['subGroups'] as $erply_sub_category){
					$this->sync_category($erply_sub_category, $category_id);
				}
			}
		} else {
			// TODO: oc category id missing from mapping or no mapping and oc category creation failed
		}
	}
	
	private function erply_to_oc_category($erply_category, $parent_id){
		$oc_category = array(
			'parent_id' => isset($parent_id) ? $parent_id : 0,
			'top' => isset($parent_id) ? 0 : 1,
			'sort_order' => $erply_category['positionNo'],
			'status' => $erply_category['showInWebshop'],
			'category_store' => array(0),
			'category_layout' => array(),
			'category_seo_url' => array(array(), array())
		);
		
		if(isset($erply_category['subGroups']) && !empty($erply_category['subGroups'])){
			$oc_category['column'] = $this->get_category_column_count(sizeof($erply_category['subGroups']));
			
		} else {
			
			$oc_category['column'] = 1;
		}

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
	
	private function get_category_column_count($sub_category_count){			
		if($sub_category_count > 30){
			$columns = 4;
		} else if($sub_category_count > 20){
			$columns = 3;
		} else if($sub_category_count > 10){
			$columns = 2;
		}
		
		$this->debug("@get_category_column_count for " . $sub_category_count . " categories returning " . (isset($columns) ? $columns : 1) . " columns");
		
		return isset($columns) ? $columns : 1;
	}
	
	/**
	* Returns number of products retrieved from Erply (for setting category columns)
	*/
	private function sync_products($erply_category_id, $oc_category_id){
		$this->debug("@sync_products creating products for category with id " . $oc_category_id);
		
		$products_response = json_decode(
			$this->erply->sendRequest('getProducts', array(
				'groupID' => $erply_category_id,
				'displayedInWebshop' => 1,
				'getStockInfo' => 1,
				'active' => 1,
				'getPriceListPrices' => 1,
				'type' => 'PRODUCT,BUNDLE,ASSEMBLY' // ensures no matrix product parents in response
			)
		), true);
		
		$erply_products = $products_response['records'];
		
		foreach ($erply_products as $erply_product){
			$mapping = $this->model_extension_module_erply->find_product_mapping_by_erply_id($erply_product['productID']);
			
			$oc_product = $this->erply_to_oc_product($erply_product, $oc_category_id);
			
			if($mapping){
				$oc_db_product = $this->model_catalog_product->getProduct($mapping['oc_product_id']);
				
				if($oc_db_product){
					$this->debug("@sync_products mapping for product " . $erply_product['productID'] . " already exists, updating product!");
					
					if($oc_db_product['price'] != $oc_product['price']){
						$this->debug("@sync_products updating product " . $oc_db_product['product_id'] . " price from " . $oc_db_product['price'] . " to " . $oc_product['price']);
						
						$this->model_extension_module_erply->set_product_price($oc_db_product['product_id'], $oc_product['price']);
					}
					
					if($oc_db_product['quantity'] != $oc_product['quantity']){
						$this->debug("@sync_products updating product " . $oc_db_product['product_id'] . " quantity from " . $oc_db_product['quantity'] . " to " . $oc_product['quantity']);
						
						$this->model_extension_module_erply->set_product_stock($oc_db_product['product_id'], $oc_product['quantity']);
					}
					
					if($oc_db_product['stock_status_id'] != $oc_product['stock_status_id']){
						$this->debug("@sync_products updating product " . $oc_db_product['product_id'] . " stock_status_id from " . $oc_db_product['stock_status_id'] . " to " . $oc_product['stock_status_id']);
						
						$this->model_extension_module_erply->set_product_stock_status($oc_db_product['product_id'], $oc_product['stock_status_id']);
					}
					
					$this->add_product_images($erply_product, $oc_db_product['product_id']);
					
					continue;
				} else {
					$this->debug("@sync_products invalid mapping for product " . $erply_product['productID'] . ", recreating!");
					$this->model_extension_module_erply->remove_product_mapping($erply_category['productID']);
				}
			}
			
			$this->debug("@sync_products creating product " . $erply_product['name'] . " with model " . $erply_product['code']);
			
			
			$oc_product_id = $this->model_catalog_product->addProduct($oc_product);
			
			$this->model_extension_module_erply->add_product_mapping($oc_product_id, $erply_product['productID'], $erply_product['lastModified']);
			
			$this->add_product_images($erply_product, $oc_product_id);
		}
		
		return sizeof($erply_products);
	}
	
	private function add_product_images($erply_product, $oc_product_id){
		$images = $this->download_product_images($erply_product);
		
		if(!isset($images)){
			return;
		}
		
		$this->debug("@add_product_images adding " . sizeof($images) . " images for product " . $oc_product_id);
		
		$this->model_extension_module_erply->add_product_images($oc_product_id, $images);
	}
	
	private function erply_to_oc_product($erply_product, $oc_category_id){
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
	
	private function download_product_images($erply_product){
		if(!isset($erply_product['images'])){
			return array();
		}
		
		$images = array();
		
		$erply_dir = 'erply_images';
		$fs_base_dir = DIR_IMAGE . 'catalog/' . $erply_dir;
		$db_base_dir = 'catalog/' . $erply_dir;
		
		if (!file_exists($fs_base_dir)) {
			mkdir($fs_base_dir, 0777, true);
		}
				
		foreach ($erply_product['images'] as $erply_image){
			$url = $erply_image['fullURL'];
			
			$file_extension = pathinfo(parse_url($url)['path'], PATHINFO_EXTENSION);
			$file_name = self::$file_prefix . '_' . $erply_product['productID'] . '_' . $erply_image['pictureID'] . '.' . $file_extension;
			$file_local_path = $fs_base_dir . '/' . $file_name;
						
			if(!file_exists ($file_name)){
				$content = file_get_contents($url);
				file_put_contents($file_local_path, $content);
				$images[] = ($db_base_dir . '/' . $file_name);
			}
		}
		
		return $images;
	}
		
	private function init_erply($user, $password, $client_code){
		session_start();
		$this->load->library('EAPI');
		
		$this->erply = new EAPI();

		// Configuration settings
		$this->erply->username = $user;
		$this->erply->password = $password;
		$this->erply->clientCode = $client_code;
		$this->erply->url = 'https://'.$this->erply->clientCode.'.erply.com/api/';
	}
	
	private function check_permissions() {
		if (!$this->user->hasPermission('modify', 'extension/module/erply')) {
			$this->error['warning'] = $this->language->get('error_permission');
			// TODO: error handling
			die();
		}
	}
	
    /**
     * validate function validates the values of the post and also the permission
     * @return boolean return true if any of the index of $error contains value
     */
	protected function validate() {
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
	
	private function json_cb(&$item, $key) { 
		if (is_string($item)) $item = mb_encode_numericentity($item, array (0x80, 0xffff, 0, 0xffff), 'UTF-8'); 
	}

	private function my_json_encode($arr){
		array_walk_recursive($arr, array($this, 'json_cb'));
		return mb_decode_numericentity(json_encode($arr), array (0x80, 0xffff, 0, 0xffff), 'UTF-8');
	}
	
	private function debug($message){
		if(self::$debug_enabled){
			$this->log->write($message);
		}
	}
}

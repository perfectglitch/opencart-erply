<?php
class ControllerExtensionModuleErply extends Controller
{
	/**
	 * property named $error is defined to put errors
	 * @var array
	 */
	private $error = array();
	private $erplyService;

	private static $sync_lock = 0;

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

			$this->erplyService->delete_removed_categories();
			$this->erplyService->delete_removed_products();

			$this->erplyService->sync_categories_and_products();
		} finally {

			$this->sync_lock = 0;
		}
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

		// Erply
		$this->load->library('ErplyClient');
		$this->load->library('OcErplyHelper');
		$this->load->library('OcErplyService');

		$username = $this->config->get('module_erply_user');
		$password = $this->config->get('module_erply_password');
		$client_code = $this->config->get('module_erply_client_code');
		$url = 'https://' . $client_code . '.erply.com/api/';

		$erplyClient = new ErplyClient($url, $client_code, $username, $password, null);
		$erplyHelper = new OcErplyHelper();

		$this->erplyService = new OcErplyService(
			$erplyClient,
			$erplyHelper,
			$this->model_catalog_product,
			$this->model_catalog_category,
			$this->model_extension_module_erply
		);
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
}

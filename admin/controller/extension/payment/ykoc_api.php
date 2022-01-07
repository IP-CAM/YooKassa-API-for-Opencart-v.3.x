<?php

class ControllerExtensionPaymentYkocApi extends Controller
{
    private $error = array();

    public function index()
    {
        $this->load->language('extension/payment/ykoc_api');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('catalog/option');
        $this->load->model('localisation/currency');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_ykoc_api', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        // Errors and Warning
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['ykoc_api'])) {
            $data['error_bank'] = $this->error['ykoc_api'];
        } else {
            $data['error_bank'] = array();
        }

        // Breadcrumbs
        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/ykoc_api', 'user_token=' . $this->session->data['user_token'], true)
        );

        // Buttons
        $data['action'] = $this->url->link('extension/payment/ykoc_api', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        // Включить приём онлайн платежей?
        if (isset($this->request->post['payment_ykoc_api_status'])) {
            $data['payment_ykoc_api_status'] = $this->request->post['payment_ykoc_api_status'];
        } else {
            $data['payment_ykoc_api_status'] = $this->config->get('payment_ykoc_api_status');
        }

        // shopId
        if (isset($this->request->post['payment_ykoc_api_shop_id'])) {
            $data['payment_ykoc_api_shop_id'] = $this->request->post['payment_ykoc_api_shop_id'];
        } else {
            $data['payment_ykoc_api_shop_id'] = $this->config->get('payment_ykoc_api_shop_id');
        }

        // Секретный ключ
        if (isset($this->request->post['payment_ykoc_api_sicret_key'])) {
            $data['payment_ykoc_api_sicret_key'] = $this->request->post['payment_ykoc_api_sicret_key'];
        } else {
            $data['payment_ykoc_api_sicret_key'] = $this->config->get('payment_ykoc_api_sicret_key');
        }

        // URL, на который вернется пользователь после подтверждения или отмены платежа на веб-странице.
        if (isset($this->request->post['payment_ykoc_api_return_url'])) {
            $data['payment_ykoc_api_return_url'] = $this->request->post['payment_ykoc_api_return_url'];
        } else {
            $data['payment_ykoc_api_return_url'] = $this->config->get('payment_ykoc_api_return_url');
        }

        // Отображаемое название типа платежа
        if (isset($this->request->post['payment_ykoc_api_heading_payment'])) {
            $data['payment_ykoc_api_heading_payment'] = $this->request->post['payment_ykoc_api_heading_payment'];
        } else {
            $data['payment_ykoc_api_heading_payment'] = $this->config->get('payment_ykoc_api_heading_payment');
        }

        // Условия оформления заказа
        $this->load->model('catalog/information');

		$data['informations'] = $this->model_catalog_information->getInformations();

        if (isset($this->request->post['payment_ykoc_api_terms'])) {
            $data['payment_ykoc_api_terms'] = $this->request->post['payment_ykoc_api_terms'];
        } else {
            $data['payment_ykoc_api_terms'] = $this->config->get('payment_ykoc_api_terms');
        }

        // Минимальная сумма заказа для оплаты
        if (isset($this->request->post['payment_ykoc_api_total'])) {
            $data['payment_ykoc_api_total'] = $this->request->post['payment_ykoc_api_total'];
        } else {
            $data['payment_ykoc_api_total'] = $this->config->get('payment_ykoc_api_total');
        }

        // Logo способа оплаты
        $this->load->model('tool/image');

        if (isset($this->request->post['payment_ykoc_api_logo']) && is_file(DIR_IMAGE . $this->request->post['payment_ykoc_api_logo'])) {
			$data['payment_ykoc_api_logo'] = $this->model_tool_image->resize($this->request->post['payment_ykoc_api_logo'], 100, 100);
		} elseif ($this->config->get('payment_ykoc_api_logo') && is_file(DIR_IMAGE . $this->config->get('payment_ykoc_api_logo'))) {
			$data['payment_ykoc_api_logo'] = $this->model_tool_image->resize($this->config->get('payment_ykoc_api_logo'), 100, 100);
		} else {
			$data['payment_ykoc_api_logo'] = $this->model_tool_image->resize('payment/ykoc_api/payment_logo.png', 100, 100);
		}
		
        // Порядок сортировки
        if (isset($this->request->post['payment_ykoc_api_sort_order'])) {
            $data['payment_ykoc_api_sort_order'] = $this->request->post['payment_ykoc_api_sort_order'];
        } else {
            $data['payment_ykoc_api_sort_order'] = $this->config->get('payment_ykoc_api_sort_order');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/ykoc_api', $data));
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/ykoc_api')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (empty($this->request->post['payment_ykoc_api_shop_id'])) {
            $this->error['warning'] = $this->language->get('error_shop_id');
        }

        if (empty($this->request->post['payment_ykoc_api_sicret_key'])) {
            $this->error['warning'] = $this->language->get('error_sicret_key');
        }

        if (empty($this->request->post['payment_ykoc_api_return_url'])) {
            $this->error['warning'] = $this->language->get('error_return_url');
        }

        return !$this->error;
    }
}

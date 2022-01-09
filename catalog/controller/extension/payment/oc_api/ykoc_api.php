<?php

class ControllerExtensionPaymentOcApiYkocApi extends Controller
{
    const MODULE_NAME = 'ykoc_api';

    public function index()
    {
        try {
            $data = [];

            $this->load->model('tool/image');
            $this->load->model('extension/payment/ykoc_api');

            $data['thumb'] = $this->model_tool_image->resize($this->config->get('payment_ykoc_api_logo'), 150, 40);

            // Action
            if (isset($this->session->data['order_id'])) {
                $data['action'] = $this->model_extension_payment_ykoc_api->getAction();
            } else {
                $data['action'] = '';
            }

            return $data;
        } catch (Throwable $error) {
            $this->log->write('[YooKassa] ' . "\n\t" . 'Message - ' . $error->getMessage() . "\n\t" . 'Code - ' . $error->getCode() . "\n\t" . 'File - ' . $error->getFile() . "\n\t" . 'Line - ' . $error->getLine(), true);

            // Response status
            $data['status']['code'] = 10; // Error
            $data['status']['message'] = $error->getMessage();
            return $data;
        }
    }
    
}

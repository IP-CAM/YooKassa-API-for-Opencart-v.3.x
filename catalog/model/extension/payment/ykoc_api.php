<?php
// Autoload yookassa sdk
require_once __DIR__ . DIRECTORY_SEPARATOR . 'ykoc_api' . DIRECTORY_SEPARATOR . 'autoload.php';

class ModelExtensionPaymentYkocApi extends Model
{
	const MODULE_NAME = 'ykoc_api';

	public function getMethod($address, $total)
	{
		$this->load->language('extension/payment/ykoc_api');

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_ykoc_api_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

		if ($this->config->get('payment_ykoc_api_total') > 0 && $this->config->get('payment_ykoc_api_total') > $total) {
			$status = false;
		} elseif (!$this->config->get('payment_ykoc_api_geo_zone_id')) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}

		$method_data = array();

		if ($status) {
			$method_data = array(
				'code'			=> 'ykoc_api',
				'title'			=> $this->config->get('payment_ykoc_api_heading_payment') ? $this->config->get('payment_ykoc_api_heading_payment') : $this->language->get('text_title_method'),
				'terms'			=> $this->config->get('payment_ykoc_api_terms') ? $this->config->get('payment_ykoc_api_terms') : $this->config->get('config_checkout_id'),
				'sort_order'	=> $this->config->get('payment_ykoc_api_sort_order')
			);
		}

		return $method_data;
	}

	public function getAction()
	{
		try {
			$action = '';

			if (isset($this->session->data['order_id'])) {

				$total = $this->load->controller('extension/module/oc_api/checkout/total/getTotal');

				if (isset($this->session->data['total_payment'])) {

					if ($this->session->data['total_payment'] != $total) {

						$this->session->data['total_payment'] = $total;

						unset($this->session->data['action']);

						$action = $this->createPaymentUrl($total);

						$this->session->data['action'] = $action;

						return $action;
					} else {
						return $this->session->data['action'];
					}
				} else {
					$this->session->data['total_payment'] = $total;

					unset($this->session->data['action']);

					$action = $this->createPaymentUrl($total);

					$this->session->data['action'] = $action;

					return $action;
				}
			} else {
				return $action;
			}
		} catch (Throwable $error) {

			$this->log->write('[YooKassa] ' . "\n\t" . 'Message - ' . $error->getMessage() . "\n\t" . 'Code - ' . $error->getCode() . "\n\t" . 'File - ' . $error->getFile() . "\n\t" . 'Line - ' . $error->getLine(), true);

			// Response status
			$data['status']['code'] = 10; // Error
			$data['status']['message'] = $error->getMessage();

			return $data;
		}
	}

	private function createPaymentUrl($total)
	{

		$items = [];
		$customer = $this->session->data['shipping_address'];

		$productItems 	= $this->getProductCart();
		foreach ($productItems as $product) {
			array_push($items, $product);
		}

		$serviceItems 	= $this->getServices();
		foreach ($serviceItems as $service) {
			array_push($items, $service);
		}

		$response = array(
			"amount" 		=> array(
				"value" 		=> (string)number_format($total, 2, '.', ''),
				"currency" 		=> $this->session->data['currency'],
			),
			"confirmation" 	=> array(
				"type" 			=> "redirect",
				"return_url" 	=> $this->config->get('payment_ykoc_api_return_url')
			),
			'description' => 'Заказ №' . $this->session->data['order_id'],
			"receipt" 		=> array(
				"customer" 	=> array(
					"full_name" => $customer['firstname'] . ' ' . $customer['lastname'],
					"phone" 	=> $customer['telephone'],
				),
				"items" 	=> $items,
			)
		);

		$idempotenceKey = uniqid('', true);

		$client = new \YooKassa\Client();
		$client->setAuth($this->config->get('payment_ykoc_api_shop_id'), $this->config->get('payment_ykoc_api_sicret_key'));
		$response = $client->createPayment($response, $idempotenceKey);

		return $response->getConfirmation()->getConfirmationUrl();
	}

	private function getProductCart()
	{

		$products = [];

		foreach ($this->cart->getProducts() as $product) {

			$option = (count($product['option']) > 0) ? ' (' . $product['option'][0]['name'] . ' ' . $product['option'][0]['value'] . ')' : '';

			$products[] = array(
				"description" => html_entity_decode($product['name'] . $option, ENT_QUOTES, 'UTF-8'),
				"quantity" => $product['quantity'],
				"amount" => array(
					"value" => (string)number_format($product['total'] / $product['quantity'], 2, '.', ''),
					"currency" => $this->session->data['currency']
				),
				"vat_code" => "1", // Без НДС
				"payment_mode" => "full_prepayment", // Полная предоплата
				"payment_subject" => "commodity" // Товар
			);
		}
		return $products;
	}

	private function getServices()
	{
		$services = [];

		$totals = $this->load->controller('extension/module/oc_api/checkout/total/getTotals');

		foreach ($totals as $total) {

			if ($total['code'] === 'shipping') {
				$shipping = array(
					"description" 		=> html_entity_decode($total['title'], ENT_QUOTES, 'UTF-8'),
					"quantity" 			=> '1',
					"amount" 			=> array(
						"value" 			=> (string)number_format($total['value'], 2, '.', ''),
						"currency" 			=> $this->session->data['currency'] ? $this->session->data['currency'] : 'RUB'
					),
					"vat_code" 			=> "1", // Без НДС
					"payment_mode" 		=> "full_prepayment", // Полная предоплата
					"payment_subject" 	=> "service" // Услуга по доставке
				);
				array_push($services, $shipping);
			}

			if ($total['code'] === 'tax') {
				$tax = array(
					"description" 		=> html_entity_decode($total['title'], ENT_QUOTES, 'UTF-8'),
					"quantity" 			=> '1',
					"amount" 			=> array(
						"value" 			=> (string)number_format($total['value'], 2, '.', ''),
						"currency" 			=> $this->session->data['currency'] ? $this->session->data['currency'] : 'RUB'
					),
					"vat_code" 			=> "1", // Без НДС
					"payment_mode" 		=> "full_prepayment", // Полная предоплата
					"payment_subject" 	=> "sales_tax" // Торговый сбор
				);

				array_push($services, $tax);
			}
		}
		return $services;
	}
}

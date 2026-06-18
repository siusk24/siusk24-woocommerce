<?php

namespace Mijora\S24IntApiLib;

use Mijora\S24IntApiLib\Exception\S24ApiException;
use Mijora\S24IntApiLib\Exception\ValidationException;

class API
{
    protected $url = "https://demo.siusk24.lt/api/v1/";
    protected $token;
    private $timeout = 15;
    private $debug_mode;
    private $debug_data = array();

    public function __construct($token = false, $test_mode = false, $api_debug_mode = false)
    {
        if (!$token) {
            throw new S24ApiException("User Token is required");
        }

        $this->token = $token;

        if (!$test_mode) {
            $this->url = "https://www.siusk24.lt/api/v1/";
        }

        if ($api_debug_mode) {
            $this->debug_mode = $api_debug_mode;
        }
    }

    public function setToken($token)
    {
        $this->token = $token;

        return $this;
    }

    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;

        return $this;
    }

    private function callAPI($url, $data = [])
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer " . $this->token
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        if ($data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $transactionTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);

        curl_close($ch);

        if ($this->debug_mode) {
            $this->addToDebug('Token', $this->token);
            $this->addToDebug('Endpoint', $url);
            $this->addToDebug('Method', debug_backtrace()[1]['function'] . '()');
            $this->addToDebug('Data passed', json_encode($data, JSON_PRETTY_PRINT));
            $this->addToDebug('Data returned', json_encode(json_decode($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->addToDebug('Response HTTP code', $httpCode);
            $this->addToDebug('Response time', $transactionTime);
        }

        return $this->handleApiResponse($response, $httpCode);
    }

    private function handleApiResponse($response, $httpCode)
    {
        $respObj = json_decode($response, true);
        if ($httpCode == 200) {
            if (isset($respObj['messages']) && $respObj['messages']) {
                if ($this->debug_mode) {
                    $this->addToDebug('Error from ' . debug_backtrace()[2]['function'] . '()', json_encode($respObj['messages'], JSON_PRETTY_PRINT));
                }
                $this->throwErrors($respObj['messages']);
            }
            return json_decode($response)->result;
        }

        if ($httpCode == 401) {
            // galetu buti tikslesnis exception - Siusk24NotAuthorizedException
            throw new S24ApiException(implode(" \n", json_decode($response)->errors));
        }

        if (isset($respObj['errors']) && $respObj['errors']) {
            if ($this->debug_mode) {
                $this->addToDebug('Error from ' . debug_backtrace()[2]['function'] . '()', json_encode($respObj['errors'], JSON_PRETTY_PRINT));
            }
            $this->throwErrors($respObj['errors']);
        }

        $r = $response ? json_encode($response) : 'Connection timed out';
        throw new S24ApiException('API responded with error:<br><br>' . 'errors in ' . debug_backtrace()[2]['function'] . '():<br><br>' . $r);
    }


    private function throwErrors(array $arr)
    {
        $errs = [];

        $keys = array_keys($arr);
        for ($i = 0; $i < count($arr); $i++) {
            // 133-136 iskelti i atskira funkcija
            if (is_array($arr[$keys[$i]]))
                foreach ($arr[$keys[$i]] as $err)
                    array_push($errs, $keys[$i] . '->' . $err);
            else array_push($errs, $arr[$keys[$i]]);
        }

        throw new ValidationException(implode(",<br>", $errs));
    }

    private function addToDebug($title, $value)
    {
        $this->debug_data[] = array(
            'time' => time(),
            'title' => $title,
            'value' => $value,
        );
    }

    public function getDebugData($return_as_string = true, $with_date = true)
    {
        $return = array();
        
        foreach ( $this->debug_data as $data ) {
            $date = ($with_date) ? '[' . date('Y-m-d H:i:s', $data['time']) . '] ' : '';
            $return[] = $date . $data['title'] . ': ' . $data['value'];
        }

        return ($return_as_string) ? implode(PHP_EOL, $return) : $return;
    }

    public function listAllCountries()
    {
        $response = $this->callAPI($this->url . 'countries');

        return $response->countries;
    }

    public function listAllStates()
    {
        $response = $this->callAPI($this->url . 'states');

        return $response->states;
    }

    public function listAllServices()
    {
        $response = $this->callAPI($this->url . 'services');

        return $response->services;
    }

    public function getOffers(Sender $sender, Receiver $receiver, $parcels)
    {
        $post_data = array(
            'sender' => $sender->generateSenderOffers(),
            'receiver' => $receiver->generateReceiverOffers(),
            'parcels' => $parcels
        );
        $response = $this->callAPI($this->url . 'services/', $post_data);

        return $response->offers;
    }

    public function getAllOrders()
    {
        return $this->callAPI($this->url . 'orders');
    }

    public function generateOrder($order)
    {
        $post_data = $order->__toArray();
        return $this->callAPI($this->url . 'orders', $post_data);
    }

    public function generateOrder_parcelTerminal($order)
    {
        $post_data = $order->__toArray();

        return $this->callAPI($this->url . 'orders', $post_data);
    }

    public function cancelOrder($shipment_id)
    {
        return $this->callAPI($this->url . 'orders/' . $shipment_id . '/cancel');
    }

    public function getLabel($shipment_id)
    {
        return $this->callAPI($this->url . "orders/" . $shipment_id . "/label");
    }

    public function trackOrder($shipment_id)
    {
        return $this->callAPI($this->url . 'orders/' . $shipment_id . '/track');
    }

    public function generateManifest($cart_id)
    {
        return $this->callAPI($this->url . 'manifests/' . $cart_id);
    }

    public function generateManifestLatest()
    {
        return $this->callAPI($this->url . 'manifests/latest');
    }

    public function getTerminals($country_code = 'ALL')
    {
        if ($country_code == 'ALL') {
            return $this->callAPI($this->url . 'terminals');
        }
        return $this->callAPI($this->url . 'terminals/' . $country_code);
    }
}

<?php 
/**
 * This OpenCart Model allow you to call SIGNIFYD API and create a new Investigation Case
 *
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @category   OpenCart Model
 * @package    SIGNIFYD Transaction Investigation
 * @version    1.0
 * @author     Mauro Dutra <mdutra@cudazoo.com>
 * @copyright  2015 Cuda Zoo, LLC.
 * @License:   http://www.gnu.org/licenses/gpl-3.0.en.html
 * @SIGNIFYD API Docs: https://www.signifyd.com/docs/api/
 */

class ModelPaymentSignifydTransactionInvestigation extends Model {
    
    public function CreateCase() {

            $objDateTime = new DateTime('NOW');
            $isoDate = $objDateTime->format('c');         

            foreach ($this->session->data['product_data'] as $product) {                               
                $products[] = array(
                  'itemId' => $product['product_id'],
                  'itemName' => $product['name'],
                  //'itemUrl' => '',
                  //'itemImage' => '',
                  'itemPrice' => $product['price'],
                  'itemQuantity' => $product['quantity']
                  //'itemWeight' => intval($product['weight'] * 453.59237); // The weight of each item in grams.
                );        
            }                

            $d_addr = $this->parseAddressLine($this->session->data['shipping']['address_1'], $this->session->data['shipping']['address_2']);
            $p_addr = $this->parseAddressLine($this->session->data['payment_address']['address_1'], $this->session->data['payment_address']['address_2']);

            $case = array(
                'purchase' => array(
                    'browserIpAddress' => $this->session->data['CLIENT_IP'],
                    'orderId' => (string)$this->session->data['invoice_number'],
                    'createdAt' => $isoDate,  // yyyy-MM-dd'T'HH:mm:ssZ
                    'paymentGateway' => 'authorizenet',
                    'currency' => $this->currency->getCode(),
                    'avsResponseCode' => $this->session->data['avsResponseCode'],
                    'cvvResponseCode' => $this->session->data['cvvResponseCode'],
                    'orderChannel' => 'WEB',  // WEB, PHONE
                    'totalPrice' => $this->currency->format($this->session->data['grand_total'], $this->currency->getCode(), 1.00000, FALSE),  //The total price of the order, including shipping price and taxes.
                    'products' => $products,
                ),
                'recipient' => array(
                    'fullName' => $this->session->data['shipping']['firstname'] . ' ' . $this->session->data['shipping']['lastname'],  // The full name of the person receiving the goods. If this item is being shipped, then this field is the person it is being shipping to. Don't assume this name is the same as card.cardHolderName. Only put a value here if the name will actually appear on the shipping label. If this item is digital, then this field will likely be blank.
                    'confirmationEmail' => $this->session->data['email'],
                    'confirmationPhone' => $this->session->data['telephone'],
                    'organization' => $this->session->data['company'],  // If provided by the buyer, the name of the recipient's company or organization.
                    'deliveryAddress' => array(
                        'streetAddress' => $d_addr['streetAddress'],
                        'unit' => $d_addr['unit'],  // The unit or apartment number.
                        'city' => $this->session->data['shipping']['city'],
                        'provinceCode' => $this->session->data['shipping']['zone'],
                        'postalCode' => $this->session->data['shipping']['postcode'],
                        'countryCode' => $this->session->data['shipping']['iso_code_2']  // The two-letter ISO-3166 country code. If left blank, we will assume US.
                        //'latitude' => ''
                        //'longitude' => ''
                    ),
                ),
                'card' => array(
                    'cardHolderName' => $this->session->data['payment_method']['cc_owner'],
                    'bin' => substr($this->session->data['payment_method']['cc_number'], 0, 6),
                    'last4' => substr($this->session->data['payment_method']['cc_number'], -4),
                    'expiryMonth' => $this->session->data['payment_method']['cc_expire_date_month'],
                    'expiryYear' => $this->session->data['payment_method']['cc_expire_date_year'],
                    'hash' => md5($this->session->data['payment_method']['cc_number']),   // A string uniquely identifying this card (do not send the card number itself).
                    'billingAddress' => array(
                        'streetAddress' => $p_addr['streetAddress'],
                        'unit' => $p_addr['unit'],  // The unit or apartment number.
                        'city' => $this->session->data['payment_address']['city'],
                        'provinceCode' => $this->session->data['payment_address']['zone'],
                        'postalCode' => $this->session->data['payment_address']['postcode'],
                        'countryCode' => $this->session->data['payment_address']['iso_code_2']  // The two-letter ISO-3166 country code. If left blank, we will assume US.
                        //'latitude' => ''
                        //'longitude' => ''
                    ),
                )                        
            );

            //** Commonly, a customer must create an account before placing an order. These data values are details from that account. You should only fill these values in if the customer has an account into which they can login. Leave them blank if this was a one-time transaction with no account.
            if (isset($this->session->data['new_customer'])) {
                //** Format date yyyy-MM-dd'T'HH:mm:ssZ
                $objDateTime = new DateTime($this->session->data['new_customer']['date_added']);
                $isoDate = $objDateTime->format('c');         
                $case['userAccount'] = array(
                    'emailAddress' => $this->session->data['new_customer']['email'],
                    'username' => $this->session->data['new_customer']['username'],
                    'phone' => $this->session->data['new_customer']['telephone'],
                    'createdDate' => $isoDate,
                    'accountNumber' => $this->session->data['new_customer']['customer_id'],
                    'lastOrderId' => '',  // The unique identifier for the last order placed by this account, prior to the current order. (NEW CUSTOMER - no previous orders.)
                    'aggregateOrderCount' => '1',  // The total count of orders placed by this account since it was created, including the current order.
                    'lastUpdateDate' => $isoDate  
                );
            } else {
                if ($this->customer->isLogged()) {
                    //** Format date yyyy-MM-dd'T'HH:mm:ssZ
                    $objDateTime = new DateTime($this->customer->getDateCreated());
                    $dateCreated = $objDateTime->format('c');         
                    $objDateTime = new DateTime($this->customer->getLastModified());
                    $lastModified = $objDateTime->format('c');         
                    $case['userAccount'] = array(
                        'emailAddress' => $this->customer->getEmail(),
                        'username' => $this->customer->getUsername(),
                        'phone' => $this->customer->getTelephone(),
                        'createdDate' => $dateCreated,
                        'accountNumber' => $this->customer->getId(),
                        'lastOrderId' => $this->customer->getLastOrderID(),  // The unique identifier for the last order placed by this account, prior to the current order. (NEW CUSTOMER - no previous orders.)
                        'aggregateOrderCount' => $this->customer->getOrderCount() + 1,  // The total count of orders placed by this account since it was created, including the current order.
                        'lastUpdateDate' => $lastModified  
                    );
                }
            }

            $json = json_encode($case);                                                                                   

            $signifyd_trans_key = 'qZtvQeWzo66uVCNKP3fLRKIjz';
            $signifyd_api_endpoint = 'https://api.signifyd.com/v2/cases';

            $curl = curl_init($signifyd_api_endpoint);

            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen($json)));
            curl_setopt($curl, CURLOPT_USERPWD, $signifyd_trans_key . ":");
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true );   // Return response instead of printing.
            curl_setopt($curl, CURLOPT_TIMEOUT, 3);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 3);

            $response = curl_exec($curl);

            if (!$response) {
                $this->session->data['error'] = 'SIGNIFYD Checkout failed: ' . curl_error($curl) . '(#' . curl_errno($curl) . ')';
                curl_close($curl);
                return false;
            }

            curl_close($curl);

            //$this->load->language('payment/signifyd_transaction_investigation');

            $caseID = 0;

            // HHTP Status Code 201 CREATED - The resource requested was successfully created.                
            if (strpos($response, '201') !== false) {
                $caseID = $this->getCaseID($response);
            }
            $this->load->model('checkout/order');
            $this->model_checkout_order->update_checkout_sti_caseid($this->session->data['order_id'], $caseID);
            return true;
    }
    
    private function getCaseID($response) {
        //*** A successful SIGNIFYD API Create Case response looks like this
        //
        //    "HTTP/1.1 100 Continue
        //
        //    HTTP/1.1 201 Created
        //    Access-Control-Allow-Credentials: true
        //    Access-Control-Allow-Headers: 
        //    Access-Control-Allow-Methods: *
        //    Access-Control-Allow-Origin: 
        //    Cache-Control: no-cache
        //    Content-Type: application/json; charset=utf-8
        //    Vary: Origin
        //    Content-Length: 28
        //    Connection: keep-alive
        //
        //    {"investigationId":15865101}
        //
        //    "
        $json = substr($response, strpos($response, '{"investigationId"'));        
        $case = json_decode($json, true);
        return $case['investigationId'];
    }

    private function parseAddressLine($addr1, $addr2) {
                
        $unitDesignators = "/(#|APARTMENT|APT|BUILDING|BLDG|FLOOR|FL|SUITE|STE|UNIT|UNIT|ROOM|RM|DEPARTMENT|DEPT|SPC|HNGR|HANGER|LOT|PIER|RM|ROOM|TRLR|TRAILER|BSMT|BASEMENT|FRNT|FRONT|LBBY|LOBBY|LOWR|LOWER|OFC|OFFICE|REAR|SIDE|UPPR|UPPER)/";

        //** Default to the address line 1 without parsing any unit number.
        $addr = array(
            'streetAddress' => $addr1,
            'unit' => ''
        );

        if (preg_match($unitDesignators, $addr1, $matches, PREG_OFFSET_CAPTURE)) {                    
            $pos = $matches[0][1];
            if ($pos > 0) {
                $addr = array(
                    'streetAddress' => substr($addr1, 0, $pos),
                    'unit' => substr($addr1, $pos)
                );
            }
        } else {
            //** If an unit number is not found in address_line_1 then we search in address_line_2
            if (preg_match($unitDesignators, $addr2, $matches, PREG_OFFSET_CAPTURE)) {
                $pos = $matches[0][1];
                if ($pos > 0) {
                    $addr = array(
                        'streetAddress' => $addr1,
                        'unit' => substr($addr2, $pos)
                    );
                }
            }
        }
        return $addr;
    }
}
?>

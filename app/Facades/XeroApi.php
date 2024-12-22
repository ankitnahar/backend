<?php

namespace App\Facades;

use XeroPHP\Application;

class XeroApi {

    function __construct() {
       

    }

    public static function getConnection(){
         $XeroAuthData = \App\Models\Backend\XeroAuth::find(1);
        $provider = new \Calcinai\OAuth2\Client\Provider\Xero([
            'clientId' => env('XERO_KEY'),
            'clientSecret' => env('XERO_SECRETE'),
            'redirectUri' => 'http://localhost/xero-php-oauth2-starter-master/callback.php',
            'urlAuthorize' => 'https://login.xero.com/identity/connect/authorize',
            'urlAccessToken' => 'https://identity.xero.com/connect/token',
            'urlResourceOwnerDetails' => 'https://api.xero.com/api.xro/2.0/Organisation'
        ]);
        $options = [
            'scope' => ['openid email profile offline_access assets projects accounting.settings accounting.transactions accounting.contacts accounting.journals.read accounting.reports.read accounting.attachments']
        ];
        $newAccessToken = $provider->getAccessToken('refresh_token', [
            'refresh_token' => $XeroAuthData->refresh_token
        ]);
        // Save Refresh Token
        $XeroAuthData->token = $newAccessToken->getToken();
        $XeroAuthData->expires = $newAccessToken->getExpires();
        $XeroAuthData->refresh_token = $newAccessToken->getRefreshToken();
        $XeroAuthData->id_token = $newAccessToken->getValues()["id_token"];
        $XeroAuthData->updated_on = date("Y-m-d H:i:s");
        $XeroAuthData->save();
        return $XeroAuthData;
    }
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    public static function getAllContacts() {
        $XeroAuthData = self::getConnection();
       
        $xero = new \XeroPHP\Application($XeroAuthData->token, $XeroAuthData->tenant_id);
        # get contacts from XERO
        return $xero->load('Accounting\\Contact')->execute();
    }

    /**
     * Get the ContactID by Email registered
     *
     * @return string
     */
    public static function getContactByEmail($email) {
        $XeroAuthData = self::getConnection();
        $xero = new \XeroPHP\Application($XeroAuthData->token, $XeroAuthData->tenant_id);
        # get contacts from XERO 
        $contacts = $xero->load('Accounting\\Contact')->execute();
        foreach ($contacts as $contact) {
            if (strtolower($contact->EmailAddress) == strtolower($email)) {
                return $contact->ContactID;
            }
        }
        return false;
    }

    /**
     * Get the ContactID by Email registered
     *
     * @return string
     */
    public static function getContactByName($name) {
       $XeroAuthData = self::getConnection();
        $xero = new \XeroPHP\Application($XeroAuthData->token, $XeroAuthData->tenant_id);
        # get contacts from XERO 
        $contacts = $xero->load(\XeroPHP\Models\Accounting\Contact::class)->execute();
        //showArray($contacts);exit;
        foreach ($contacts as $contact) {
            if (strtolower($contact->Name) == strtolower($name)) {
                return $contact->ContactID;
            }
        }
        return false;
    }

    /**
     * Get the ContactID by Email registered
     *
     * @return string
     */
    public static function checkInvoice($invoiceNo) {
        $XeroAuthData = self::getConnection();
        $xero = new \XeroPHP\Application($XeroAuthData->token, $XeroAuthData->tenant_id);
        # get invoices from XERO 
        return $xero->load('Accounting\\Invoice')->where("InvoiceNumber",$invoiceNo)->execute();
    }
    
    /**
     * Get the ContactID by Email registered
     *
     * @return string
     */
    public static function getAllInvoices() {
        $XeroAuthData = self::getConnection();
        $xero = new \XeroPHP\Application($XeroAuthData->token, $XeroAuthData->tenant_id);
        # get invoices from XERO 
        return $xero->load('Accounting\\Invoice')->execute();
    }

    /**
     * Get the invoices by status EG; INVOICE_STATUS_AUTHORISED
     *
     * @return string
     */
    public static function getInvoicesByStatus($status) {
        $XeroAuthData = self::getConnection();
        $xero = new \XeroPHP\Application($XeroAuthData->token, $XeroAuthData->tenant_id);
        # get invoices from XERO 
        return $xero->load('Accounting\\Invoice')
                        ->where('Status', \XeroPHP\Models\Accounting\Invoice::$status)
                        ->execute();
    }

    /**
     * Get the invoices by status EG; INVOICE_STATUS_AUTHORISED
     *
     * @return string
     */
    public static function getPartPaidInvoices($date = NULL) {
        if ($date == NULL) {
            $date = date('Y,m,d', strtotime("-1 day"));
        }
        $XeroAuthData = self::getConnection();
        $xero = new \XeroPHP\Application($XeroAuthData->token, $XeroAuthData->tenant_id);
        # get invoices from XERO 
        return $xero->load('Accounting\\Invoice')
                        ->where('AmountDue > 0.00')
                        ->where('AmountPaid != 0')
                        ->where(sprintf('UpdatedDateUTC >= DateTime(%s)', $date))
                        ->execute();
    }

    /**
     * Get the invoices by status EG; INVOICE_STATUS_AUTHORISED
     *
     * @return string
     */
    public static function getPaidInvoices($date = NULL) {
        if ($date == NULL) {
            $date = date('Y,m,d', strtotime("-2 day"));
        }
        $XeroAuthData = self::getConnection();
        $xero = new \XeroPHP\Application($XeroAuthData->token, $XeroAuthData->tenant_id);
        # get invoices from XERO 
        return $xero->load('Accounting\\Invoice')
                        ->where('AmountCredited > 0')
                        ->orWhere('AmountPaid > 0')
                        ->where(sprintf('UpdatedDateUTC >= DateTime(%s)', $date))
                        ->orderBy('UpdatedDateUTC', 'DESC')
                        ->execute();
    }

    /**
     * Create invoice
     *
     * @return string
     */
    public static function createInvoice($dataArray) {
        $sendInvoice = array();
        //showArray($dataArray);exit;
        if (!empty($dataArray)) {
            foreach ($dataArray as $data) {
               
                //\App\Models\Backend\Invoice::where("invoice_no",$data['InvoiceNumber'])->update(["xero_responce"=>0]);
               $XeroAuthData =self::getConnection();
        $xero = new \XeroPHP\Application($XeroAuthData->token, $XeroAuthData->tenant_id);
                if ($data['xero_contact_id'] == '') {
                    $ContactID = self::getContactByName($data['EntityName']);
                    
                    if (empty($ContactID)) {
                        $ContactID = self::createContact($data['EntityName']);
                    }
                    //echo $ContactID;exit;
                    \App\Models\Backend\Entity::where("id", $data['EntityId'])->update(["xero_contact_id" => $ContactID]);
                } else {
                    $ContactID = $data['xero_contact_id'];
                }
                $contact = new \XeroPHP\Models\Accounting\Contact($xero);
                $contact->setName($data['EntityName']);
                $contact->setContactID($ContactID);

                $xeroInvoice = new \XeroPHP\Models\Accounting\Invoice($xero);
                $xeroInvoice->setType($data['Type']);
                $xeroInvoice->setContact($contact);
                $xeroInvoice->setReference($data['Reference']);
                $xeroInvoice->setLineAmountType($data['AmountType']);
                $xeroInvoice->setInvoiceNumber($data['InvoiceNumber']);
                $xeroInvoice->setStatus($data['Status']);
                $xeroInvoice->setDate(\DateTime::createFromFormat('Y-m-d', $data['Date']));
                $xeroInvoice->setDueDate(\DateTime::createFromFormat('Y-m-d', $data['DueDate']));

                foreach ($data['LineItems'] as $LineItem) {
                    $xeroLineItem = new \XeroPHP\Models\Accounting\Invoice\LineItem($xero);
                    $xeroLineItem->setQuantity($LineItem['Quantity']);
                    $xeroLineItem->setDescription($LineItem['Description']);
                    $xeroLineItem->setUnitAmount($LineItem['UnitAmount']);
                   // if (isset($LineItem['AccountCode'])) {
                        $xeroLineItem->setAccountCode($LineItem['AccountCode']);
                   // }
                    if (isset($LineItem['TrackingCategory'])) {
                        $trackingCategory = (new \XeroPHP\Models\Accounting\TrackingCategory($xero))
                                ->setName($LineItem['TrackingCategory']['Name'])
                                ->setOption($LineItem['TrackingCategory']['Option']);

                        $xeroLineItem->addTracking($trackingCategory);
                    }
                    $xeroInvoice->addLineItem($xeroLineItem);
                }
                //showArray($xeroInvoice);exit;
                //$sendInvoice[] = $xeroInvoice;
                $sendInvoice = $xero->save($xeroInvoice);
                if ($sendInvoice->getElementErrors()) { 
                    \App\Models\Backend\Invoice::where("invoice_no", $data['InvoiceNumber'])
                            ->update(["xero_responce"=>0,"xero_error" => $sendInvoice->getElementErrors()]);                            
                       // return $sendInvoice;
                    } else {
                            \App\Models\Backend\Invoice::where("invoice_no", $data['InvoiceNumber'])->update(["status_id" => 9,"xero_responce"=>1]);
                            \App\Http\Controllers\Backend\Invoice\InvoiceXeroController::addInvoiceLog($data['InvoiceNumber'], 9);
                    }
            }
           
           // echo '<br/>';
            // $allInvoice = $xero->saveAll($sendInvoice);
            //showArray($allInvoice);exit;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Create Contact
     *
     * @return string
     */
    public static function createContact($contactName) {
        session_start();
 
/*$provider = new \Calcinai\OAuth2\Client\Provider\Xero([
    'clientId'          => '3308372314D344EFBAE4FD2C53F93AE8',
   'clientSecret'       => 'ebJ48dAvSptPj_Ec87L-m8t62nRQoqP2pQTKx8q9ejRIcsbf',
    'redirectUri'       => 'http://localhost/bdmsapi/public/v1.0',
]);*/

$XeroAuthData = self::getConnection();
        $xero = new \XeroPHP\Application($XeroAuthData->token, $XeroAuthData->tenant_id);

  // $xero = new PrivateApplication(config('services.xero_base_config'));
        $xero_new_contact = new \XeroPHP\Models\Accounting\Contact($xero);
        $xero_new_contact->setName($contactName);
        $Contact = $xero_new_contact->save(); # for arrays of objects->   $xero->saveAll();
        $contactValue = $Contact->getElements();
        $contactId = $contactValue[0]['ContactID'];

        return $contactId;
    }

}

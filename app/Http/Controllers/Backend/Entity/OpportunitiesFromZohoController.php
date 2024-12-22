<?php

namespace App\Http\Controllers\Backend\Entity;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\Entity;

$GLOBALS['account_id'] = array();
$GLOBALS['actual_lead_owner'] = array();
$GLOBALS['contract_executed'] = array();
$GLOBALS['contract_signed_date'] = array();

/**
 * This is a client class controller.
 * 
 */
class OpportunitiesFromZohoController extends Controller {

    /**
     * Created by: Jayesh Shingrakhiya
     * Created on: Jan 09, 2019
     * Reason: To prepare request URL
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public static function prepareUrl($params) {
        if (is_array($params) && !empty($params)) {
            $i = 0;
            foreach ($params as $key => $value) {
                if ($i == 0)
                    $preparedUrl = $key . '=' . $value;
                else
                    $preparedUrl .= '&' . $key . '=' . $value;

                $i++;
            }
        }
        return $preparedUrl;
    }

    /**
     * Created by: Vivek Parmar
     * Created on: Feb 05, 2020
     * Reason: To prepare parse JSON
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public static function parseJson($records) {
        try {
            if (isset($records) && !empty($records)) {
                $responseArray = array();
                $zohoServices = \App\Models\Backend\Services::where('show_in_pi', 1)->get()->toArray();
                $servicesArray = array();
                foreach ($zohoServices as $keyServices => $valueServices)
                    $servicesArray[$valueServices['pi_zoho_service']] = $valueServices;

                $entityPermenentInfo = $billingBasic = $entityCheckoutlist = array();
                $j = 0;
                foreach ($records as $record) {
                    $contract_date = '';
                    $entityId = $record->getEntityId();
                    
                    $contract_executed = !empty($record->getFieldValue('Contract_executed')) ? $record->getFieldValue('Contract_executed') : '';

                    // Get All service agreed by client
                    $services = !empty($record->getFieldValue('Agreed_Services')) ? $record->getFieldValue('Agreed_Services') : '';
                    if (!empty($services)) {
                        if ($contract_executed == "Yes") {
                            $oldDate = date('d-m-Y', strtotime("-12 Months", strtotime(date('Y-01-01'))));
                            $contract_date = !empty($record->getFieldValue('Contract_signed_date')) ? strtotime($record->getFieldValue('Contract_signed_date')) : '';

                            if (!empty($contract_date)) {
                                if ($contract_date != '' || $contract_date > $oldDate) {
                                    if (!empty($record->getFieldValue('Contract_type')) && ($record->getFieldValue('Contract_type')) == 'Discontinued')
                                        continue;
                                }
                                $accountName = $record->getFieldValue('Account_Name');
                                
                                if (!empty($accountName)) {
                                    $tempEntityName = strtolower(preg_replace("/[^a-zA-Z0-9\s]/", "", $accountName->getLookupLabel()));
                                    $entityName = stripslashes(addslashes(trim($accountName->getLookupLabel())));
                                  
                                    $accountId = $accountName->getEntityId();
                                    $email = !empty($record->getFieldValue('Email_1')) ? stripslashes(addslashes(trim($record->getFieldValue('Email_1')))) : '';
                                    $duplicateEntity = \App\Models\Backend\Entity::where('zoho_entity_account_id', addslashes(trim($accountId)))->count();
                                    $entityStage = $pendingWorksheetScheduleMaster = $newEntityReviewForm = $entityOtherAllocation = $entitySystemSetupStage = array();
                                   /*$entityDataCode = Entity::where("zoho_entity_account_id",addslashes(trim($accountId)));
                                        if($entityDataCode->count() > 0) 
                                        {
                                            $entityDataCode = $entityDataCode->first();
                                            $record->setFieldValue("Client_Code", $entityDataCode->code);
                                            $apiResponse=$record->update();
                                            continue;
                                        }*/
                                    if ($duplicateEntity == 0 && $accountId != 882444000036365403) {
                                        $entity = array();
                                        
                                        // Generate Unique Client Code
                                        $entityCode = generateClientCode($tempEntityName);

                                        if ($entityCode != '') {
                                            $id = addslashes(trim($accountId));
                                            if(env('DB_HOST') == '65.0.143.196'){
                                            $record->setFieldValue("Client_Code", $entityCode);
                                            $apiResponse=$record->update();
                                            }
                                        }
                                        $checkQuote = \App\Models\Backend\QuoteMaster::where("lead_company_name",$entityName)->where("stage_id",12);
                                        if($checkQuote->count() > 0){
                                            $checkQuote = $checkQuote->first();
                                            \App\Models\Backend\QuoteMaster::where("id",$checkQuote->id)->update(["stage_id" => 8]);
                                            $quoteStageLog['quote_master_id'] = $checkQuote->id;
                                            $quoteStageLog['stage_id'] = 8;
                                            $quoteStageLog['created_by'] = (app('auth')->guard()->id()) ? app('auth')->guard()->id() : 1;
                                            $quoteStageLog['created_on'] = date('Y-m-d H:i:s');
                                            \App\Models\Backend\QuoteStageLog::insert($quoteStageLog);
                                        }
                                        //[In future Qoute module related code will be developed here].
                                        $entity['name'] = $entityName;
                                        $entity['billing_name'] = $entityName;
                                        $entity['trading_name'] = $entityName;
                                        $entity['code'] = $entityCode;
                                        $entity['entity_from_zoho'] = 1;
                                        $entity['zoho_entity_insert_time'] = date('Y-m-d H:i:s');
                                        $entity['zoho_entity_account_id'] = addslashes(trim($accountId));
                                        $entity['created_by'] = 1;
                                        $entity['created_on'] = date('Y-m-d H:i:s');

                                        // Add data in entity
                                        $entityData = \App\Models\Backend\Entity::create($entity);
                                        $entityId = $entityData->id;
                                        $entityPermenentInfo[0]['entity_id'] = $entityId;
                                        $entityPermenentInfo[0]['entity_name'] = !empty($accountName->getLookupLabel()) ? addslashes(trim($accountName->getLookupLabel())) : '';
                                        $entityPermenentInfo[0]['abn_number'] = !empty($record->getFieldValue('ABN')) ? addslashes(trim($record->getFieldValue('ABN'))) : '';
                                        // $entityPermenentInfo[0]['phone'] = !empty($record->getFieldValue('ABN')) ? addslashes(trim($record->getFieldValue('ABN'))) : '';
                                        $entityPermenentInfo[0]['email'] = !empty($record->getFieldValue('Email_1')) ? addslashes(trim($record->getFieldValue('Email_1'))) : '';
                                        $entityPermenentInfo[0]['industry'] = !empty($record->getFieldValue('Industry')) ? addslashes(trim($record->getFieldValue('Industry'))) : '';
                                        // $entityPermenentInfo[0]['turnover'] = isset($arrangeData[$i]['Estimated Annual Value']) ? addslashes(trim($arrangeData[$i]['Estimated Annual Value'])) : '';
                                        $entityPermenentInfo[0]['contract_signed_date'] = !empty($record->getFieldValue('Contract_signed_date')) ? $record->getFieldValue('Contract_signed_date') : '';
                                        $salesPerson = !empty($record->getFieldValue('Actual_Lead_Owner')) ? \App\Models\User::select('id')->where('userfullname', addslashes(trim($record->getFieldValue('Actual_Lead_Owner'))))->get()->toArray() : array();
                                        $entityPermenentInfo[0]['sales_person_id'] = (!empty($salesPerson) && isset($salesPerson[0]['id'])) ? $salesPerson[0]['id'] : 0;
                                        $entityPermenentInfo[0]['service_id'] = '';
                                        $entityPermenentInfo[0]['created_by'] = 1;
                                        $entityPermenentInfo[0]['created_on'] = date('Y-m-d H:i:s');

                                        // check agreed services and add entity stage and wr3 information
                                        $entitySystemSetupStageService = array();
                                        if ($services != '') {
                                            $isSMSFOnly = 0;
                                            if (!empty($services) && ($services[0] == 'SMSF' || $services[0] == 'Existing SMSF Compliance' || $services[0] == 'NEW SMSF Setup and Compliance')) {
                                                // $services[0] = array('is_archived' => 1, 'archived_date' => date('Y-m-d H:i:s'));
                                                $isSMSFOnly = 1;
                                                $entityStage = [8];
                                            } else if (!empty($services) && $services[0] == 'Tax') {
                                                $entityStage = [3, 5, 8];
                                            } else {
                                                $stageId = [1, 3, 4, 5, 6, 8, 9, 13];
                                                $stageId[] = financialYear();
                                                $entityStage = \App\Models\Backend\SystemSetupStage::whereIn('id', $stageId)->pluck('id', 'id')->toArray();
                                            }

                                            foreach ($entityStage as $keyStage => $valueStage) {
                                                $data = array();
                                                $data['entity_id'] = $entityId;
                                                $data['stage_id'] = $valueStage;
                                                $data['status'] = 'N';
                                                $entitySystemSetupStage[] = $data;
                                            }

                                            $serviceIds = array();
                                            foreach ($services as $keyService => $valueService) {
                                                $serviceIds[] = $servicesArray[$valueService]['id'];
                                                if (isset($servicesArray[$valueService]['id']) && $valueService !='Tax') {
                                                    self::insertIntoWr3($servicesArray[$valueService], $entityId, 1);
                                                }
                                                $entitySystemSetupStageService[$j]['entity_id'] = $entityId;
                                                $entitySystemSetupStageService[$j]['service'] = $valueService;
                                                $entitySystemSetupStageService[$j]['status'] = 0;
                                            }

                                            if ($isSMSFOnly == 1) {
                                                $entitySystemSetupStageService[$j]['status'] = 1;
                                                $entitySystemSetupStageService[$j]['updated_by'] = 1;
                                                $entitySystemSetupStageService[$j]['updated_on'] = date('Y-m-d H:i:s');
                                                $wr3 = \App\Models\Backend\WR3::where('entity_id', $entityId)->update(['is_archived' => 1, 'modified_on' => date('Y-m-d H:i:s'), 'modified_by' => 1]);
                                            }

                                            $billingBasic[0]['entity_id'] = $entityId;
                                            $billingBasic[0]['created_by'] = 1;
                                            $billingBasic[0]['created_on'] = date('Y-m-d H:i:s');
                                            $serviceIds = array_unique($serviceIds);
                                            $entityPermenentInfo[0]['service_id'] = implode(',', $serviceIds);

                                            $masterChecklist = \App\Models\Backend\MasterChecklist::select('master_checklist.*')->join('master_activity as ma', 'ma.id', '=', 'master_activity_id')->where('ma.is_active', 1)->whereIn('ma.service_id', $serviceIds)->get();
                                            if (count($masterChecklist)) {
                                                $j = 0;
                                                foreach ($masterChecklist as $keyChecklist => $valueChecklist) {
                                                    $entityCheckoutlist[$j]['master_checklist_id'] = $valueChecklist->id;
                                                    $entityCheckoutlist[$j]['entity_id'] = $entityId;
                                                    $entityCheckoutlist[$j]['is_applicable'] = 1;
                                                    $entityCheckoutlist[$j]['created_by'] = 1;
                                                    $entityCheckoutlist[$j]['created_on'] = date('Y-m-d H:i:s');
                                                    $j++;
                                                }

                                                // Add data in entity check list
                                                \App\Models\Backend\EntityChecklist::insert($entityCheckoutlist);
                                            }

                                            $year = financialYear();
                                            $pendingWorksheetScheduleMaster[$j]['entity_id'] = $entityId;
                                            $pendingWorksheetScheduleMaster[$j]['year'] = $year;
                                            $pendingWorksheetScheduleMaster[$j]['status'] = $isSMSFOnly;
                                            $pendingWorksheetScheduleMaster[$j]['modified_by'] = 1;
                                            $pendingWorksheetScheduleMaster[$j]['modified_on'] = date('Y-m-d H:i:s');

                                            $newEntityReviewForm['entity_id'] = $entityId;
                                            $newEntityReviewForm['created_on'] = date('Y-m-d H:i:s');
                                            $newEntityReviewForm['created_by'] = 1;

                                            $otherEntity = \App\Models\Backend\Button::select(app('db')->raw('GROUP_CONCAT(utr.user_id) as user_id'))->where('tab_button.button_name', 'all_entity')->where('tab_button.visible', 1)->whereRaw('FIND_IN_SET(tab_button.id, utr.other_right)')->join('user_tab_right AS utr', 'tab_button.tab_id', '=', 'utr.tab_id')->get()->toArray();
                                            if (isset($otherEntity[0]['user_id']) && $otherEntity[0]['user_id'] != '') {
                                                $entityOtherAllocation['entity_id'] = $entityId;
                                                $entityOtherAllocation['other'] = '';
                                            }
                                        }
                                        if (!empty($services) && $services[0] != 'Tax') {
                                        // Add Permanent Info
                                        $isPermanentInfo = \App\Models\Backend\PermanentInfo::select('id')->where('entity_id', $entityId)->count();
                                        if ($isPermanentInfo == 0)
                                            \App\Models\Backend\PermanentInfo::insert($entityPermenentInfo);
                                        
                                         // Add Pending worksheet Schedule Info
                                        $isWorksheetSchedule = \App\Models\Backend\PendingWorksheetScheduleMaster::select('id')->where('entity_id', $entityId)->count();
                                        if ($isWorksheetSchedule == 0)
                                            \App\Models\Backend\PendingWorksheetScheduleMaster::insert($pendingWorksheetScheduleMaster);

                                        // Add New Client Review Info
                                        $isReviewForm = \App\Models\Backend\NewClientReviewMaster::select('id')->where('entity_id', $entityId)->count();
                                        if ($isReviewForm == 0)
                                            \App\Models\Backend\NewClientReviewMaster::insert($newEntityReviewForm);
                                        
                                        }
                                        // Add Billing Info
                                        $isBillingAgree = \App\Models\Backend\Billing::select('id')->where('entity_id', $entityId)->count();
                                        if ($isBillingAgree == 0)
                                            \App\Models\Backend\Billing::insert($billingBasic);

                                        // Add System Setup Entity Stage Info
                                        $isSystemSetupEntityStage = \App\Models\Backend\SystemSetupEntityStage::select('id')->where('entity_id', $entityId)->count();
                                        if ($isSystemSetupEntityStage == 0)
                                            app('db')->table('system_setup_entity_stage')->insert($entitySystemSetupStage);

                                        // Add System Setup Entity Stage Service Info
                                        $isEntityStageService = \App\Models\Backend\SystemSetupEntityStageService::select('id')->where('entity_id', $entityId)->count();
                                        if ($isEntityStageService == 0)
                                            \App\Models\Backend\SystemSetupEntityStageService::insert($entitySystemSetupStageService);

                                       

                                        autoAssignAllEntityUser($entityId);

                                        $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('OPPORTINUTIESFROMZOHO');
                                        if ($emailTemplate->is_active == 1) {
                                            $data['to'] = $emailTemplate->to == '' ? $emailTemplate->cc : $emailTemplate->to;
                                            $data['from'] = 'noreply-bdms@befree.com.au';
                                            $data['cc'] = $emailTemplate->cc;
                                            $data['subject'] = $emailTemplate->subject;
                                            $data['content'] = str_replace('ENTITYNAME', $entityName, $emailTemplate->content);
                                            storeMail('', $data);
                                        }
                                    }
                                }
                            }
                        }
                    }
                    $j++;
                }
            }
        } catch (Exception $exception) {
            $error = "Error code: " . $xml->error->code;
            $error .= "Error message: " . $xml->error->message;
            app('log')->error("Oportunities related error while prepare potential array : " . $error);
        }
    }

    /**
     * Created by: Jayesh Shingrakhiya
     * Created on: Jan 09, 2019
     * Reason: To prepare request URL
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public static function curlRequest($url, $queryString) {
        try {
            $ch = curl_init(); // initialize curl handle
            curl_setopt($ch, CURLOPT_URL, $url); // set url to send post request
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // allow redirects
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return a response into a variable
            curl_setopt($ch, CURLOPT_TIMEOUT, 30); // times out after 30s
            curl_setopt($ch, CURLOPT_POST, 1); // set POST method
            curl_setopt($ch, CURLOPT_POSTFIELDS, $queryString); // add POST fields parameters
            /* If local then only may required because SSL verify peer not required in live */
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // set SSL verify Mode false
            $result = curl_exec($ch); // execute the cURL
            curl_close($ch);
            return $result;
        } catch (Exception $exception) {
            $error = "Error code: " . $xml->error->code;
            $error .= "Error message: " . $xml->error->message;
            app('log')->error("Oportunities related error while prepare potential array : " . $error);
        }
    }

    /**
     * Created by: Jayesh Shingrakhiya
     * Created on: Jan 09, 2019
     * Reason: To prepare parse XML
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public static function parseXml($data, $zohoDetail) {
        $xmlString = <<<XML
$data 
XML;
        $xml = simplexml_load_string($xmlString);
        if (isset($xml->result)) {
            $totalRows = count($xml->result->Potentials->row);
            $tempData[] = array();
            for ($i = 0; $i < $totalRows; $i++) {
                $totalInnerRows = count($xml->result->Potentials->row[$i]->FL);
                for ($j = 0; $j < $totalInnerRows; $j++) {
                    switch ((string) $xml->result->Potentials->row[$i]->FL[$j]['val']) {
                        case 'ACCOUNTID': // Get attributes as element indices
                            $tempData[$i]['ACCOUNTID'] = (string) $xml->result->Potentials->row[$i]->FL[$j];
                            break;
                        case 'Contract executed?':
                            $tempData[$i]['Contract executed?'] = (string) $xml->result->Potentials->row[$i]->FL[$j];
                            break;
                        case 'Contract signed date':
                            $tempData[$i]['Contract signed date'] = (string) $xml->result->Potentials->row[$i]->FL[$j];
                            break;
                        case 'Actual Lead Owner':
                            $tempData[$i]['Actual Lead Owner'] = (string) $xml->result->Potentials->row[$i]->FL[$j];
                            break;
                        case 'Existing client':
                            $tempData[$i]['Existing client'] = (string) $xml->result->Potentials->row[$i]->FL[$j];
                            break;
                    }
                }
            }
            asort($tempData);
            if (!empty($tempData)) {
                $accountId = $actualLeadOwner = $contractExecuted = $contracSignedDate = array();
                $alreadyExist = Entity::where('zoho_entity_account_id', '!=', '')->pluck('zoho_entity_account_id', 'id')->toArray();

                for ($k = 0; $k < 199; $k++) {
                    if (isset($tempData[$k]['Contract executed?']) && ($tempData[$k]['Contract executed?'] == 'Yes') && isset($tempData[$k]['Existing client']) && ($tempData[$k]['Existing client'] == 'No') && isset($tempData[$k]['Contract signed date']) && ($tempData[$k]['Contract signed date'] != '')) {
                        if (!in_array($tempData[$k]['ACCOUNTID'], $alreadyExist)) {
                            array_push($GLOBALS['account_id'], $tempData[$k]['ACCOUNTID']);
                            array_push($GLOBALS['actual_lead_owner'], isset($tempData[$k]['Actual Lead Owner']) ? $tempData[$k]['Actual Lead Owner'] : '');
                            array_push($GLOBALS['contract_executed'], isset($tempData[$k]['Contract executed?']) ? $tempData[$k]['Contract executed?'] : '');
                            array_push($GLOBALS['contract_signed_date'], isset($tempData[$k]['Contract signed date']) ? $tempData[$k]['Contract signed date'] : '');
                        }
                    }
                }

                // $accountId = array(882444000035405929);
                $accountId = $GLOBALS['account_id'];
                // Get Account Informations 
                if (!empty($accountId)) {
                    $zohoDetail['accountUrl'];
                    $tokenArray = \GuzzleHttp\json_decode($zohoDetail['token']);

                    foreach ($tokenArray as $keyToken => $valueToken) {
                        $param['scope'] = $zohoDetail['scope'];
                        $param['authtoken'] = $valueToken;
                        $param['idlist'] = implode(";", $accountId);
                        $param['lastModifiedTime'] = date('yy-mm-dd hh-ii-ss');
                        $param['fromIndex'] = 1;
                        $param['toIndex'] = 1;
                        $param['selectColumns'] = 'Accounts(LEADID,Account Name,Account Type,ABN,Phone,Email,Contract Executed,Contract Signed Date,Industry,Estimated Annual Value,Sales Person,Service To Be Provided)';
                        $queryString = self::prepareUrl($param);
                        $response = self::curlRequest($zohoDetail['accountUrl'], $queryString);
                        self::parseXmlAndInsertIntoBdms($response);
                    }
                }
            }
        }
    }

    public static function parseXmlAndInsertIntoBdms($responseData) {
        $xmlString = <<<XML
$responseData 
XML;
        $xml = simplexml_load_string($xmlString);
        if (isset($xml->result)) {
            self::insertIntoBdms($xml);
        } else if (isset($xml->error)) {
            $error = "Error code: " . $xml->error->code;
            $error .= "Error message: " . $xml->error->message;
            app('log')->error("Oportunities related error while insert records into bdms database: " . $error);
        }
    }

    public static function insertIntoBdms($rawData) {
        // $initialPreviousYear = date('d-m-Y', strtotime(date('Y-07-01') . ' -1 year'));
        $initialPreviousYear = date('d-m-Y', strtotime(date('Y-m-d') . ' -60 days'));
        $totalRow = count($rawData->result->Accounts->row);
        $arrangeData = array();
        for ($i = 0; $i < $totalRow; $i++) {
            if (strtotime($initialPreviousYear) < strtotime($GLOBALS['contract_signed_date'][$i])) {
                $totalInnerRow = count($rawData->result->Accounts->row[$i]->FL);
                for ($j = 0; $j < $totalInnerRow; $j++) {
                    switch ((string) $rawData->result->Accounts->row[$i]->FL[$j]['val']) {
                        case 'LEADID': // Get attributes as element indices
                            $arrangeData[$i]['LEADID'] = (string) $rawData->result->Accounts->row[$i]->FL[$j];
                            break;
                        case 'ACCOUNTID':
                            $arrangeData[$i]['ACCOUNTID'] = (string) $rawData->result->Accounts->row[$i]->FL[$j];
                            break;
                        case 'Account Name':
                            $arrangeData[$i]['Account Name'] = (string) $rawData->result->Accounts->row[$i]->FL[$j];
                            break;
                        case 'Account Type':
                            $arrangeData[$i]['Account Type'] = (string) $rawData->result->Accounts->row[$i]->FL[$j];
                            break;
                        case 'ABN':
                            $arrangeData[$i]['ABN'] = (string) $rawData->result->Accounts->row[$i]->FL[$j];
                            break;
                        case 'Phone':
                            $arrangeData[$i]['Phone'] = (string) $rawData->result->Accounts->row[$i]->FL[$j];
                            break;
                        case 'Email':
                            $arrangeData[$i]['Email'] = (string) $rawData->result->Accounts->row[$i]->FL[$j];
                            break;
                        case 'Industry':
                            $arrangeData[$i]['Industry'] = (string) $rawData->result->Accounts->row[$i]->FL[$j];
                            break;
                        case 'Estimated Annual Value':
                            $arrangeData[$i]['Estimated Annual Value'] = (string) $rawData->result->Accounts->row[$i]->FL[$j];
                            break;
                        case 'Sales Person':
                            $arrangeData[$i]['Sales Person'] = $GLOBALS['actual_lead_owner'][$i];
                            break;
                        case 'Service To Be Provided':
                            $arrangeData[$i]['Service To Be Provided'] = (string) $rawData->result->Accounts->row[$i]->FL[$j];
                            break;
                        default :
                    }
                }
                $arrangeData[$i]['Contract Executed'] = 'Yes';
                $arrangeData[$i]['Contract Signed Date'] = $GLOBALS['contract_signed_date'][$i];
            }
        }

        $zohoServices = \App\Models\Backend\Services::where('show_in_pi', 1)->get()->toArray();
        $servicesArray = array();
        foreach ($zohoServices as $keyServices => $valueServices)
            $servicesArray[$valueServices['pi_zoho_service']] = $valueServices;
        //        $servicesAvailableInBdms = $zohoServices->pluck('service_name', 'pi_zoho_service')->toArray();
        //        $servicesAvailableInBdmsListbyId = $zohoServices->pluck('id', 'pi_zoho_service')->toArray();
        $entityPermenentInfo = $billingBasic = $entityCheckoutlist = array();
        for ($i = 0; $i < count($arrangeData); $i++) {
            /**
             * As per discussion with Viral sir if Contract Executed then we only we convert in to account otherwise we will not take this account in BDMS
             * Discussed By Nishant on 26-07-2016
             */
            $contract_executed = !empty($arrangeData[$i]['Contract Executed']) ? $arrangeData[$i]['Contract Executed'] : '';
            if ($contract_executed == "Yes") {
                $oldDate = date('d-m-Y', strtotime("-12 Months", strtotime(date('Y-01-01'))));
                $zoho_lead_email = $arrangeData[$i]['Email'];
                $contract_date = !empty($arrangeData[$i]['Contract Signed Date']) ? strtotime($arrangeData[$i]['Contract Signed Date']) : '';
                if ($contract_date != '' || $contract_date > $oldDate) {
                    if (!empty($arrangeData[$i]['Account Type']) && $arrangeData[$i]['Account Type'] == 'Discontinued')
                        continue;
                }

                $tempEntityName = strtolower(preg_replace("/[^a-zA-Z0-9\s]/", "", $arrangeData[$i]['Account Name']));
                $entityName = stripslashes(addslashes(trim($arrangeData[$i]['Account Name'])));
                $email = !empty($arrangeData[$i]['Email']) ? stripslashes(addslashes(trim($arrangeData[$i]['Email']))) : '';
                $duplicateEntity = \App\Models\Backend\Entity::where('zoho_entity_account_id', addslashes(trim($arrangeData[$i]['ACCOUNTID'])))->count();

                $entityStage = $pendingWorksheetScheduleMaster = $newEntityReviewForm = $entityOtherAllocation = $entitySystemSetupStage = array();
                if ($duplicateEntity == 0 && $arrangeData[$i]['ACCOUNTID'] != 882444000036365403) {
                    $entity = array();
                    $entityCode = generateClientCode($tempEntityName);
                    if ($entityCode != '') {
                        $id = addslashes(trim($arrangeData[$i]['ACCOUNTID']));
                        // self::Updatezohoopportunity($id, $entityCode);
                    }
                    //[In future Qoute module related code will be developed here].
                    $entity['name'] = $entityName;
                    $entity['billing_name'] = $entityName;
                    $entity['trading_name'] = $entityName;
                    $entity['code'] = $entityCode;
                    $entity['entity_from_zoho'] = 1;
                    $entity['zoho_entity_insert_time'] = date('Y-m-d H:i:s');
                    $entity['zoho_entity_account_id'] = addslashes(trim($arrangeData[$i]['ACCOUNTID']));
                    $entity['created_by'] = 1;
                    $entity['created_on'] = date('Y-m-d H:i:s');

                    $entityData = \App\Models\Backend\Entity::create($entity);
                    $entityId = $entityData->id;
                    $entityPermenentInfo[0]['entity_id'] = $entityId;
                    $entityPermenentInfo[0]['entity_name'] = isset($arrangeData[$i]['Account Name']) ? addslashes(trim($arrangeData[$i]['Account Name'])) : '';
                    $entityPermenentInfo[0]['abn_number'] = isset($arrangeData[$i]['ABN']) ? addslashes(trim($arrangeData[$i]['ABN'])) : '';
                    $entityPermenentInfo[0]['phone'] = isset($arrangeData[$i]['Phone']) ? addslashes(trim($arrangeData[$i]['Phone'])) : '';
                    $entityPermenentInfo[0]['email'] = isset($arrangeData[$i]['Email']) ? addslashes(trim($arrangeData[$i]['Email'])) : '';
                    $entityPermenentInfo[0]['industry'] = isset($arrangeData[$i]['Industry']) ? addslashes(trim($arrangeData[$i]['Industry'])) : '';
                    $entityPermenentInfo[0]['turnover'] = isset($arrangeData[$i]['Estimated Annual Value']) ? addslashes(trim($arrangeData[$i]['Estimated Annual Value'])) : '';
                    $entityPermenentInfo[0]['contract_signed_date'] = isset($arrangeData[$i]['Contract Signed Date']) ? addslashes(trim($arrangeData[$i]['Contract Signed Date'])) : '';
                    $salesPerson = isset($arrangeData[$i]['Sales Person']) ? \App\Models\User::select('id')->where('userfullname', addslashes(trim($arrangeData[$i]['Sales Person'])))->get()->toArray() : array();
                    $entityPermenentInfo[0]['sales_person_id'] = (!empty($salesPerson) && isset($salesPerson[0]['id'])) ? $salesPerson[0]['id'] : 0;
                    $services = isset($arrangeData[$i]['Service To Be Provided']) ? addslashes(trim($arrangeData[$i]['Service To Be Provided'])) : '';
                    $entityPermenentInfo[0]['service_id'] = '';
                    $entityPermenentInfo[0]['created_by'] = 1;
                    $entityPermenentInfo[0]['created_on'] = date('Y-m-d H:i:s');
                    $entitySystemSetupStageService = array();

                    if ($zoho_lead_email != '') {
                        \App\Models\Backend\QuoteMaster::where('lead_email', $zoho_lead_email)->where('stage_id', 12)->update(array('stage_id' => 7, "entity_id", $entityId));
                    }

                    if ($services != '') {
                        $explodeServices = explode(';', $services);
                        // If only SMSF service agree then perm info some stages are not required
                        $isSMSFOnly = 0;
                        if (!empty($explodeServices) && ($explodeServices[0] == 'SMSF' || $explodeServices[0] == 'Existing SMSF Compliance' || $explodeServices[0] == 'NEW SMSF Setup and Compliance')) {
                            $entityPermenentInfo[0]['is_archived'] = 1;
                            $entityPermenentInfo[0]['archived_date'] = date('Y-m-d H:i:s');
                            $isSMSFOnly = 1;
                            $entityStage = [8];
                        } else if (!empty($explodeServices) && $explodeServices[0] == 'Tax') {
                            $entityStage = [3, 5, 8, 10];
                        } else {
                            $stageId = [1, 3, 4, 5, 6, 8, 9, 13];
                            $stageId[] = financialYear();
                            $entityStage = \App\Models\Backend\SystemSetupStage::whereIn('id', $stageId)->pluck('id', 'id')->toArray();
                        }

                        foreach ($entityStage as $keyStage => $valueStage) {
                            $data = array();
                            $data['entity_id'] = $entityId;
                            $data['stage_id'] = $valueStage;
                            $data['status'] = 'N';
                            $entitySystemSetupStage[] = $data;
                        }

                        $serviceIds = array();
                        foreach ($explodeServices as $keyService => $valueService) {
                            $serviceIds[] = $servicesArray[$valueService]['id'];
                            if (isset($servicesArray[$valueService]['id'])) {
                                self::insertIntoWr3($servicesArray[$valueService], $entityId, 1);
                            }
                            $entitySystemSetupStageService[$i]['entity_id'] = $entityId;
                            $entitySystemSetupStageService[$i]['service'] = $valueService;
                            $entitySystemSetupStageService[$i]['status'] = 0;
                        }

                        if ($isSMSFOnly == 1) {
                            $entitySystemSetupStageService[$i]['status'] = 1;
                            $entitySystemSetupStageService[$i]['updated_by'] = 1;
                            $entitySystemSetupStageService[$i]['updated_on'] = date('Y-m-d H:i:s');
                            $wr3 = \App\Models\Backend\WR3::where('entity_id', $entityId)->update(['is_archived' => 1, 'modified_on' => date('Y-m-d H:i:s'), 'modified_by' => 1]);
                        }

                        $billingBasic[0]['entity_id'] = $entityId;
                        $billingBasic[0]['created_by'] = 1;
                        $billingBasic[0]['created_on'] = date('Y-m-d H:i:s');
                        $serviceIds = array_unique($serviceIds);
                        $entityPermenentInfo[0]['service_id'] = implode(',', $serviceIds);

                        $masterChecklist = \App\Models\Backend\MasterChecklist::select('master_checklist.*')->join('master_activity as ma', 'ma.id', '=', 'master_activity_id')->where('ma.is_active', 1)->whereIn('ma.service_id', $serviceIds)->get();
                        if (count($masterChecklist)) {
                            $j = 0;
                            foreach ($masterChecklist as $keyChecklist => $valueChecklist) {
                                $entityCheckoutlist[$j]['master_checklist_id'] = $valueChecklist->id;
                                $entityCheckoutlist[$j]['entity_id'] = $entityId;
                                $entityCheckoutlist[$j]['is_applicable'] = 1;
                                $entityCheckoutlist[$j]['created_by'] = 1;
                                $entityCheckoutlist[$j]['created_on'] = date('Y-m-d H:i:s');
                                $j++;
                            }
                            \App\Models\Backend\EntityChecklist::insert($entityCheckoutlist);
                        }

                        $year = financialYear();
                        $pendingWorksheetScheduleMaster[$j]['entity_id'] = $entityId;
                        $pendingWorksheetScheduleMaster[$j]['year'] = $year;
                        $pendingWorksheetScheduleMaster[$j]['status'] = $isSMSFOnly;
                        $pendingWorksheetScheduleMaster[$j]['modified_by'] = 1;
                        $pendingWorksheetScheduleMaster[$j]['modified_on'] = date('Y-m-d H:i:s');

                        $newEntityReviewForm['entity_id'] = $entityId;
                        $newEntityReviewForm['created_on'] = date('Y-m-d H:i:s');
                        $newEntityReviewForm['created_by'] = 1;

                        $otherEntity = \App\Models\Backend\Button::select(app('db')->raw('GROUP_CONCAT(utr.user_id) as user_id'))->where('tab_button.button_name', 'all_entity')->where('tab_button.visible', 1)->whereRaw('FIND_IN_SET(tab_button.id, utr.other_right)')->join('user_tab_right AS utr', 'tab_button.tab_id', '=', 'utr.tab_id')->get()->toArray();
                        if (isset($otherEntity[0]['user_id']) && $otherEntity[0]['user_id'] != '') {
                            $entityOtherAllocation['entity_id'] = $entityId;
                            $entityOtherAllocation['other'] = '';
                        }
                    }

                    $isPermanentInfo = \App\Models\Backend\PermanentInfo::select('id')->where('entity_id', $entityId)->count();
                    if ($isPermanentInfo == 0)
                        \App\Models\Backend\PermanentInfo::insert($entityPermenentInfo);

                    $isBillingAgree = \App\Models\Backend\Billing::select('id')->where('entity_id', $entityId)->count();
                    if ($isBillingAgree == 0)
                        \App\Models\Backend\Billing::insert($billingBasic);

                    $isSystemSetupEntityStage = \App\Models\Backend\SystemSetupEntityStage::select('id')->where('entity_id', $entityId)->count();
                    if ($isSystemSetupEntityStage == 0)
                        app('db')->table('system_setup_entity_stage')->insert($entitySystemSetupStage);

                    $isEntityStageService = \App\Models\Backend\SystemSetupEntityStageService::select('id')->where('entity_id', $entityId)->count();
                    if ($isEntityStageService == 0)
                        \App\Models\Backend\SystemSetupEntityStageService::insert($entitySystemSetupStageService);

                    $isWorksheetSchedule = \App\Models\Backend\PendingWorksheetScheduleMaster::select('id')->where('entity_id', $entityId)->count();
                    if ($isWorksheetSchedule == 0)
                        \App\Models\Backend\PendingWorksheetScheduleMaster::insert($pendingWorksheetScheduleMaster);

                    $isReviewForm = \App\Models\Backend\NewClientReviewMaster::select('id')->where('entity_id', $entityId)->count();
                    if ($isReviewForm == 0)
                        \App\Models\Backend\NewClientReviewMaster::insert($newEntityReviewForm);

                    //                    $billingTeam = \App\Models\Backend\UserHierarchy::where('team_id', 4)->pluck('user_id', 'id')->toArray();
                    //                    if (!empty($billingTeam)) {
                    //                        $billingTeamid = implode(',', $billingTeam);
                    //                        \App\Models\Backend\EntityAllocationOther::insert(['entity_id' => $entityId, 'other' => $billingTeamid]);
                    //                    }
                    autoAssignAllEntityUser($entityId);

                    $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('OPPORTINUTIESFROMZOHO');
                    if ($emailTemplate->is_active == 1) {
                        $data['to'] = $emailTemplate->to == '' ? $emailTemplate->cc : $emailTemplate->to;
                        $data['from'] = 'noreply-bdms@befree.com.au';
                        $data['cc'] = $emailTemplate->cc;
                        $data['subject'] = $emailTemplate->subject;
                        $data['content'] = str_replace('ENTITYNAME', $entityName, $emailTemplate->content);
                        storeMail('', $data);
                    }
                }
            }
        }
    }

    /* Created By: Jayesh Shingrakhiya
     * Created On: January 10, 2019
     * function for inserting service in wr3 table
     * $skip param for import client form zoho at that time not required of comment
     */

    public static function insertIntoWr3($servicesDetail, $entityId, $skip = 0) {
        if ($servicesDetail['parent_id'] == 0) {
            $wr3 = \App\Models\Backend\WR3::where('entity_id', $entityId)->where('parent_id', $servicesDetail['id'])->get();
            $updateArray = $insertArray = array();
            if (count($wr3) > 0) {
                $service_id = $wr3[0]->service_id . ',' . $servicesDetail['id'];
                if (isset($wr3[0]->parent_id) && ($wr3[0]->parent_id > 0)) {
                    $updateArray['service_id'] = $service_id;
                    $updateArray['is_archived'] = 0;
                } else {
                    $updateArray['service_id'] = $service_id;
                    $updateArray['is_archived'] = 0;
                    $updateArray['parent_id'] = $servicesDetail['id'];
                }
                \App\Models\Backend\WR3::where('id', $wr3[0]->id)->update($updateArray);
            } else {
                $wr3 = \App\Models\Backend\WR3::where('entity_id', $entityId)->whereRaw('(parent_id = 0 OR parent_id IS NULL OR parent_id = "")')->get();
                $service_id = $servicesDetail['id'];
                if (count($wr3) > 0) {
                    $updateArray['service_id'] = $service_id;
                    $updateArray['parent_id'] = $servicesDetail['id'];
                    $updateArray['is_archived'] = 0;
                    \App\Models\Backend\WR3::where('id', $wr3[0]->id)->update($updateArray);
                } else {
                    $insertArray['entity_id'] = $entityId;
                    $insertArray['service_id'] = $service_id;
                    $insertArray['parent_id'] = $servicesDetail['id'];
                    $wr3Added = \App\Models\Backend\WR3::create($insertArray);
                }
            }

            if ($skip == 0) {
                $insertArray['wr_id'] = isset($wr3Added) ? $wr3Added->id : $wr3[0]->id;
                $insertArray['service_id'] = $servicesDetail['id'];
                $insertArray['created_by'] = 1;
                $insertArray['comment'] = $servicesDetail['service_name'] . " is agreed by client";
                $insertArray['created_on'] = date('Y-m-d H:i:s');
                \App\Models\Backend\WR3Comment::insert($insertArray);
            }
        } else {
            $parentService = \App\Models\Backend\Services::where('id', $servicesDetail['parent_id'])->get();
            if (count($parentService) > 0) {
                $wr3 = \App\Models\Backend\WR3::where('entity_id', $entityId)->where('parent_id', $parentService[0]->id)->get();
                if (count($wr3) > 0) {
                    if (isset($wr3[0]->parent_id) && ($wr3[0]->parent_id > 0)) {
                        $service_id = $wr3[0]->service_id . ',' . $parentService[0]->id;
                        $updateArray['service_id'] = trim($service_id, ',');
                        $updateArray['is_archived'] = 0;
                    } else {
                        $service_ids = $wr3[0]->service_ids . ',' . $servicesDetail['id'];
                        $updateArray['service_id'] = trim($service_ids, ',');
                        $updateArray['parent_id'] = $servicesDetail['id'];
                        $updateArray['is_archived'] = 0;
                    }
                    \App\Models\Backend\WR3::where('id', $wr3[0]->id)->update($updateArray);
                    if ($skip == 0) {
                        $insertArray['wr_id'] = $wr3[0]->id;
                        $insertArray['service_id'] = $servicesDetail['id'];
                        $insertArray['created_by'] = 1;
                        $insertArray['comment'] = $servicesDetail['service_name'] . " is agreed by client";
                        $insertArray['created_on'] = date('Y-m-d H:i:s');
                        \App\Models\Backend\WR3Comment::insert($insertArray);
                    }
                } else {
                    $wr3 = \App\Models\Backend\WR3::where('entity_id', $entityId)->whereRaw('(parent_id = 0 OR parent_id IS NULL OR parent_id = "")')->get();
                    if (count($wr3) > 0) {
                        $updateArray['service_id'] = $parentService[0]->id;
                        $updateArray['parent_id'] = $parentService[0]->id;
                        $updateArray['is_archived'] = 0;
                        \App\Models\Backend\WR3::where('id', $wr3[0]->id)->update($updateArray);
                    } else {
                        $insertArray['entity_id'] = $entityId;
                        $insertArray['parent_id'] = $parentService[0]->id;
                        $insertArray['service_id'] = $parentService[0]->id;
                        $wr3Added = \App\Models\Backend\WR3::create($insertArray);
                    }

                    if ($skip == 0) {
                        $insertArray['wr_id'] = isset($wr3Added) ? $wr3Added->id : $wr3[0]->id;
                        $insertArray['service_id'] = $servicesDetail['id'];
                        $insertArray['created_by'] = 1;
                        $insertArray['comment'] = $servicesDetail['service_name'] . " is agreed by client";
                        $insertArray['created_on'] = date('Y-m-d H:i:s');
                        \App\Models\Backend\WR3Comment::insert($insertArray);
                    }
                }
            }
        }
    }

    /* Created By: Jayesh Shingrakhiya
     * Created On: May 24, 2019
     * function for updating entity code in zoho opportunities
     * $id param for account id of zoho account, $entityCode param for BDMS entity code
     */

    public static function Updatezohoopportunity($id, $entityCode) {
        $zoho = config('constant.zoho');
        $tokenArray = \GuzzleHttp\json_decode($zoho['token']);
        $opportunityUpdateUrl = $zoho['opportunityupdateUrl'];
        $scope = $zoho['scope'];

        foreach ($tokenArray AS $token) {
            $xmldata = "<Accounts><row no=\"1\"><FL val=\"Client Code\">" . $entityCode . "</FL></row></Accounts>";
            $param['scope'] = $scope;
            $param['authtoken'] = $token;
            $param['id'] = $id;
            $param['xmlData'] = $xmldata;
            $queryString = self::prepareUrl($param);
            $response = self::curlRequest($opportunityUpdateUrl, $queryString);
        }
        return $response;
    }

}

<?php

namespace App\Http\Controllers\Backend\Entity;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\InformationTrigger;
use DB;

class InformationTriggerController extends Controller {

    /**
     * Store Information Trigger details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $id) {
        //try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'entity_id' => 'required',
            'frequency_id' => 'required',
            'month' => 'required',
            'year' => 'required',
            'trigger_day' => 'required',
            'confirm' => 'required'
                ], []);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        $entityAllocation = \App\Models\Backend\EntityAllocation::select(DB::raw('JSON_VALUE(allocation_json,"$.9") as TAM'))
                        ->where('service_id', 1)
                        ->where("entity_id", $request->input('entity_id'))->first();

        if (isset($entityAllocation->TAM) && ($entityAllocation->TAM == 0 || $entityAllocation->TAM == null)) {
            return createResponse(config('httpResponse.UNPROCESSED'), 'First Allocated TAM for this Client', ['error' => 'First Allocated TAM for this Client']);
        }
        $eID = $request->input('entity_id');
        $entityName = \App\Models\Backend\Entity::select(DB::raw("GROUP_CONCAT(DISTINCT billing_name) as entity_name"))
                        ->whereRaw("id IN ($eID)")->first();
        $month = $request->get('month');
        $year = $request->get('year');
        $startDate = date('01-' . $month . '-' . $year);
        $endDate = date('d-m-Y', strtotime('+2 years', strtotime($startDate)));
        $requestData['month'] = $month;
        $requestData['year'] = $year;
        $requestData['startDate'] = $startDate;
        $requestData['endDate'] = $endDate;
        $requestData['frequencyId'] = $request->get('frequency_id');
        $requestData['entityId'] = $request->get('entity_id');
        $requestData['trigger_day'] = $request->get('trigger_day');
        $recurringData = $this->generateTrigger($requestData, $id);
        if ($request->input('confirm') == 1) {
            return $trigger = $this->addUpdateTrigger($requestData, $recurringData, $id);
        } else {
            $re = $request->all();
            $re['id'] = $id;
            return createResponse(config('httpResponse.SUCCESS'), 'Trigger Preview', ['data' => $recurringData, 'selectedData' => $re, 'entityName' => $entityName->entity_name]);
        }
        /* } catch (\Exception $e) {
          app('log')->error("Information Trigger creation failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add Information Trigger', ['error' => 'Could not add Information Trigger']);
          } */
    }

    public function generateTrigger($requestData, $id) {

        $month = $requestData['month'];
        $year = $requestData['year'];
        $startDate = $requestData['startDate'];
        $endDate = $requestData['endDate'];
        
        $frequency = $requestData['frequencyId'];
        //get frequency
        $frequencyList = \App\Models\Backend\Frequency::find($frequency);
        $frequencyDay = $frequencyList->days;

        $triggerDays = $requestData['trigger_day'];
        $firstDate = strtotime($startDate);
        $lastDate = strtotime($endDate);

        $invDateLogic = ' + ' . $triggerDays . ' days';

        $generateTrigger = array();
        $i = $days = 0;
        $countEnddate = '';
        $temp = true;

        switch ($frequency) {
            case 1:// For Weekly frequency               
            case 2:// For Fortnightly
            case 3:// For monthly frequency
                while ($firstDate <= $lastDate) {
                    // invoice start date
                    $startDate = date('Y-m-d', $firstDate);
                    //invoice end date
                    $days = date("t", $firstDate);
                    if ($firstDate <= $lastDate) {
                        $endDate = date('Y-m-d', strtotime("+" . ($days - 1) . " days", $firstDate));
                        $firstDate = strtotime("+" . ($days) . " days", $firstDate);
                        $triggerDate = date('Y-m-d', strtotime($endDate . $invDateLogic));
                        $triggerDate = date('Y-m-d', strtotime($triggerDate));
                        $generateTrigger[$i]['frequency'] = $frequencyList->frequency_name;
                        $generateTrigger[$i]['startDate'] = $startDate;
                        $generateTrigger[$i]['endDate'] = $endDate;
                        $generateTrigger[$i]['triggerDate'] = $triggerDate;
                    }
                    $i++;
                }
                break;
            case 4:// For quartely frequency
                $f = 0;
                while ($firstDate <= $lastDate) {
                    $startDate = date('Y-m-d', $firstDate);
                    if ($firstDate <= $lastDate) {
                        if ($f == 0) {
                            $startMonth = date('m', $firstDate);
                            if ($startMonth <= 3) {
                                $eDate = date($year.'-03-31');
                            } else if ($startMonth > 3 && $startMonth <= 6) {
                                $eDate = date($year.'-06-30');
                            } else if ($startMonth > 6 && $startMonth <= 9) {
                                $eDate = date($year.'-09-30');
                            } else {
                                $eDate = date($year.'-12-31');
                            }
                            $countEnddate = strtotime($eDate);
                        } else {
                            $countEnddate = strtotime("+ 3 month", $firstDate);
                        }
                        
                        $nextDate = strtotime(date('Y-m-d', strtotime("+ 1 days", $countEnddate)));
                         if ($f == 0) {
                             $endDate = date('Y-m-d', $countEnddate);
                        $firstDate = $nextDate;
                         }else{
                             $endDate = date('Y-m-d', strtotime("- 1 days",$countEnddate));
                           $firstDate = $countEnddate; 
                         }
                        $triggerDate = date('Y-m-d', strtotime($endDate . $invDateLogic));
                        $triggerDate = date('Y-m-d', strtotime($triggerDate));
                        $generateTrigger[$i]['frequency'] = $frequencyList->frequency_name;
                        $generateTrigger[$i]['startDate'] = $startDate;
                        $generateTrigger[$i]['endDate'] = $endDate;
                        $generateTrigger[$i]['triggerDate'] = $triggerDate;
                    }
                    $i++;
                    $f++;
                }
                break;
                case 5:// For yearly frequency
               
                $f = 0;
                //$days = date("t", $firstDate);
                while ($firstDate <= $lastDate) {      
                     
                     $startDate = date('Y-m-d', $firstDate);
                     $year = date("Y", strtotime("+12 months $startDate"));
                    $frequency_day = 365;
                if((0 == $year % 4) & (0 != $year % 100) | (0 == $year % 400)){
                    $frequency_day = 366;
                }
                    if ($firstDate <= $lastDate) {
                       // $endDate = date('Y-m-d', strtotime("+" . ($frequency_day -1) . " days", $firstDate));
                       // $firstDate = strtotime("+" . ($frequency_day) . " days", $firstDate);
                        $endDate = date('Y-m-d', strtotime("+12 months $startDate"));
                        $endDate = date('Y-m-d', strtotime($endDate . "-1 days"));
                        $firstDate = strtotime("+12 months $startDate");
                        $triggerDate = date('Y-m-d', strtotime($endDate . $invDateLogic));
                        $triggerDate = date('Y-m-d', strtotime($triggerDate));
                        $generateTrigger[$i]['frequency'] = $frequencyList->frequency_name;
                        $generateTrigger[$i]['startDate'] = $startDate;
                        $generateTrigger[$i]['endDate'] = $endDate;
                        $generateTrigger[$i]['triggerDate'] = $triggerDate;
                    }
                    $i++;
                    $f++;
                }
                break;
            case 6:// For half monthly frequency
                while ($firstDate <= $lastDate) {
                    $startDate = date('Y-m-d', $firstDate);

                    if ($firstDate <= $lastDate) {
                        $days = date("t", $firstDate);
                        if ($days % 2 == 0) {
                            $startDate = date('Y-m-d', $firstDate);
                            $countEnddate = $TaskEndDate = strtotime("+" . ($days / 2) . " days", $firstDate);
                            $firstDate = strtotime("+" . ($days - $days / 2) . " days", $firstDate);
                        } else {
                            if ($temp) {
                                $startDate = date('Y-m-d', $firstDate);
                                $countEnddate = $TaskEndDate = strtotime("+" . $frequencyDay . " days", $firstDate);
                                $firstDate = strtotime("+" . ($days - $frequencyDay) . " days", $firstDate);
                                $temp = false;
                            } else {
                                $startDate = date('Y-m-d', $firstDate - 1);
                                $countEnddate = $TaskEndDate = strtotime("+" . ($days - $frequencyDay - 1) . " days", $firstDate);
                                $firstDate = strtotime("+" . ($days - $frequencyDay - 1) . " days", $firstDate);
                                $temp = true;
                            }
                        }

                        $endDate = date('Y-m-d', strtotime("-1 days", $countEnddate));
                        $triggerDate = date('Y-m-d', strtotime($endDate . $invDateLogic));
                        $triggerDate = date('Y-m-d', strtotime($triggerDate));
                        $generateTrigger[$i]['frequency'] = $frequencyList->frequency_name;
                        $generateTrigger[$i]['startDate'] = $startDate;
                        $generateTrigger[$i]['endDate'] = $endDate;
                        $generateTrigger[$i]['triggerDate'] = $triggerDate;
                    }
                    $i++;
                }
                break;
        }
        return $generateTrigger;
    }

    /**
     * update Information Trigger details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // Information Trigger id
     * @return Illuminate\Http\JsonResponse
     */
    public function addUpdateTrigger($requestData, $data, $id) {
        //try {
        // store Information Trigger details
        $month = $requestData['month'];
        $year = $requestData['year'];
        $startDate = $requestData['startDate'];
        $endDate = $requestData['endDate'];
        $frequencyId = $requestData['frequencyId'];
        $trigger_day = $requestData['trigger_day'];
        $entityId = $requestData['entityId'];
        if ($id == '0') {
            $loginUser = loginUser();
            $InformationTrigger = \App\Models\Backend\InformationTrigger::create([
                        'entity_id' => $entityId,
                        'frequency_id' => $frequencyId,
                        'month' => $month,
                        'year' => $year,
                        'start_date' => date('Y-m-d',strtotime($startDate)),
                        'end_date' => date('Y-m-d',strtotime($endDate)),
                        'trigger_day' => $trigger_day,                        
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser,
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser
            ]);

            foreach ($data as $key => $value) {
                $triggerInformation['trigger_id'] = $InformationTrigger->id;
                $triggerInformation['start_date'] = $value['startDate'];
                $triggerInformation['end_date'] = $value['endDate'];
                $triggerInformation['trigger_date'] = $value['triggerDate'];
                $triggerInformation['created_by'] = loginUser();
                $triggerInformation['created_on'] = date('Y-m-d H:i:s');
                $triggerRecurringData[] = $triggerInformation;
            }
            $infoTriggerDetail = \App\Models\Backend\InformationTriggerDetail::insert($triggerRecurringData);


            return createResponse(config('httpResponse.SUCCESS'), 'Information Trigger has been added successfully', ['data' => $infoTriggerDetail]);
        } else {
            $InformationTrigger = InformationTrigger::find($id);

            if (!$InformationTrigger)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Information Trigger does not exist', ['error' => 'The Information Trigger does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
             $endDate = date('Y-m-d', strtotime('+2 years', strtotime($startDate)));
            $loginUser = loginUser();
            $updateData['frequency_id'] = $frequencyId;
            $updateData['trigger_day'] = $trigger_day;
            $updateData['month'] = $month;
            $updateData['year'] = $year;
            $updateData['is_stop'] = 0;
            $updateData['start_date'] = date('Y-m-d',strtotime($startDate));
            $updateData['end_date'] = $endDate;
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;
            //update the details
            $InformationTrigger->update($updateData);

            $triggerDetail = \App\Models\Backend\InformationTriggerDetail::where("trigger_id", $id)->get();
            $count = $triggerDetail->count();

            if ($count == count($data)) {//if bath count match then value update else recurring detail value delete and then inser again
                $i = 0;
                foreach ($data as $key => $value) {
                    $triggerData = [
                        'trigger_id' => $id,
                        'start_date' => $value['startDate'],
                        'end_date' => $value['endDate'],
                        'trigger_date' => $value['triggerDate'],
                        'created_by' => loginUser(),
                        'created_on' => date('Y-m-d H:i:s')];
            
                    \App\Models\Backend\InformationTriggerDetail::where("id", $triggerDetail[$i]->id)->update($triggerData);
                    $i++;
                }
            } else {
                \App\Models\Backend\InformationTriggerDetail::where("trigger_id", $id)->delete();
                foreach ($data as $key => $value) {
                    $informationTrigger['trigger_id'] = $id;
                    $InformationTrigger['start_date'] = $value['startDate'];
                    $informationTrigger['end_date'] = $value['endDate'];
                    $informationTrigger['trigger_date'] = $value['triggerDate'];
                    $informationTrigger['created_by'] = loginUser();
                    $informationTrigger['created_on'] = date('Y-m-d H:i:s');
                    $triggerData[] = $informationTrigger;
                }
                $invoiceRecurringDetail = \App\Models\Backend\InformationTriggerDetail::insert($triggerData);
            }
             $currentDate = date('Y-m-d');
             $startDate = date('Y-m-d',strtotime($startDate));
            /*if($startDate < $currentDate){               
                $data = self::generateInformation($entityId);                
            }*/

            return createResponse(config('httpResponse.SUCCESS'), 'Information Trigger has been updated successfully', ['message' => 'Information Trigger has been updated successfully']);
        }
        /* } catch (\Exception $e) {
          app('log')->error("Information Trigger updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update Information Trigger details.', ['error' => 'Could not update Information Trigger details.']);
          } */
    }

    /**
     * get particular Information Trigger details
     *
     * @param  int  $id   //Information Trigger id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $InformationTrigger = InformationTrigger::with('createdBy:userfullname as created_by,id', 'modifiedBy:userfullname as modified_by,id')->where("entity_id", $id)->first();

            if (!isset($InformationTrigger))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Information Trigger does not exist', ['error' => 'The Information Trigger does not exist']);

            //send Information Trigger information
            return createResponse(config('httpResponse.SUCCESS'), 'Information Trigger data', ['data' => $InformationTrigger]);
        } catch (\Exception $e) {
            app('log')->error("Information Trigger details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Information Trigger.', ['error' => 'Could not get Information Trigger.']);
        }
    }
    
     public function stopTrigger($id) {
       // try {
            $InformationTrigger = InformationTrigger::where("id", $id)->update(["is_stop"=>"1"]);
            
            //send Information Trigger information
            return createResponse(config('httpResponse.SUCCESS'), 'Information Trigger stop', ['message' => 'Information Trigger Stop']);
        /*} catch (\Exception $e) {
            app('log')->error("Information Trigger details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Information Trigger.', ['error' => 'Could not get Information Trigger.']);
        }*/
    }
    
    public static function generateInformation($entityId){
         $currentDate = date('Y-m-d');
         $informationDetail = \App\Models\Backend\InformationTrigger::leftjoin("entity as e","e.id","information_trigger.entity_id")
                ->leftjoin("information_trigger_detail as it", "it.trigger_id", "information_trigger.id")
                ->select("it.start_date as sDate", "it.end_date as eDate", "information_trigger.*")
                ->where("information_trigger.is_stop","0")
                ->where("it.trigger_date","<=", $currentDate)
                ->where("information_trigger.entity_id",$entityId)
                ->where("e.discontinue_stage","!=","2");  
        // echo getSQL($informationDetail);exit;
        if ($informationDetail->count() >0) {
            foreach ($informationDetail->get() as $info) {
               
                $startMonth = date('M', strtotime($info->sDate));
                $endMonth = date('M Y', strtotime($info->eDate));
                // check curren client allocation
                $allocation = \App\Models\Backend\EntityAllocation::
                                where('entity_id', $info->entity_id)
                                ->where("service_id", 1)->first();
                //based on month we will generate Subject
                if ($info->frequency_id == 3) {
                    $subject = 'Information Required For '. $endMonth;
                } 
                else if ($startMonth!='' && $endMonth != '') {
                    $subject = 'Information Required For ' . $startMonth . ' TO ' . $endMonth;
                } else {
                    $year = date('Y', strtotime($info->sDate));
                    $subject = 'Information Required For ' . $startMonth . ' ' . $year;
                }

                $bankInfo = \App\Models\Backend\EntityBankInfo::leftjoin("banks as b", "b.id", "entity_bank_info.bank_id")
                        ->leftjoin("bank_type as bt", "bt.id", "entity_bank_info.type_id")
                        ->select("entity_bank_info.id", "b.bank_name", "bt.type_name", "entity_bank_info.account_no", "entity_bank_info.follow_up_notes")
                        ->where("entity_bank_info.entity_id", $info->entity_id)
                        ->where("entity_bank_info.viewing_rights", "0")
                        ->where("entity_bank_info.auto_feed_up", "0")
                        ->where("entity_bank_info.is_active", "1")
                        ->where("b.is_active", "1")
                        ->where("bt.is_active", "1");


                $otherInfo = \App\Models\Backend\EntityOtherInfo::leftjoin("other_account as o", "o.id", "entity_other_info.otheraccount_id")
                        ->select("entity_other_info.id", "o.account_name", "entity_other_info.befree_comment")
                        ->where('entity_other_info.entity_id', $info->entity_id)
                        ->where("entity_other_info.view_access","!=","1")
                        ->where('entity_other_info.is_active', "1")
                        ->where('o.is_active', "1");
                
                if ($bankInfo->count() > 0 || $otherInfo->count() > 0) {
                    $information = \App\Models\Backend\Information::create([
                                'entity_id' => $info->entity_id,
                                'stage_id' => 1,
                                'subject' => $subject,
                                'start_period' => $info->sDate,
                                'end_period' => $info->eDate,
                                'frequency_id' => $info->frequency_id,
                                'team_json' => $allocation->allocation_json,
                                'created_on' => date('Y-m-d H:i:s'),
                                'created_by' => 1]);
                    
                    

                    $infoDetail = $infoOtherDetail = array();
                    // ADD bank info where viewing rights no we will add directly on table
                    // afetr add on this table then change on bank info we will not update in this table

                    foreach ($bankInfo->get() as $bn) {
                        $acc = substr($bn->account_no, -4);
                        $infoDetail[] = array(
                            "information_id" => $information->id,
                            "start_period" => $info->sDate,
                            "end_period" => $info->eDate,
                            "bank_info_id" => $bn->id,
                            "bank_other" => $bn->bank_name,
                            "type_account" => $bn->type_name,
                            "account_no" => $acc,
                            "befree_comment" => '',
                            "status_id" => 0,
                            "created_on" => date('Y-m-d H:i:s'),
                            "created_by" => 1);
                    }
                     \App\Models\Backend\InformationDetail::insert($infoDetail);

                    foreach ($otherInfo->get() as $on) {
                        $infoOtherDetail[] = array(
                            "information_id" => $information->id,
                            "start_period" => $info->sDate,
                            "end_period" =>$info->eDate,
                            "bank_info_id" => $on->id,
                            "bank_other" => $on->account_name,
                            "type_account" => '',
                            "account_no" => '',
                            "befree_comment" => $on->befree_comment,
                            "status_id" => 0,
                            "created_on" => date('Y-m-d H:i:s'),
                            "created_by" => 1);
                    }


                    \App\Models\Backend\InformationDetail::insert($infoOtherDetail);
                    \App\Models\Backend\InformationLog::addLog($information->id, 1, 1);

                    // Create folder for information 
                    $dateDetail = explode("-", $info->eDate);
                    if ($dateDetail[1] > 6) {
                        $year = $dateDetail[0] + 1;
                    } else {
                        $year = $dateDetail[0];
                    }
                    $folderID = \App\Models\Backend\DirectoryEntity::where("entity_id", $info->entity_id)
                                    ->where("year", $year)->where("directory_id", "250")->first();

                    \App\Http\Controllers\Backend\GoogleDrive\GoogleDriveFolderController::createFolderForInformation($subject, $folderID->folder_id, 'information', $information->id);
                }
            }
        }
    }

}

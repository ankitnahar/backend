<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class InformationGenerateCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'info:add';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Information ADD Command';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle($date = null) {
        try {
        if ($date == null) {
            $currentDate = date('Y-m-d');
        } else {
            $currentDate = $date;
        }       
        
        $informationDetail = \App\Models\Backend\InformationTrigger::leftjoin("entity as e", "e.id", "information_trigger.entity_id")
                ->leftjoin("information_trigger_detail as it", "it.trigger_id", "information_trigger.id")
                ->select("it.start_date as sDate", "it.end_date as eDate", "information_trigger.*")
                ->where("information_trigger.is_stop", "0")
                ->where("it.trigger_date", $currentDate)
                ->where("e.discontinue_stage", "!=", "2");
        //showArray(getSQL($informationDetail));
        if ($informationDetail->count() > 0) {
            foreach ($informationDetail->get() as $info) {
                $infoAlready = \App\Models\Backend\Information::where("entity_id", $info->entity_id)->where("start_period", $info->sDate)->where("end_period", $info->eDate)->count();

                //echo 'hi';exit;
                if ($infoAlready > 0) {
                    continue;
                }

                //trigger DAte
                $date = $currentDate;
                if ($info->eDate <= $date) {
                    $infoTable = \App\Models\Backend\InformationTrigger::where('id', $info->id)->first();
                    $month = $infoTable['month'];
                    $year = $infoTable['year'];
                    $startDate = $infoTable['end_date'];
                    $endDate = date('d-m-Y', strtotime('+3 years', strtotime($startDate)));
                    $updateData['end_date'] = $endDate;
                    $updateData['modified_on'] = date('Y-m-d H:i:s');
                    $updateData['modified_by'] = 1;
                    //update the details
                    $infoTable->update($updateData);
                }

                // if Old Information is in pending for TM stage then we willmerge info
                $infomartionData = \App\Models\Backend\Information::where("entity_id", $info->entity_id)->where("stage_id", "1");

                $startMonth = date('M', strtotime($info->sDate));
                $flag = false;
                $endMonth = '';
                //if ($info->count != 0) {
                if ($infomartionData->count() > 0) {
                    $flag=true;
                    $infomartionData1 = $infomartionData->first();
                    $startMonth = date('M', strtotime($infomartionData1->start_period));
                    
                } else {
                    $startMonth = date('M', strtotime($info->sDate));
                }
                
                $endMonth = date('M Y', strtotime($info->eDate));
                //}
                // check curren client allocation
                $allocation = \App\Models\Backend\EntityAllocation::
                                where('entity_id', $info->entity_id)
                                ->where("service_id", 1)->first();
                //based on month we will generate Subject
                if ($info->frequency_id == 3 && $infomartionData->count() == 0) {
                    $subject = 'Information Required For ' . $endMonth;
                } else if ($startMonth != '' && $endMonth != '') {
                    $subject = 'Information Required For ' . $startMonth . ' TO ' . $endMonth;
                } else {
                    $year = date('Y', strtotime($info->sDate));
                    $subject = 'Information Required For ' . $startMonth . ' ' . $year;
                }

                $bankInfo = \App\Models\Backend\EntityBankInfo::leftjoin("banks as b", "b.id", "entity_bank_info.bank_id")
                        ->leftjoin("bank_type as bt", "bt.id", "entity_bank_info.type_id")
                        ->select("entity_bank_info.id", "b.bank_name", "bt.type_name", "entity_bank_info.account_no", "entity_bank_info.notes")
                        ->where("entity_bank_info.entity_id", $info->entity_id)
                        ->where("entity_bank_info.viewing_rights", "0")
                        ->where("entity_bank_info.auto_feed_up", "0")
                        ->where("entity_bank_info.is_active", "1")
                        ->where("b.is_active", "1")
                        ->where("bt.is_active", "1");


                $otherInfo = \App\Models\Backend\EntityOtherInfo::leftjoin("other_account as o", "o.id", "entity_other_info.otheraccount_id")
                        ->select("entity_other_info.id", "o.account_name", "entity_other_info.befree_comment")
                        ->where('entity_other_info.entity_id', $info->entity_id)
                        ->where("entity_other_info.view_access", "!=", "1")
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
                    if ($flag == true) {
                        $oldinfomartion = $infomartionData1;
                        \App\Models\Backend\InformationDetail::where("information_id", $oldinfomartion->id)->update(["information_id" => $information->id]);
                       \App\Models\Backend\Information::where("id", $information->id)->update(["is_merge" => 1, 'modified_on' => date('Y-m-d H:i:s'),
                            'modified_by' => 1]);
                        \App\Models\Backend\Information::where("id", $oldinfomartion->id)->delete();
                    }
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
                            "befree_comment" => $bn->notes,
                            "status_id" => 0,
                            "created_on" => date('Y-m-d H:i:s'),
                            "created_by" => 1);
                    }
                    
                    \App\Models\Backend\InformationDetail::insert($infoDetail);

                    foreach ($otherInfo->get() as $on) {
                        $infoOtherDetail[] = array(
                            "information_id" => $information->id,
                            "start_period" => $info->sDate,
                            "end_period" => $info->eDate,
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
         } catch (Exception $e) {
          $cronName = "Information Add";
          $message = $e->getMessage();
          cronNotWorking($cronName, $message);
          } 
    }

}

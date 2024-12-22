<?php

namespace App\Http\Controllers\Backend\Query;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

class QueryController extends Controller {

    /**
     * Send Back to TL details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function sendBack(Request $request, $id) {
        // try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'stage_id' => 'required',
            'type' => 'required|in:1,2,3',
            'send_back_reason' => 'required'
                ], []);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        $send_back_reason = $request->get('send_back_reason');
        $type = $request->input('type');
        $query = \App\Models\Backend\Query::find($id);
        if (!$query)
            return createResponse(config('httpResponse.NOT_FOUND'), 'Query does not exist', ['error' => 'The Query does not exist']);

        if ($query->stage_id == $request->input('stage_id'))
            return createResponse(config('httpResponse.NOT_FOUND'), 'Can not change stage, Already is in Same stage', ['error' => 'Can not change stage, Already is in Same stage']);

        $reason = 'sendback_reason_tl';
        $by = 'TAM';
        if ($type == '1') {
            $reason = 'sendback_reason_tm';
            $by = 'ATL';
            $informationLog = \App\Models\Backend\InformationLog::addLog($id, 21);
        } else if ($type == '2') {
            $reason = 'sendback_reason_atl';
            $by = 'TL';
            $informationLog = \App\Models\Backend\InformationLog::addLog($id, 22);
        } else {
            $informationLog = \App\Models\Backend\InformationLog::addLog($id, 23);
        }

        \App\Models\Backend\Query::where("id", $id)->update(["stage_id" => $request->input('stage_id'), $reason => $send_back_reason]);

        $entityAllocation = \App\Models\Backend\EntityAllocation::where("entity_id", $query->entity_id)
                        ->where("service_id", 1)->first();
        $decodeAllocation = \GuzzleHttp\json_decode($entityAllocation->allocation_json, true);
        /* $atl = '';
          if (isset($decodeAllocation[61]) && $decodeAllocation[61] != '')
          $atl = $decodeAllocation[61]; */
        if (isset($decodeAllocation[60]) && $decodeAllocation[60] != '')
            $tl = $decodeAllocation[60];
        if (isset($decodeAllocation[10]) && $decodeAllocation[10] != '')
            $staff = $decodeAllocation[10];

        $user = \App\Models\User::select(DB::raw("GROUP_CONCAT(email) as email"))
                ->whereIn("id", [$tl, $staff])
                ->first();

        $entityDetail = \App\Models\Backend\Entity::where('id', $query->entity_id)->first();
        //\App\Models\Backend\Entity::where
        $data['to'] = $user->email;
        $data['cc'] = str_replace(' ', '', $request->input('cc'));
        $data['content'] = 'Hi <br/><br/> Please check below reason <br/> Reason: ' . $send_back_reason;
        $data['subject'] = $entityDetail->trading_name . ' send back by ' . $by;

// add log            
        $QueryLog = \App\Models\Backend\QueryLog::addLog($id, $request->input('stage_id'));

        return createResponse(config('httpResponse.SUCCESS'), 'Query is send back to ' . $by . ' successfully', ['message' => 'Query is assigned to ' . $type . ' successfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Query is send back failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not send back Query', ['error' => 'Could not send back Query']);
          } */
    }

    public function store(Request $request, $id) {

        // try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'worksheet_id' => 'required|numeric',
            'entity_id' => 'required|numeric',
            'bank_list' => 'json',
            'upload' => 'array'
                ], []);

        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
        $worksheet = \App\Models\Backend\Worksheet::find($request->input('worksheet_id'));



        $allocation = \App\Models\Backend\EntityAllocation::where("service_id", "1")->where("entity_id", $request->input('entity_id'))->first();
        $sDate = date("d-m-Y", strtotime($worksheet->start_date));
        $eDate = date("d-m-Y", strtotime($worksheet->end_date));
        $queryData = \App\Models\Backend\Query::where("entity_id", $worksheet->entity_id)->where("stage_id", "1");
        if ($queryData->count() == 0) {
            $query = \App\Models\Backend\Query::create([
                        "entity_id" => $request->input('entity_id'),
                        "worksheet_id" => $request->input('worksheet_id'),
                        "stage_id" => 1,
                        "subject" => "Query For the period " . $sDate . " To " . $eDate,
                        "start_period" => $worksheet->start_date,
                        "end_period" => $worksheet->end_date,
                        "team_json" => $allocation->allocation_json,
                        "created_on" => date("Y-m-d H:i:s"),
                        "created_by" => loginUser(),
                        "modified_on" => date("Y-m-d H:i:s"),
                        "modified_by" => loginUser()]);
            $queryId = $query->id;
            $month = date("m");
            if ($month > 7) {
                $year = date("Y", strtotime("+1 Year"));
            } else {
                $year = date("Y");
            }
            $folderName = "Query For the period " . $sDate . " To " . $eDate;
            $folderID = \App\Models\Backend\DirectoryEntity::where("entity_id", $request->input('entity_id'))
                            ->where("year", $year)->where("directory_id", "251")->first();

            \App\Http\Controllers\Backend\GoogleDrive\GoogleDriveFolderController::createFolderForInformation($folderName, $folderID->folder_id, 'Query', $query->id);
        } else {
            $queryData = $queryData->first();
            $queryId = $queryData->id;
        }
        $queryFolderDetail = \App\Models\Backend\Query::where("id", $queryId)->first();
        if ($queryFolderDetail->folder_id == '' || $queryFolderDetail->folder_id == NULL) {
            \App\Models\Backend\Query::where('id', $queryId)->delete();
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => "Foler id not update so please generate Query again"]);
        }

        $bankList = \GuzzleHttp\json_decode($request->input('bank_list'), true);
        //showArray($bankList);
        if (!empty($bankList)) {
            foreach ($bankList as $row) {
                if ($row['is_checked'] == 1) {
                    $fname = '';
                    if ($row['queryType'] != 1) {
                        $fname = 'upload_' . $row['id'];
                    }
                    $queryBankDetail = \App\Models\Backend\QueryBankDetail::where("query_id", $queryId)->where("bank_info_id", $row['id']);
                    if ($queryBankDetail->count() == 0) {
                        $queryBankDetail = \App\Models\Backend\QueryBankDetail::create([
                                    "query_id" => $queryId,
                                    "bank_info_id" => $row['id'],
                                    "bank_id" => $row['bank_id'],
                                    "start_date" => $row['start_date'],
                                    "end_date" => $row['end_date'],
                                    "file_name" => $fname,
                                    "rows" => $row['rows'],
                                    "created_on" => date("Y-m-d H:i:s"),
                                    "created_by" => loginUser(),
                                    "modified_on" => date("Y-m-d H:i:s"),
                                    "modified_by" => loginUser()
                        ]);
                    } else {
                        $queryBankDetail = $queryBankDetail->first();
                        \App\Models\Backend\QueryBankDetail::where("id", $queryBankDetail->id)->update(["query_id" => $queryId,
                            "bank_info_id" => $row['id'],
                            "bank_id" => $row['bank_id'],
                            "start_date" => $row['start_date'],
                            "end_date" => $row['end_date'],
                            "file_name" => $fname,
                            "rows" => $row['rows'],
                            "modified_on" => date("Y-m-d H:i:s"),
                            "modified_by" => loginUser()]);
                    }
                }

                $lastId = 0;
                $file = $request->file('upload_' . $row['id']);
                if (!empty($file)) {
                    $filename = $file->getPathname();
                    $ro = 0;
                    if (($handle = fopen($filename, "r")) !== FALSE) {
                        $i = 0;
                        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            $num = count($data);
                            if ($ro == 0) {
                                $ro++;
                                continue;
                            }
                            for ($c = 0; $c < $num; $c++) {
                                if ($data[0] == '' || strlen($data[0]) < 7) {
                                    \App\Models\Backend\Query::where("id", $queryId)->delete();
                                    return createResponse(config('httpResponse.UNPROCESSED'), 'Date format not correct, Please add in DDMMYYYY format', ['error' => 'Date format not correct, Please add in DDMMYYYY format']);
                                }
                                if (isset($data[0])) {
                                    $date = rtrim($data[0]);
                                    $date = date("Y-m-d", strtotime($date));
                                }
                                $memo = "";
                                if (isset($data[1])) {
                                    $memo = rtrim($data[1]);
                                }
                                $withdraw = "";
                                if (isset($data[2])) {
                                    $withdraw = rtrim($data[2]);
                                }
                                $deposite = "";
                                if (isset($data[3])) {
                                    $deposite = rtrim($data[3]);
                                }
                                $query = "";
                                if (isset($data[4])) {
                                    $query = rtrim($data[4]);
                                }
                                $gst = "";
                                if (isset($data[5])) {
                                    $gst = rtrim($data[5]);
                                }
                                $answer = "";
                                if (isset($data[6])) {
                                    $answer = rtrim($data[6]);
                                }
                            }
                            $queryDetail = \App\Models\Backend\QueryDetail::create(["query_id" => $queryId,
                                        "bank_info_id" => $row['id'],
                                        "bank_id" => $row['bank_id'],
                                        "transation_date" => $date,
                                        "memo" => $memo,
                                        "withdraw" => $withdraw,
                                        "deposit" => $deposite,
                                        "gst" => $gst,
                                        "status_id" => 0,
                                        "query_comment" => $query,
                                        "merge_start" => ($query != '') ? $ro : '0',
                                        "merge_end" => ($query != '') ? $ro : '0',
                                        "merge_id" => ($query == '') ? $lastId : '0',
                                        "answer_type" => $answer,
                                        "created_on" => date("Y-m-d H:i:s"),
                                        "created_by" => loginUser(),
                                        "modified_on" => date("Y-m-d H:i:s"),
                                        "modified_by" => loginUser()]);
                            if ($query != '') {
                                $lastId = $queryDetail->id;
                                \App\Models\Backend\QueryDetail::where("id", $lastId)->update(["merge_id" => $lastId]);
                            } else if ($lastId > 0) {
                                \App\Models\Backend\QueryDetail::where("id", $lastId)->update(["merge_end" => $ro, "merge_id" => $lastId]);
                            }
                            $ro++;
                        }
                        fclose($handle);
                    }
                } else if ($row['rows'] > 0) {
                    $totalRow = $row['rows'];
                    for ($i = 0; $i < $totalRow; $i++) {
                        \App\Models\Backend\QueryDetail::create(["query_id" => $queryId,
                            "bank_id" => $row['bank_id'],
                            "bank_info_id" => $row['id'],
                            "created_on" => date("Y-m-d H:i:s"),
                            "created_by" => loginUser(),
                            "modified_on" => date("Y-m-d H:i:s"),
                            "modified_by" => loginUser()]
                        );
                    }
                }
            }
            \App\Models\Backend\QueryLog::addLog($queryId, 1, loginUser());
        }

        return createResponse(config('httpResponse.SUCCESS'), 'Query has been added successfully', ['data' => 'upload sucessfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Query updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get query details.', ['error' => 'Could not get query log details.']);
          } */
    }

    public function addQueryLine(Request $request, $query_id) {
        //try{
        $validator = app('validator')->make($request->all(), [
            'bank_id' => 'required|numeric',
            'bank_info_id' => 'required|numeric'
                ], []);
        
        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);


        \App\Models\Backend\QueryDetail::insert(["query_id" => $query_id,
            "bank_id" => $request->input('bank_id'),
            "bank_info_id" => $request->input('bank_info_id')]);
        return createResponse(config('httpResponse.SUCCESS'), 'Query detail has been added successfully', ['data' => 'Query detail has been added sucessfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Query updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get query details.', ['error' => 'Could not get query log details.']);
          } */
    }

    public function showBankAccount(Request $request, $id) {
        // try {
        $validator = app('validator')->make($request->all(), [
            'entity_id' => 'required|numeric'
                ], []);

        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        $QueryBankDetail = \App\Models\Backend\QueryBankDetail::where("query_id", $id)->where("is_active", "1");
        if ($QueryBankDetail->count() == 0) {
            $QueryBankDetail = \App\Models\Backend\EntityBankInfo::leftjoin("banks as b", "b.id", "entity_bank_info.bank_id")
                            ->select("b.bank_name", "entity_bank_info.*")
                            ->where("entity_bank_info.entity_id", $request->input("entity_id"))->where("entity_bank_info.is_active", "1");
        }
        $QueryBankDetail = $QueryBankDetail->get();
        return createResponse(config('httpResponse.SUCCESS'), 'Query Bank Detail', ['data' => $QueryBankDetail]);
        /* } catch (\Exception $e) {
          app('log')->error("Query updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get query details.', ['error' => 'Could not get query log details.']);
          } */
    }

    public function bankActiveInactive(Request $request, $id) {
        // try {

        $QueryBankDetail = \App\Models\Backend\QueryBankDetail::where("bank_id", $id);
        if ($QueryBankDetail->count() > 0) {
            $QueryBankDetail = $QueryBankDetail->first();
            \App\Models\Backend\QueryBankDetail::where("bank_id", $id)->update(["is_active" => "0"]);
            \App\Models\Backend\QueryDetail::where("bank_id", $QueryBankDetail->bank_id)->where("query_id", $QueryBankDetail->query_id)->delete();
        }
        return createResponse(config('httpResponse.SUCCESS'), 'Query Bank Detail', ['data' => $QueryBankDetail]);
        /* } catch (\Exception $e) {
          app('log')->error("Query updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get query details.', ['error' => 'Could not get query log details.']);
          } */
    }

    /**
     * update invoice details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // bank id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        //try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'stage_id' => 'required|numeric',
            'reminder' => 'numeric',
            'bank_details' => 'json'
                ], []);

        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        // Filter the fields which need to be updated
        $loginUser = loginUser();
        $query = \App\Models\Backend\Query::find($id);

        $queryDetail = \GuzzleHttp\json_decode($request->input('bank_details'), true);

        if ($request->has('final_submit') && $request->input('final_submit') == 1) {
            $stage_id = 8;
            foreach ($queryDetail as $row) {
                if (!empty($row['infoDetail'])) {
                    foreach ($row['infoDetail'] as $queryRow) {
                        //showArray($queryRow);exit;

                        if ($queryRow['status_id'] == 1 || $queryRow['status_id'] == 2) {
                            $stage_id = 7;
                        }
                        $queryData['status_id'] = !empty($queryRow['status_id']) ? $queryRow['status_id'] : 0;
                        $queryData['modified_on'] = date('Y-m-d H:i:s');
                        $queryData['modified_by'] = $loginUser;
                        \App\Models\Backend\QueryDetail::where('id', $queryRow['id'])->update($queryData);
                    }
                }
            }

            $updateData['stage_id'] = $stage_id;
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;

            $query->update($updateData);
            \App\Models\Backend\QueryLog::addLog($id, $stage_id, loginUser());

            if ($stage_id == 7) {
                $queryData = \App\Models\Backend\Query::where("entity_id", $query->entity_id)->where("stage_id", "1");
                if ($queryData->count() > 0) {
                    //check Bank
                    $queryData = $queryData->first();
                    $partialDetail = \App\Models\Backend\QueryDetail::where("query_id", $id)->whereIn('status_id', [1, 2])->get();
                    $i = 1;
                    $mergeId = 0;
                    foreach ($partialDetail as $value) {
                        $partialbankDetail = \App\Models\Backend\QueryBankDetail::where("query_id", $queryData->id)
                                ->where('bank_id', $value->bank_id)
                                ->where('bank_info_id', $value->bank_info_id);
                        if ($partialbankDetail->count() == 0) {
                            $partialbankDetail = \App\Models\Backend\QueryBankDetail::where("query_id", $id)
                                            ->where('bank_id', $value->bank_id)
                                            ->where('bank_info_id', $value->bank_info_id)->first();
                            $queryBankDetail = \App\Models\Backend\QueryBankDetail::create([
                                        "query_id" => $queryData->id,
                                        "bank_info_id" => $partialbankDetail->bank_info_id,
                                        "bank_id" => $partialbankDetail->bank_id,
                                        "start_date" => $partialbankDetail->start_date,
                                        "end_date" => $partialbankDetail->end_date,
                                        "created_on" => date("Y-m-d H:i:s"),
                                        "created_by" => loginUser()
                            ]);
                        }
                        if ($mergeId != $value->merge_id) {
                            $firstQueryMerge = \App\Models\Backend\QueryDetail::where("id", $value->merge_id)->first();
                            $countEnd = \App\Models\Backend\QueryDetail::where("merge_id", $value->merge_id)->where('status_id', "2")->count();

                            $value['query_comment'] = $firstQueryMerge->query_comment;
                            $value['merge_start'] = $i;
                            $value['merge_end'] = $i + $countEnd - 1;
                            $value['query_id'] = $queryData->id;
                            $firstId = \App\Models\Backend\QueryDetail::create($value);
                            \App\Models\Backend\QueryDetail::where("id", $firstId)->update(["merge_id" => $firstId]);
                            $mergeId = $value->merge_id;
                        } else {
                            $value['query_id'] = $queryData->id;
                            $value['merge_id'] = $firstId;
                            \App\Models\Backend\QueryDetail::create($value);
                        }

                        $i++;
                    }

                    \App\Models\Backend\Query::where("id", $queryData->id)->update(["is_merge" => 1]);
                } else {
                    $queryCopy = \App\Models\Backend\Query::create([
                                'entity_id' => $query->entity_id,
                                "worksheet_id" => $query->worksheet_id,
                                'stage_id' => 1,
                                'subject' => $query->subject,
                                'start_period' => $query->start_period,
                                'end_period' => $query->end_period,
                                'team_json' => $query->team_json,
                                'is_partial' => 1,
                                'created_on' => date('Y-m-d H:i:s'),
                                'created_by' => 1]);

                    $partialDetailValue = \App\Models\Backend\QueryDetail::where("query_id", $id)->whereIn("status_id", [1, 2])->get();
                    $i = 1;
                    $mergeId = 0;
                    foreach ($partialDetailValue as $p) {
                        $new = \App\Models\Backend\QueryBankDetail::
                                        where('bank_id', $p->bank_id)
                                        ->where('bank_info_id', $p->bank_info_id)->where("query_id", $queryCopy->id);
                        if ($new->count() == 0) {
                            $partialbankDetail = \App\Models\Backend\QueryBankDetail::
                                            where('bank_id', $p->bank_id)
                                            ->where('bank_info_id', $p->bank_info_id)->where("query_id", $id)->first();
                            $queryBankDetail = \App\Models\Backend\QueryBankDetail::create([
                                        "query_id" => $queryCopy->id,
                                        "bank_info_id" => $partialbankDetail->bank_info_id,
                                        "bank_id" => $partialbankDetail->bank_id,
                                        "start_date" => $partialbankDetail->start_date,
                                        "end_date" => $partialbankDetail->end_date,
                                        "created_on" => date("Y-m-d H:i:s"),
                                        "created_by" => loginUser()
                            ]);
                        }
                        if (!empty($partialDetailValue)) {
                            $insertArray = array(
                                "query_id" => $queryCopy->id,
                                "bank_info_id" => $p->bank_info_id,
                                "bank_id" => $p->bank_id,
                                "transation_date" => $p->transation_date,
                                "memo" => $p->memo,
                                "withdraw" => $p->withdraw,
                                "deposit" => $p->deposit,
                                "gst" => $p->gst,
                                "status_id" => 0,
                                "query_comment" => $p->query_comment,
                                "answer_type" => $p->answer_type,
                                "created_on" => date("Y-m-d H:i:s"),
                                "created_by" => loginUser(),
                                "modified_on" => date("Y-m-d H:i:s"),
                                "modified_by" => loginUser());
                            if ($mergeId != $p->merge_id) {
                                $firstQueryMerge = \App\Models\Backend\QueryDetail::where("id", $p->merge_id)->first();
                                $countEnd = \App\Models\Backend\QueryDetail::where("merge_id", $p->merge_id)->where('status_id', "2")->count();
                                $insertArray['query_comment'] = $firstQueryMerge->query_comment;
                                $insertArray['merge_start'] = $i;
                                $insertArray['merge_end'] = $i + $countEnd - 1;
                                $firstId = \App\Models\Backend\QueryDetail::create($insertArray);
                                \App\Models\Backend\QueryDetail::where("id", $firstId)->update(["merge_id" => $firstId]);
                                $mergeId = $p->merge_id;
                            } else {
                                $insertArray['merge_id'] = $firstId;
                                \App\Models\Backend\QueryDetail::create($insertArray);
                            }
                            $i++;
                        }
                    }
                }
            }
        } else {
            if (!empty($queryDetail)) {
                foreach ($queryDetail as $row) {
                    if (!empty($row['infoDetail'])) {
                        foreach ($row['infoDetail'] as $queryRow) {
                            //showArray($queryRow);exit;
                            if ($request->input('stage_id') == 5 && $queryRow['status_id'] == 2 && empty($queryRow['status_comment'])) {

                                return createResponse(config('httpResponse.UNPROCESSED'), "Befree comment not blank", ['error' => "Befree comment not blank"]);
                            }
                            $queryData['query_comment'] = !empty($queryRow['query_comment']) ? $queryRow['query_comment'] : '';
                            $queryData['answer_type'] = !empty($queryRow['answer_type']) ? $queryRow['answer_type'] : '';
                            $queryData['status_comment'] = !empty($queryRow['status_comment']) ? $queryRow['status_comment'] : '';
                            $queryData['status_id'] = !empty($queryRow['status_id']) ? $queryRow['status_id'] : 0;
                            $queryData['transation_date'] = $queryRow['transation_date'];
                            $queryData['memo'] = $queryRow['memo'];
                            $queryData['withdraw'] = $queryRow['withdraw'];
                            $queryData['deposit'] = $queryRow['deposit'];
                            $queryData['gst'] = $queryRow['gst'];
                            $queryData['deposit'] = $queryRow['deposit'];
                            $queryData['modified_on'] = date('Y-m-d H:i:s');
                            $queryData['modified_by'] = $loginUser;
                            \App\Models\Backend\QueryDetail::where('id', $queryRow['id'])->update($queryData);
                        }
                    }
                }
            }

            $checkALLREsolved = \App\Models\Backend\QueryDetail::where("query_id", $id)->whereRaw("status_id NOT IN (5,3)");
            $checkAllAditionalQuery = \App\Models\Backend\QueryAdditionalInfo::where("query_id", $id)->where("is_deleted", "=", "0");
            if ($checkALLREsolved->count() == 0 && $checkAllAditionalQuery->count() == 0) {
                \App\Models\Backend\Query::where("id", $id)->update(["stage_id" => 8]);
                \App\Models\Backend\QueryLog::addLog($id, 8, loginUser());
            } else {

                $queryBank = \App\Models\Backend\QueryBankDetail::whereRaw("file_name !=''")->where("query_id", $id)->get();
                //showArray(getSQL($queryBank));exit;
                foreach ($queryBank as $qb) {
                    $queryDetailNew = \App\Models\Backend\QueryDetail::where("query_id", $id)->where('bank_id', $qb->bank_id)
                                    ->where("bank_info_id", $qb->bank_info_id)->where("status_id", "!=", "5")->get();
                    $i = 1;
                    $startId = 0;
                    foreach ($queryDetailNew as $q) {
                        if ($startId != $q->merge_id) {
                            $checkResolve = \App\Models\Backend\QueryDetail::where("merge_id", $q->merge_id)->where("status_id", "5")->count();
                            $QueryMergeDetail = \App\Models\Backend\QueryDetail::where("merge_id", $q->merge_id);
                            $clientMergestart = $i;
                            $totalQueryMerge = $QueryMergeDetail->count();
                            $clientMergeEnd = ($i + $totalQueryMerge - 1) - $checkResolve;
                            $comment = $QueryMergeDetail->orderBy("id", "asc")->first();
                            \App\Models\Backend\QueryDetail::where("id", $q->id)->update(["query_comment" => $comment->query_comment,
                                "client_merge_start" => $clientMergestart, 'client_merge_end' => $clientMergeEnd]);
                            $startId = $q->merge_id;
                        }
                        $i++;
                    }
                }
                //exit;
                if ($request->input('stage_id') != 5) {
                    $updateData['reminder'] = $request->input('reminder');
                    if ($request->input('reminder') > 0) {
                        $todayDate = date("Y-m-d");
                        $updateData['reminder_date'] = date("Y-m-d", strtotime("+" . $request->input('reminder') . " day"));
                    }
                }
                $updateData['subject'] = $request->input('subject');
                $updateData['stage_id'] = $request->input('stage_id');
                $updateData['modified_on'] = date('Y-m-d H:i:s');
                $updateData['modified_by'] = $loginUser;

                $query->update($updateData);
                \App\Models\Backend\QueryLog::addLog($id, $request->input('stage_id'), loginUser());
            }
        }
        return createResponse(config('httpResponse.SUCCESS'), 'Query has been updated successfully', ['message' => 'Query has been updated successfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Query updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get query details.', ['error' => 'Could not get query log details.']);
          } */
    }

    public function showQueryDetails(Request $request, $id) {
        //try {
        $queryDetail = \App\Models\Backend\QueryDetail::with("createdBy:id,userfullname as created_by")
                        ->select("query_detail.*")
                        ->where('id', '=', $id)->get();

        foreach ($queryDetail as $qDetail) {
            $qDetail['documents'] = array();
            $queryDocument = \App\Models\Backend\QueryDetailDocument::with('createdBy:id,userfullname,email')
                    ->leftJoin('directory_entity_file as df', function($query) {
                        $query->on('df.file_id', '=', 'query_detail_document.document_name');
                        $query->on('df.move_to_trash', '=', DB::raw("0"));
                    })
                    ->select("query_detail_document.id", "query_detail_document.is_drive", "query_detail_document.document_path", "query_detail_document.is_client", DB::raw("IF(query_detail_document.is_drive=0,query_detail_document.document_name,df.file_name) as document_name"), "df.file_id", "df.csv_excel_file_id", "df.mime_type", "df.size")
                    ->where('query_detail_id', $qDetail->id);
            if ($queryDocument->count() > 0) {
                $queryDocument = $queryDocument->get();
                $qDetail['documents'] = $queryDocument;
            }
        }

        return createResponse(config('httpResponse.SUCCESS'), 'Query Detail data', ['data' => $queryDetail]);
        /* } catch (\Exception $e) {
          app('log')->error("Query log updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get query details details.', ['error' => 'Could not get query details details.']);
          } */
    }

    /**
     * log particular Query
     *
     * @param  int  $id   //invoice id
     * @return Illuminate\Http\JsonResponse
     */
    public function log(Request $request, $id) {
        //try {
        $querylog = \App\Models\Backend\QueryLog::with("statusId:id,status_name")
                ->where("query_id", $id);
        $log = array();
        if ($querylog->count() == 0)
            return createResponse(config('httpResponse.NOT_FOUND'), 'The Query Log does not exist', ['error' => 'The Query Log does not exist']);
       
        foreach ($querylog->get() as $q) {            
             if ($q->status_id == 6) {
                $q->modified_by = array("id" =>$q->modified_by, "modified_by" => 'Sent by client');
            }else{
                $userName = \App\Models\User::where("id",$q->modified_by)->select("userfullname");
                if($userName->count() > 0){
                    $userName = $userName->first();
                 $q->modified_by = array("id" =>$q->modified_by,"modified_by" => $userName->userfullname);
                }else if($q->status_id == 5){
                 $q->modified_by = array("id" =>$q->modified_by,"modified_by" => "Pending from client");   
                }
            }
            $log[] = $q;
            
        }
        //query log
        return createResponse(config('httpResponse.SUCCESS'), 'Query Log data', ['data' => $log]);
        /* } catch (\Exception $e) {
          app('log')->error("Query log updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get query log details.', ['error' => 'Could not get query log details.']);
          } */
    }

    public function reminderLog(Request $request, $id) {
        //try {
        $querylog = \App\Models\Backend\QueryReminderLog::with("createdBy:id,userfullname as created_by")
                ->where("query_id", $id);
        if ($querylog->count() == 0)
            return createResponse(config('httpResponse.NOT_FOUND'), 'The Query Reminder Log does not exist', ['error' => 'The Query Reminder Log does not exist']);

        //query log
        return createResponse(config('httpResponse.SUCCESS'), 'Query Reminder Log data', ['data' => $querylog->get()]);
        /* } catch (\Exception $e) {
          app('log')->error("Query Reminder log updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get query Reminder log details.', ['error' => 'Could not get query Reminder log details.']);
          } */
    }

    /**
     * Query Stages particular Query
     *
     * @param  int  $id   //Query id
     * @return Illuminate\Http\JsonResponse
     */
    public function queryStage() {
        try {
            $queryStage = \App\Models\Backend\QueryStage::where('is_active', '=', 1)
                            ->where('applicable', '=', 1)
                            ->orderBy("id")->get();
            //Query log
            return createResponse(config('httpResponse.SUCCESS'), 'Query Stages List', ['data' => $queryStage]);
        } catch (\Exception $e) {
            app('log')->error("Query stage detail failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Query stage details.', ['error' => 'Could not get Query stage details.']);
        }
    }

    /**
     * Query Move to TL/STtaff
     *
     * @param  int  $id   //query id
     * @return Illuminate\Http\JsonResponse
     */
    public function moveToTlTam(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'stage_id' => 'required',
                'type' => 'required|in:1,2,3'
                    ], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $query = \App\Models\Backend\Query::find($id);

            if (!$query)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Query does not exist', ['error' => 'The Query does not exist']);

            $currentInfoStage = $query->stage_id;

            if ($request->has('type') == 1 && $query->stage_id != '2') {
                $type = 'ATL';
                $sId = 10;
            } else if ($request->has('type') == 2 && $query->stage_id != '3') {
                $type = 'TL';
                $sId = 11;
            } else if ($request->has('type') == 3 && $query->stage_id != '4') {
                $type = 'TAM';
                $sId = 12;
            }
            \App\Models\Backend\QueryLog::addLog($id, $sId);
            $query->update(["stage_id" => $request->input('stage_id')]);
            \App\Models\Backend\QueryLog::addLog($id, $request->input('stage_id'));

            return createResponse(config('httpResponse.SUCCESS'), 'Query is assigned to ' . $type . ' successfully', ['message' => 'Query is assigned to ' . $type . ' successfully']);
        } catch (\Exception $e) {
            app('log')->error("Query Stage not change " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update query stage', ['error' => 'Could not update query stage']);
        }
    }

    /**
     * get particular Query List details
     *
     * @param  int  $id   //Query id
     * @return Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id) {
        // try {
        $queryGroup = array();

        $query = \App\Models\Backend\Query::queryData()->find($id);
        if (!empty($query)) {
            $queryGroup['basic'] = $query;
        }

        $queryBankDetail = \App\Models\Backend\QueryBankDetail::with("createdBy:id,userfullname as created_by")
                ->leftjoin("banks as b", "b.id", "query_bank_detail.bank_id")
                ->leftjoin("entity_bank_info as bi", "bi.id", "query_bank_detail.bank_info_id")
                ->select("query_bank_detail.*", "b.bank_name", "bi.account_no")
                ->where('query_id', '=', $id)
                ->get();
        foreach ($queryBankDetail as $queryBank) {
            $queryGroup[$queryBank->bank_info_id] = $queryBank;
            $queryDetail = \App\Models\Backend\QueryDetail::with("createdBy:id,userfullname as created_by")
                    ->select("query_detail.*")
                    ->where('bank_info_id', '=', $queryBank->bank_info_id)
                    ->where('query_id', '=', $id)
                    ->get();
            foreach ($queryDetail as $qDetail) {
                $qDetail['documents'] = array();
                $queryDocument = \App\Models\Backend\QueryDetailDocument::with('createdBy:id,userfullname,email')
                        ->leftJoin('directory_entity_file as df', function($query) {
                            $query->on('df.file_id', '=', 'query_detail_document.document_name');
                            $query->on('df.move_to_trash', '=', DB::raw("0"));
                        })
                        ->select("query_detail_document.id", "query_detail_document.is_drive", "query_detail_document.document_path", "query_detail_document.is_client", DB::raw("IF(query_detail_document.is_drive=0,query_detail_document.document_name,df.file_name) as document_name"), "df.file_id", "df.csv_excel_file_id", "df.mime_type", "df.size")
                        ->where('query_detail_id', $qDetail->id);
                if ($queryDocument->count() > 0) {
                    $queryDocument = $queryDocument->get();
                    $qDetail['documents'] = $queryDocument;
                }
            }
            if (!empty($queryDetail)) {
                $queryGroup[$queryBank->bank_info_id]['detail'] = $queryDetail;
            }
        }


        $addQueryDetail = \App\Models\Backend\QueryAdditionalInfo::with('createdBy:id,userfullname as created_by')
                ->select("query_additional_info.id", "query_additional_info.comment")
                ->where('query_id', '=', $id)
                ->get();
        $queryAddInfo = array();
        if (!empty($addQueryDetail)) {
            foreach ($addQueryDetail as $qInfo) {
                $qInfo['documents'] = array();
                $addInfoDocument = \App\Models\Backend\QueryAdditionalDocument::with('createdBy:id,userfullname,email')
                        ->leftJoin('directory_entity_file as df', function($query) {
                            $query->on('df.file_id', '=', 'query_additional_document.document_name');
                            $query->on('df.move_to_trash', '=', DB::raw("0"));
                        })
                        ->select("query_additional_document.id", "query_additional_document.document_path", "query_additional_document.is_drive", "query_additional_document.is_client", DB::raw("IF(query_additional_document.is_drive=0,query_additional_document.document_name,df.file_name) as document_name"), "df.file_id", "df.csv_excel_file_id", "df.mime_type", "df.size")
                        ->where('query_add_id', $qInfo->id);
                if ($addInfoDocument->count() > 0) {
                    $addInfoDocument = $addInfoDocument->get();
                    $qInfo['documents'] = $addInfoDocument;
                }
                $queryAddInfo[] = $qInfo;
            }
        }
        if (!empty($queryAddInfo)) {
            $queryGroup['adddetail'] = $queryAddInfo;
        }

        if (!isset($query))
            return createResponse(config('httpResponse.NOT_FOUND'), 'The Query List does not exist', ['error' => 'The Query does not exist']);


        //send query
        return createResponse(config('httpResponse.SUCCESS'), 'Query List data', ['data' => $queryGroup]);
        /* } catch (\Exception $e) {
          app('log')->error("Query List details api failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Query List.', ['error' => 'Could not get Query List.']);
          } */
    }

    /**
     * Created by: Vivek Parmar
     * Created on: March 28,  2020
     * Fetch query assignee user
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function infoAssignee(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'query_id' => 'required'], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $queryId = $request->get('query_id');
            $updateData = array();
            if ($request->get('additional_tm') != '' || !empty($request->get('additional_tl'))) {
                $info = \App\Models\Backend\Query::find($queryId);
                $additionalTL = $request->get('additional_tl');
                $additionalTM = $request->get('additional_tm');
                if (isset($additionalTL)) {
                    $updateData['additional_tl'] = $additionalTL;
                }

                if (isset($additionalTM)) {
                    $updateData['additional_tm'] = $additionalTM;
                }
                $info->update($updateData);
            }

            return createResponse(config('httpResponse.SUCCESS'), "Succesfully additional users assign", ['message' => 'Succesfully additional users assign']);
        } catch (\Exception $e) {
            app('log')->error("Dropdown listing fail : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not fetch dropdown listing.', ['error' => 'Could not fetch dropdown listing.']);
        }
    }

    /**
     * Created by: Vivek Parmar
     * Created on: April 02,  2020
     * Fetch query assignee user
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function snoozeInfo(Request $request,$id) {
       // try {
            $validator = app('validator')->make($request->all(), [
                'snooze' => 'required|numeric',
                'reminder' => 'required|numeric'], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);
            $updateData = array();
            $query = \App\Models\Backend\Query::find($id);

            $updateData['reminder'] = !empty($request->input('reminder')) ? $request->input('reminder') : '';
            $updateData['snooze'] = !empty($request->input('snooze')) ? $request->input('snooze') : '';

            $query->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Query has been updated successfully', ['message' => 'Query has been updated successfully']);
        /*} catch (\Exception $e) {
            app('log')->error("Snooze info fail : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not fetch snooze info.', ['error' => 'Could not fetch snooze info.']);
        }*/
    }

    /**
     * Created by: Vivek Parmar
     * Created on: April 03,  2020
     * Fetch snooz dropdown user
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function snnozDropdown(Request $request, $id) {
        try {

            $snoozReminder = array();
            // define soring parameters

            if (!empty($id)) {
                $info = \App\Models\Backend\Query::find($id);
                if (!empty($info)) {
                    $snoozReminder['snooze'] = $info->snooze;
                    $snoozReminder['reminder'] = $info->reminder;
                }
            }

            return createResponse(config('httpResponse.SUCCESS'), "Query reminder value list.", ['data' => array('reminder' => $snoozReminder)]);
        } catch (\Exception $e) {
            app('log')->error("Dropdown listing fail : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not fetch dropdown listing.', ['error' => 'Could not fetch dropdown listing.']);
        }
    }

    public function removeQuery($id) {
        // try {
        $queryDetail = \App\Models\Backend\Query::find($id);
        if (!$queryDetail)
            return createResponse(config('httpResponse.NOT_FOUND'), 'The query does not exist', ['error' => 'The query does not exist']);

        \App\Models\Backend\QueryDetail::where("query_id", $id)->delete();
        //\App\Models\Backend\QueryDetailDocument::where("query_id",$id)->delete();
        \App\Models\Backend\QueryAdditionalInfo::where("query_id", $id)->delete();
        // \App\Models\Backend\QueryAdditionalDocument::where("query_id",$id)->delete();
        $queryDetail->delete();


        return createResponse(config('httpResponse.SUCCESS'), 'Query has been deleted successfully', ['message' => 'Query has been deleted successfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Query download failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete Query.', ['error' => 'Could not delete Query.']);
          } */
    }

    public function destroy(Request $request, $id) {
        try {
            $QueryDetail = \App\Models\Backend\QueryDetail::find($id);
            // Check weather additional Query exists or not
            if (!isset($QueryDetail))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Query Document does not exist', ['error' => 'Query Document does not exist']);

            \App\Models\Backend\QueryDetailDocument::where("query_detail_id", $id)->delete();
            $QueryDetail->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Query line has been deleted successfully', ['message' => 'Query line has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Query Document deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete Query line.', ['error' => 'Could not delete Query line.']);
        }
    }

}

?> 
<?php

namespace App\Http\Controllers\Backend\BulkAllocation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BulkAllocationController extends Controller {

    /**
     * Created by: Jayesh Shingrakhiya
     * Created on: Nov 29, 2018
     * Purpose: Modified bulk allocation user details
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function allocation(Request $request) {
       // try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'required',
                'services' => 'required',
                'new_user_id' => 'required|numeric']);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $entityId = explode(",", $request->get('entity_id'));
            $services = explode(",", $request->get('services'));
            $newUserId = $request->get('new_user_id');

            if (in_array(0, $services)) {
                $otherAllocationOther = \App\Models\Backend\EntityAllocationOther::select('id', 'other')->whereIn('entity_id', $entityId)->get();
                $otherEntityAllocation = array();
                foreach ($otherAllocationOther as $key => $value) {
                    $other = array();
                    if ($value->other != '')
                        $other = explode(',', $value->other);

                    if (!in_array($newUserId, $other)) {
                        $other[] = $newUserId;
                        app('db')->table('entity_allocation_other')->where('id', $value->id)->update(['other' => implode(",", $other)]);
                    }
                }
                goto end;
            }

            if (!empty($services)) {
                $allocation = \App\Models\Backend\EntityAllocation::select('id', 'allocation_json')->whereIn('service_id', $services)->whereIn('entity_id', $entityId)->get();

                $designation = \App\Models\Backend\UserHierarchy::getUserHierarchy($newUserId);
                foreach ($allocation as $keyAllocation => $valueAllocation) {
                    $allocationJsondecode = array();
                    if($valueAllocation->allocation_json!=''){
                    $allocationJsondecode = \GuzzleHttp\json_decode($valueAllocation->allocation_json, true);
                    $allocationJsondecode[$designation[$newUserId]] = $newUserId;
                    }
                    app('db')->table('entity_allocation')->where('id', $valueAllocation->id)->update(['allocation_json' => \GuzzleHttp\json_encode($allocationJsondecode)]);
                }
            }

            end:
            return createResponse(config('httpResponse.SUCCESS'), 'Bulk allocation operation performed successfully', ['message' => 'Bulk allocation operation performed successfully']);
        /*} catch (\Exception $e) {
            app('log')->error("Bulk allocation operation performed failed" . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not bulk allocation operation perform', ['error' => 'Could not bulk allocation operation perform']);
        }*/
    }

    /**
     * Created by: Jayesh Shingrakhiya
     * Created on: Nov 16, 2018
     * Purpose: Fetch allocate user entity list
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function entityList(Request $request, $id) {
        try {
            $entityAllocationOther = \App\Models\Backend\EntityAllocationOther::WhereRaw("FIND_IN_SET($id,other)")->leftjoin('entity AS e', 'e.id', 'entity_id')->where('e.discontinue_stage', '!=', 2)->groupBy("entity_id")->orderBy('e.trading_name', 'asc')->pluck("e.trading_name", "e.id")->toArray();

            $entityAllocation = \App\Models\Backend\EntityAllocation::whereRaw("JSON_SEARCH(allocation_json, 'all', '$id') IS NOT NULL")->leftjoin('entity AS e', 'e.id', 'entity_id')->where('e.discontinue_stage', '!=', 2)->groupBy("entity_id")->orderBy('e.trading_name', 'asc')->pluck("e.trading_name", "e.id")->toArray();

            $mergeAllcation = array_unique(array_filter($entityAllocation + $entityAllocationOther));
            $entityList = array();
            foreach ($mergeAllcation as $key => $value) {
                $entityList[] = array('id' => $key, 'trading_name' => $value);
            }

            return createResponse(config('httpResponse.SUCCESS'), "Entity list.", ['data' => $entityList]);
        } catch (\Exception $e) {
            app('log')->error("Add conference room failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not fetch client detail', ['error' => 'Could not fetch client detail']);
        }
    }

    /**
     * Created by: Jayesh Shingrakhiya
     * Created on: Nov 16, 2018
     * Purpose: Fetch user services
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function allocatedService($id) {
        try {
            $allocatedTeam = \App\Models\Backend\UserHierarchy::select('team_id')->where('user_id', $id)->first();
            $allocatedTeam = explode(',', $allocatedTeam->team_id);

            $allocatedService = \App\Models\Backend\Team::whereIn('id', $allocatedTeam)->whereIn('service_id', [1, 2, 6])->pluck('team_name', 'service_id')->toArray();
            //if (empty($allocatedService))

            $allocatedService[0] = 'Other';

            $serviceList = array();
            foreach ($allocatedService as $key => $value) {
                $serviceList[] = array('id' => $key, 'service_name' => $value);
            }

            return createResponse(config('httpResponse.SUCCESS'), "User services load", ['data' => $serviceList]);
        } catch (\Exception $e) {
            app('log')->error("Add conference room failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not fetch user services', ['error' => 'Could not fetch user services']);
        }
    }

    /**
     * Created by: Jayesh Shingrakhiya
     * Created on: Nov 29, 2018
     * Purpose: De allocation user from allocation
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function deallocation(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'user_id' => 'required|numeric',
                'entity_id' => 'required',
                'services' => 'required']);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $userId = $request->get('user_id');
            $entityId = explode(",", $request->get('entity_id'));
            $services = explode(",", $request->get('services'));

            if (in_array(0, $services)) {
                $otherAllocationOther = \App\Models\Backend\EntityAllocationOther::select('id', 'other')->whereIn('entity_id', $entityId)->get();
                $otherEntityAllocation = array();
                foreach ($otherAllocationOther as $key => $value) {
                    $other = array();
                    $other = explode(',', $value->other);
                    $isUpdateOtherAllocation = 0;
                    foreach ($other as $keyOther => $valueOther) {
                        if ($valueOther == $userId) {
                            $other[$keyOther] = 0;
                            $isUpdateOtherAllocation = 1;
                        }
                    }

                    if ($isUpdateOtherAllocation == 1)
                        app('db')->table('entity_allocation_other')->where('id', $value->id)->update(['other' => implode(",", $other)]);
                }
                $key = array_search(0, $services);
                if (false !== $key) {
                    unset($services[$key]);
                }
            }

            if (!empty($services)) {
                $allocation = \App\Models\Backend\EntityAllocation::select('id', 'allocation_json')->whereIn('service_id', $services)->whereIn('entity_id', $entityId)->get();

                foreach ($allocation as $keyAllocation => $valueAllocation) {
                    $allocationJsondecode = array();
                    $allocationJsondecode = \GuzzleHttp\json_decode($valueAllocation->allocation_json, true);
                    $isUpdateAllocation = 0;
                    foreach ($allocationJsondecode as $keyReallocation => $valueReallocation) {
                        if ($valueReallocation == $userId) {
                            $allocationJsondecode[$keyReallocation] = 0;
                            $isUpdateAllocation = 1;
                        }
                    }

                    if ($isUpdateAllocation == 1)
                        app('db')->table('entity_allocation')->where('id', $valueAllocation->id)->update(['allocation_json' => \GuzzleHttp\json_encode($allocationJsondecode)]);
                }
            }

            return createResponse(config('httpResponse.SUCCESS'), 'Bulk allocation operation performed successfully', ['message' => 'Bulk allocation operation performed successfully']);
        } catch (\Exception $e) {
            app('log')->error("Bulk allocation operation performed failed" . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not bulk allocation operation perform', ['error' => 'Could not bulk allocation operation perform']);
        }
    }

    /**
     * Created by: Jayesh Shingrakhiya
     * Created on: May 16, 2019
     * Purpose: Fetch entity id using their code
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function fetchEntity(Request $request) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'user_id' => 'required|numeric']);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        $userId = $request->get('user_id');
        $GLOBALS['entityId'] = '';
        \Maatwebsite\Excel\Facades\Excel::load($request->file('document_file'), function ($reader) use($userId) {
            $entityCode = array();
            foreach ($reader->toArray() as $row) {
                $entityCode[] = $row['client_code'];
            }

            if ($userId != 0) {
                $entityId = \App\Models\Backend\Entity::whereIn('code', $entityCode)->pluck('id', 'id')->toArray();

                $entityAllocationOther = \App\Models\Backend\EntityAllocationOther::whereIn('entity_id', $entityId)->WhereRaw("FIND_IN_SET($userId,other)")->leftjoin('entity AS e', 'e.id', 'entity_id')->groupBy("entity_id")->orderBy('e.trading_name', 'asc')->pluck("e.trading_name", "e.id")->toArray();

                $entityAllocation = \App\Models\Backend\EntityAllocation::whereIn('entity_id', $entityId)->whereRaw("JSON_SEARCH(allocation_json, 'all', '$userId') IS NOT NULL")->leftjoin('entity AS e', 'e.id', 'entity_id')->groupBy("entity_id")->orderBy('e.trading_name', 'asc')->pluck("e.trading_name", "e.id")->toArray();

                $mergeAllcation = array_unique(array_filter($entityAllocation + $entityAllocationOther));
                $entityList = array();
                foreach ($mergeAllcation as $key => $value) {
                    $entityList[] = array('id' => $key, 'trading_name' => $value);
                }
            } else {
                $entityList = \App\Models\Backend\Entity::select('id', 'trading_name')->whereIn('code', $entityCode)->get()->toArray();
            }
            $GLOBALS['entityId'] = $entityList;
        });

        return createResponse(config('httpResponse.SUCCESS'), "Entity list.", ['data' => $GLOBALS['entityId']]);
//        } catch (\Exception $e) {
//            app('log')->error("Bulk allocation operation performed failed" . $e->getMessage());
//            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not fetch client detail', ['error' => 'Could not fetch client detail']);
//        }
    }

}

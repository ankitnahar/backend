<?php

namespace App\Http\Controllers\Backend\Contact;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\Address;
use DB;

class AddressController extends Controller {
    /* Created by: Jayesh Shingrakhiya
     * Created on: July 05, 2018
     * Purpose   : Fetch entity address data
     */

    public function index(Request $request) {
       // try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'sortOrder' => 'in:asc,desc',
                'pageNumber' => 'numeric|min:1',
                'recordsPerPage' => 'numeric|min:0',
                'search' => 'json'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            // define soring parameters
            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'entity_address.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $entityAddress = Address::with('createdBy:id,userfullname,email')->with('modifiedBy:id,userfullname,email')
                    ->with('entityId:id,name,billing_name,trading_name');
            $entityAddress = $entityAddress->select('entity_address.*', 'u.userfullname', 'u.user_image','e.parent_id','ep.trading_name as parent_name', 'e.discontinue_stage', app('db')->raw('GROUP_CONCAT(`u`.`userfullname`) AS userfullname'), app('db')->raw('GROUP_CONCAT(`u`.`user_image`) AS user_image'));

            $right = checkButtonRights(24, 'all_entity');
            if ($right == false) {
                $entity_ids = checkUserClientAllocation(app('auth')->guard()->id());
                if (is_array($entity_ids))
                    $entityAddress = $entityAddress->whereRaw("entity_address.entity_id IN (". implode(",",$entity_ids).")");
            }

            if ($request->has('search')) {
                $search = $request->get('search');
                $aliase = array('entity_id' => 'entity_address',"parent_id" => "e");
                $entityAddress = search($entityAddress, $search, $aliase);
                $searchDecode = \GuzzleHttp\json_decode($search);

                if (isset($searchDecode->compare->equal->is_active) && $searchDecode->compare->equal->is_active == 0) {
                    $entityAddress = $entityAddress->with('is_active');
                }
            }

            $entityAddress->leftJoin('entity_allocation as ea', function($query) {
                        $query->on('ea.entity_id', '=', 'entity_address.entity_id');
                    })
                    ->leftJoin('user as u', function($query) {
                        $query->where('u.id', DB::raw('JSON_VALUE(ea.allocation_json,"$.9")'));
                    })
                    ->leftjoin("entity as e", "e.id", "entity_address.entity_id")
                    ->leftjoin("entity as ep", "ep.id", "e.parent_id");

            $entityAddress = $entityAddress->where("e.discontinue_stage","!=","2");
            $entityAddress = $entityAddress->groupBy('entity_address.entity_id');

            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $entityAddress = $entityAddress->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = count($entityAddress->get());

                $entityAddress = $entityAddress->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $entityAddress = $entityAddress->get();

                $filteredRecords = count($entityAddress);

                $pager = ['sortBy' => $sortBy,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            if ($request->has('excel') && $request->get('excel') == 1) {
                //format data in array 
                $state = config('constant.state');
                $addresstype = config('constant.addresstype');
                $entitydiscontinuestage = config('constant.entitydiscontinuestage');

                $data = $entityAddress->toArray();
                $column = array();
                $column[] = ['Sr.No','Parent Trading Name', 'Trading name', 'Technical account manager', 'Type', 'Street address', 'Suburb', 'State', 'Postcode', 'Client Status'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['parent_name'];
                        $columnData[] = $data['entity_id']['trading_name'];
                        $columnData[] = $data['userfullname'];
                        $columnData[] = $addresstype[$data['type']];
                        $columnData[] = $data['street_address'];
                        $columnData[] = $data['suburb'];
                        $columnData[] = isset($state[$data['state_id']]) ? $state[$data['state_id']]:'';
                        $columnData[] = $data['postcode'];
                        $columnData[] = $entitydiscontinuestage[$data['discontinue_stage']];
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'entity address', 'xlsx', 'A1:J1');
            }

            return createResponse(config('httpResponse.SUCCESS'), "Entity address  list.", ['data' => $entityAddress], $pager);
        /*} catch (\Exception $e) {
            app('log')->error("Client listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing entity address", ['error' => 'Server error.']);
        }*/
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July 05, 2018
     * Purpose   : Store entity entity address detail
     */

    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'required|numeric',
                'type' => 'required|in:1,2',
                'street_address' => 'required',
                'state_id' => 'required|numeric',
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            // store entity address details
            $entityAddress = Address::create([
                        'entity_id' => $request->get('entity_id'),
                        'type' => $request->get('type'),
                        'street_address' => $request->get('street_address'),
                        'suburb' => $request->get('suburb'),
                        'state_id' => $request->get('state_id'),
                        'postcode' => $request->get('postcode'),
                        'created_by' => app('auth')->guard()->id(),
                        'created_on' => date('Y-m-d H:i:s'),
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Entity address has been added successfully', ['data' => $entityAddress]);
        } catch (\Exception $e) {
            app('log')->error("Entity address creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add entity address', ['error' => 'Could not add entity address']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July 05, 2018
     * Purpose   : Show entity address
     */

    public function show(Request $request, $id) {
        try {
            $entityAddress = Address::with('createdBy:id,userfullname,email')->with('modifiedBy:id,userfullname,email')->with('entityId:id,name')->find($id);

            if (!isset($entityAddress))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The address  does not exist', ['error' => 'The address  does not exist']);

            //send address  information
            return createResponse(config('httpResponse.SUCCESS'), 'Entity address  data', ['data' => $entityAddress]);
        } catch (\Exception $e) {
            app('log')->error("Entity address  details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get address .', ['error' => 'Could not get address .']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July 05, 2018
     * Purpose   : Update entity address
     */

    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'numeric',
                'type' => 'numeric|in:1,2'
                    ], []);
            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $entityAddress = Address::find($id);

            if (!$entityAddress)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The entity address does not exist', ['error' => 'The entity address does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['entity_id', 'type', 'street_address', 'suburb', 'state_id', 'postcode'], $request);
            $entityAddress->modified_by = app('auth')->guard()->id();
            $entityAddress->modified_on = date('Y-m-d H:i:s');
            //update the details
            $entityAddress->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Entity address has been updated successfully', ['message' => 'Entity address has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Entity address updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update address details.', ['error' => 'Could not update address details.']);
        }
    }

}

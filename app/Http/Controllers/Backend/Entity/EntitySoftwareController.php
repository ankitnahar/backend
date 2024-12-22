<?php

namespace App\Http\Controllers\Backend\Entity;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\EntitySoftware;
use Illuminate\Support\Facades\Crypt;

class EntitySoftwareController extends Controller {
    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Fetch entity software data
     */

    public function index(Request $request) {
        try {
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
            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $entitySoftware = EntitySoftware::with('created_by:id,userfullname')->with('modified_by:id,userfullname')->with('softwareId:id,name')->with('entityId:id,name,billing_name,trading_name');
            if ($request->has('search')) {
                $search = $request->get('search');
                $entitySoftware = search($entitySoftware, $search);
                $searchDecode = \GuzzleHttp\json_decode($search);

                if (isset($searchDecode->compare->equal->is_active) && $searchDecode->compare->equal->is_active == 0) {
                    $entitySoftware = $entitySoftware->with('is_active');
                }
            }
            
            if($sortBy =='trading_name'){                
                $entitySoftware = $entitySoftware->leftjoin("entity as e","e.id","entity_software.entity_id");
                $sortBy = 'trading_name';
            }
            
            if($sortBy =='name'){                
                $entitySoftware = $entitySoftware->leftjoin("software as s","s.id","entity_software.software_id");
                $sortBy = 'name';
            }
            
            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $entitySoftware = $entitySoftware->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $entitySoftware->count();

                $entitySoftware = $entitySoftware->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $entitySoftware = $entitySoftware->get();

                $filteredRecords = count($entitySoftware);

                $pager = ['sortBy' => $sortBy,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
            $entitySoftware = EntitySoftware::decryptPassword($entitySoftware);
            return createResponse(config('httpResponse.SUCCESS'), "Entity software  list.", ['data' => $entitySoftware], $pager);
        } catch (\Exception $e) {
            app('log')->error("Client listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing entity software", ['error' => 'Server error.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July 05, 2018
     * Purpose   : Store entity entity software detail
     */

    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'required|numeric',
                'software_id' => 'required|numeric|unique:entity_software,software_id,entity_id' . $request->get('entity_id'),
                'link' => 'url'
                    ], ['software_id.unique' => 'Software already added']);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $password = Crypt::encrypt($request->get('password'));
            // store entity software details
            $entitySoftware = EntitySoftware::create([
                        'entity_id' => $request->get('entity_id'),
                        'software_id' => $request->get('software_id'),
                        'username' => $request->get('username'),
                        'password' => $password,
                        'link' => $request->get('link'),
                        'notes' => $request->get('notes'),
                        'created_by' => app('auth')->guard()->id(),
                        'created_on' => date('Y-m-d H:i:s'),
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Entity software has been added successfully', ['data' => $entitySoftware]);
        } catch (\Exception $e) {
            app('log')->error("Entity software creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add entity software', ['error' => 'Could not add entity software']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Show entity software
     */

    public function show(Request $request, $id) {
        try {
            $entitySoftware = EntitySoftware::with('created_by:id,userfullname,email')->with('modified_by:id,userfullname,email')->with('softwareId:id,name')->with('entityId:id,name')->where('id', $id)->get();

            if (!isset($entitySoftware))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The software  does not exist', ['error' => 'The software  does not exist']);

            $entitySoftware = EntitySoftware::decryptPassword($entitySoftware);
            //send software  information
            return createResponse(config('httpResponse.SUCCESS'), 'Entity software  data', ['data' => $entitySoftware]);
        } catch (\Exception $e) {
            app('log')->error("Entity software  details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get software .', ['error' => 'Could not get software .']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Update entity software
     */

    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'numeric',
                'software_id' => 'numeric',
                // 'software_id' => 'numeric|unique:entity_software,software_id,' . $id . ' ,entity_id,'.$request->get('entity_id'),
                'link' => 'url'
                    ], ['software_id.unique' => 'Software already added']);
            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $entitySoftware = EntitySoftware::find($id);

            if (!$entitySoftware)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The entity software does not exist', ['error' => 'The entity software does not exist']);
            
            $entitySoftwareDuplication = EntitySoftware::where('id', '!=', $id)->where('entity_id', $request->get('entity_id'))->where('software_id', $request->get('software_id'))->count();
            if($entitySoftwareDuplication > 0)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Duplication entity software', ['error' => 'Duplication entity software']);

            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['entity_id', 'software_id', 'username', 'password', 'link', 'notes', 'is_active'], $request);
            $entitySoftware->modified_by = app('auth')->guard()->id();
            $entitySoftware->modified_on = date('Y-m-d H:i:s');
            //update the details
            $entitySoftware->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Entity software has been updated successfully', ['message' => 'Entity software has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Entity software updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update software details.', ['error' => 'Could not update software details.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Update entity software
     */

    public function destroy(Request $request, $id) {
        try {
            $entitySoftware = EntitySoftware::find($id);
            // Check weather entity exists or not
            if (!isset($entitySoftware))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Entity software does not exist', ['error' => 'Entity software does not exist']);

            $entitySoftware->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Entity software has been deleted successfully', ['message' => 'Entity software has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Entity software deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete entity software.', ['error' => 'Could not delete entity software.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Update entity software
     */

    public function software(Request $request) {
        try {
            $software = new \App\Models\Backend\Software;

            if ($request->has('search')) {
                $search = $request->get('search');
                $software = search($software, $search);
            }

            $software = $software->get();
            if (!$software)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The software does not exist', ['error' => 'The software does not exist']);

            return createResponse(config('httpResponse.SUCCESS'), 'Software detail', ['data' => $software]);
        } catch (\Exception $e) {
            app('log')->error("Software details failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get software details.', ['error' => 'Could not get software details.']);
        }
    }

}

<?php

namespace App\Http\Controllers\Backend\Administrator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class IpAddressController extends Controller {
    /* Created by: Jayesh Shingrakhiya
     * Created on: Dec 22, 2018
     * Purpose   : Fetch ip address details
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

            $ipAddress = \App\Models\Backend\IpAddress::select('id', app('db')->raw('INET_NTOA(`from_ip`) as from_ip'), app('db')->raw('IF(`to_ip` != "",INET_NTOA(`to_ip`),"") AS to_ip'), app('db')->raw('IF(access_by="O","Other",IF(access_by="A", "Live", "Local")) as access_by'), 'belongs_to', 'created_by', "created_on", 'modified_on', 'modified_by')->with("createdBy:id,userfullname as created_by", "modifiedBy:id,userfullname as modified_by");
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $ipAddress = $ipAddress->leftjoin("user as u", "u.id", "ip_address.$sortBy");
                $sortBy = 'userfullname';
            }

            if ($request->has('search')) {
                $search = $request->get('search');
                $ipAddress = search($ipAddress, $search);
            }

            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $ipAddress = $ipAddress->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $ipAddress->count();
                $ipAddress = $ipAddress->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);
                //echo $ipAddress->toSql(); die;
                $ipAddress = $ipAddress->get();
                $filteredRecords = count($ipAddress);

                $pager = ['sortBy' => $sortBy,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Ip address list.", ['data' => $ipAddress], $pager);
        } catch (\Exception $e) {
            app('log')->error("Ip address listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing ip address list", ['error' => 'Server error.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: Dec 22, 2018
     * Purpose   : Store ip address details
     */

    public function store(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'from_ip' => 'required|ip',
                'to_ip' => 'ip',
                'access_by' => 'required|in:O,L,A'], []);

            //validate request parameters
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            // store ip address
            $ipAddress = \App\Models\Backend\IpAddress::create([
                        'from_ip' => app('db')->raw("INET_ATON('" . $request->get('from_ip') . "')"),
                        'to_ip' => app('db')->raw("INET_ATON('" . $request->get('to_ip') . "')"),
                        'access_by' => $request->get('access_by'),
                        'belongs_to' => $request->get('belongs_to'),
                        'created_by' => app('auth')->guard()->id(),
                        'created_on' => date('Y-m-d H:i:s'),
                        'modified_by' => app('auth')->guard()->id(),
                        'modified_on' => date('Y-m-d H:i:s')]);

            $ipAddress = \App\Models\Backend\IpAddress::select('id', app('db')->raw('INET_NTOA(`from_ip`) as from_ip'), app('db')->raw('IF(`to_ip` != "",INET_NTOA(`to_ip`),"") AS to_ip'), app('db')->raw('IF(access_by="O","Other",IF(access_by="A", "Live", "Local")) as access_by'), 'belongs_to', 'created_by', 'modified_by')->with("createdBy:id,userfullname as created_by", "modifiedBy:id,userfullname as modified_by")->find($ipAddress->id);
            return createResponse(config('httpResponse.SUCCESS'), 'Ip address  has been added successfully', ['data' => $ipAddress]);
        } catch (\Exception $e) {
            app('log')->error("Ip address creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add ip address', ['error' => 'Could not ip address']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: Dec 22, 2018
     * Purpose   : Show ip address details
     */

    public function show($id) {
        try {
            $ipAddress = \App\Models\Backend\IpAddress::select('id', 'from_ip', 'to_ip', app('db')->raw('IF(access_by="O","Other",IF(access_by="A", "Live", "Local")) as access_by'), 'belongs_to', 'created_by', 'modified_by')->with("createdBy:id,userfullname as created_by", "modifiedBy:id,userfullname as modified_by")->find($id);

            if (!isset($ipAddress))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The ip address does not exist', ['error' => 'The ip address does not exist']);

            //send ip address information
            return createResponse(config('httpResponse.SUCCESS'), 'Ip address  detail', ['data' => $ipAddress]);
        } catch (\Exception $e) {
            app('log')->error("Ip address details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get ip address detail.', ['error' => 'Could not get ip address detail.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: Dec 22, 2018
     * Purpose   : Update ip address details
     */

    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'from_ip' => 'required|ip',
                'to_ip' => 'ip',
                'access_by' => 'required|in:O,L,A'], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $ipAddress = \App\Models\Backend\IpAddress::find($id);
            if (!$ipAddress)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Ip address does not exist', ['error' => 'The Ip address does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $ipAddress->from_ip = app('db')->raw("INET_ATON('" . $request->get('from_ip') . "')");
            $ipAddress->to_ip = app('db')->raw("INET_ATON('" . $request->get('to_ip') . "')");
            $ipAddress->modified_on = date('Y-m-d H:i:s');
            $ipAddress->modified_by = loginUser();
            $updateData = filterFields(['access_by', 'belongs_to'], $request);
            $ipAddress->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Ip address has been updated successfully', ['message' => 'Ip address has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Ip address updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update ip address details.', ['error' => 'Could not update ip address details.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: Dec 22, 2018
     * Purpose   : Ip address details permanently removed.
     */

    public function destroy(Request $request, $id) {
        try {
            // If validation fails then return error response
            if (!is_numeric($id))
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => 'Please provice numeric id']);

            $ipAddress = \App\Models\Backend\IpAddress::find($id);
            if (!$ipAddress)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Ip address does not exist', ['error' => 'The Ip address does not exist']);

            $ipAddress->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Ip address has been deleted successfully', ['message' => 'Ip address has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Client deltion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not deleted ip address details.', ['error' => 'Could not deleted ip address details.']);
        }
    }

}

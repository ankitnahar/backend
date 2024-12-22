<?php

namespace App\Http\Controllers\Backend\Ticket;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TicketTypeController extends Controller {

    /**
     * Get Bank detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'ticket_type.name';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $ticketType = \App\Models\Backend\TicketType::where("is_active", "1");
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $ticketType = search($ticketType, $search);
            }

            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $ticketType = $ticketType->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $ticketType->count();

                $ticketType = $ticketType->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $ticketType = $ticketType->get();

                $filteredRecords = count($ticketType);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Ticket Type", ['data' => $ticketType], $pager);
        } catch (\Exception $e) {
            app('log')->error("Ticket Type listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Ticket Type", ['error' => 'Server error.']);
        }
    }

    /**
     * update Bank details     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // bank id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'add_email' => 'email_array',
                'update_email' => 'email_array',
                'close_email' => 'email_array',
                'is_active' => 'in:0,1',
                    ], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $ticketType = \App\Models\Backend\TicketType::find($id);

            if (!$ticketType)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Ticket Type does not exist', ['error' => 'The Ticket Type does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['add_email', 'update_email', 'close_email', 'is_active'], $request);
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;
            //update the details
            $ticketType->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Ticket Type has been updated successfully', ['message' => 'Ticket Type has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Ticket Type updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update ticket type details.', ['error' => 'Could not update ticket type details.']);
        }
    }

    /**
     * get particular bank details
     *
     * @param  int  $id   //bank id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $ticketType = \App\Models\Backend\TicketType::find($id);

            if (!isset($ticketType))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Ticket Type does not exist', ['error' => 'The Ticket Type does not exist']);

            //send bank information
            return createResponse(config('httpResponse.SUCCESS'), 'Ticket Type data', ['data' => $ticketType]);
        } catch (\Exception $e) {
            app('log')->error("Ticket Type details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Ticket Type.', ['error' => 'Could not get Ticket Type.']);
        }
    }
}

?>

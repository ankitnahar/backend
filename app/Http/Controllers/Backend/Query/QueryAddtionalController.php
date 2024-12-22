<?php

namespace App\Http\Controllers\Backend\Query;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

class QueryAddtionalController extends Controller {

    /**
     * Created by: Vivek Parmar
     * Created on: March 31,  2020
     * 
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $id) {
        //try {

         $addQueryDetail = \App\Models\Backend\QueryAdditionalInfo::with('createdBy:id,userfullname as created_by')
                ->select("query_additional_info.*")
                ->where('query_id', '=', $id)
                ->get();

        foreach ($addQueryDetail as $addQuery) {
            $addQuery['document'] = array();
            $addQueryDocument = \App\Models\Backend\QueryAdditionalDocument::with('createdBy:id,userfullname,email')
                    ->leftJoin('directory_entity_file as df', function($query) {
                        $query->on('df.file_id', '=', 'query_additional_document.document_name');
                        $query->on('df.move_to_trash', '=', DB::raw("0"));
                    })
                    ->select("query_additional_document.id","query_additional_document.document_path","query_additional_document.is_drive",
                                "query_additional_document.is_client",DB::raw("IF(query_additional_document.is_drive=0,query_additional_document.document_name,df.file_name) as document_name"), "df.file_id", "df.csv_excel_file_id", "df.mime_type", "df.size")
                    ->where('query_add_id', $addQuery->id);
            if ($addQueryDocument->count() > 0) {
                $addQueryDocument = $addQueryDocument->get();
                $addQuery['document'] = $addQueryDocument;
            }
            $queryAddInfo[] = $addQuery;
        }

        return createResponse(config('httpResponse.SUCCESS'), 'Additional Query Detail', ['data' => $addQueryDetail]);
        /* } catch (\Exception $e) {
          app('log')->error("additional query add fail : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get additional query.', ['error' => 'Could not get add additional query.']);
          } */
    }

    /**
     * Created by: Vivek Parmar
     * Created on: March 31,  2020
     * 
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $id) {
       // try {
            $validator = app('validator')->make($request->all(), [
                'comment' => 'required'
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $query = \App\Models\Backend\Query::find($id);
            $addQuery = \App\Models\Backend\QueryAdditionalInfo::create([
                        "query_id" => $id,
                        "comment" => !empty($request->get('comment')) ? $request->get('comment') : '',
                        "created_by" => loginUser(),
                        "created_on" => date('Y-m-d')
            ]);
           
            return createResponse(config('httpResponse.SUCCESS'), 'Additional Query has been added successfully', ['data' => $addQuery]);
       /* } catch (\Exception $e) {
            app('log')->error("additional query add fail : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add additional query.', ['error' => 'Could not add additional query.']);
        }*/
    }

    /* Created by: Vivek Parmar
     * Created on: July 31, 2020
     * Reason: Destory additional info data.
     */

    public function destroy(Request $request, $id) {
        try {
            $additioalInfo = \App\Models\Backend\QueryAdditionalInfo::find($id);
            // Check weather additional info exists or not
            if (!isset($additioalInfo))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Additional Info does not exist', ['error' => 'Additional Info does not exist']);
            
            //\App\Models\Backend\QueryAdditionalDocument::where("query_add_id",$id)->delete();
            //$additioalInfo->delete();
            $additioalInfo->update(['is_deleted' => 1,'deleted_on' => date('Y-m-d h:i:s')]);
            return createResponse(config('httpResponse.SUCCESS'), 'Additional Info has been deleted successfully', ['message' => 'Additional Info has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Additional Info deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete additional info.', ['error' => 'Could not delete additional info.']);
        }
    }    

}

?>
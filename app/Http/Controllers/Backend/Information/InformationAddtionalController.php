<?php

namespace App\Http\Controllers\Backend\Information;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

class InformationAddtionalController extends Controller {

    /**
     * Created by: Pankaj
     * Created on: March 31,  2020
     * 
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $id) {
        //try {
        $information = \App\Models\Backend\Information::where("id", $id)->first();
        $addInfoDetail = \App\Models\Backend\InformationAdditionalInfo::with('createdBy:id,userfullname as created_by')
                ->select("information_additional_info.*")
                ->where('information_id', '=', $id)
                ->get();

        foreach ($addInfoDetail as $addInfo) {
            $addInfo['document'] = array();
            $addInfoDocument = \App\Models\Backend\InformationAdditionalDocument::with('createdBy:id,userfullname,email')
                    ->leftJoin('directory_entity_file as df', function($query) {
                        $query->on('df.file_id', '=', 'information_additional_document.document_name');
                        $query->on('df.move_to_trash', '=', DB::raw("0"));
                    })
                    ->select("information_additional_document.id","information_additional_document.document_path","information_additional_document.is_drive",
                                "information_additional_document.is_client",DB::raw("IF(information_additional_document.is_drive=0,information_additional_document.document_name,df.file_name) as document_name"), "df.file_id", "df.csv_excel_file_id", "df.mime_type", "df.size")
                    ->where('information_add_id', $addInfo->id);
            if ($addInfoDocument->count() > 0) {
                $addInfoDocument = $addInfoDocument->get();
                $addInfo['document'] = $addInfoDocument;
            }
            $informationAddInfo[] = $addInfo;
        }

        return createResponse(config('httpResponse.SUCCESS'), 'Additional Information Detail', ['data' => $addInfoDetail]);
        /* } catch (\Exception $e) {
          app('log')->error("additional information add fail : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get additional information.', ['error' => 'Could not get add additional information.']);
          } */
    }

    /**
     * Created by: Pankaj
     * Created on: March 31,  2020
     * 
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $id) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'comment' => 'required'
                ], []);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        $information = \App\Models\Backend\Information::find($id);
        $addInfo = \App\Models\Backend\InformationAdditionalInfo::create([
                    "information_id" => $id,
                    "comment" => !empty($request->get('comment')) ? $request->get('comment') : '',
                    "created_by" => loginUser(),
                    "created_on" => date('Y-m-d')
        ]);
        return createResponse(config('httpResponse.SUCCESS'), 'Additional Information has been added successfully', ['data' => $addInfo]);
        /* } catch (\Exception $e) {
          app('log')->error("additional information add fail : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add additional information.', ['error' => 'Could not add additional information.']);
          } */
    }
    public function destroy(Request $request, $id) {
        //try {
            $infoDetail = \App\Models\Backend\InformationAdditionalInfo::find($id);
             
            // Check weather additional info exists or not
            if (!isset($infoDetail))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Info Additional Document does not exist', ['error' => 'Info Additional Document does not exist']);

            /*$infoDetailDocument = \App\Models\Backend\InformationAdditionalDocument::where("information_add_id",$id);
            if($infoDetailDocument->count() > 0){
                $infoDetailDocument->delete();
            }*/           
           $infoDetail->update(['is_deleted' => 1,'deleted_on' => date('Y-m-d h:i:s')]);

            return createResponse(config('httpResponse.SUCCESS'), 'Info Additional Document has been deleted successfully', ['message' => 'Info Additional Document has been deleted successfully']);
       /* } catch (\Exception $e) {
            app('log')->error("Info Document deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete Info Additional Document.', ['error' => 'Could not delete Info Additional Document.']);
        }*/
    }
}

?>
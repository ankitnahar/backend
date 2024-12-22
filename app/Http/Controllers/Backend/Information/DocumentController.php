<?php

namespace App\Http\Controllers\Backend\Information;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DocumentController extends Controller {

   public function index(Request $request, $id) {
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

            $informationDocument = \App\Models\Backend\InformationDetailDocument::with('created_by:id,userfullname,email')
                     ->leftJoin('directory_entity_file as df', function($query) {
                            $query->on('df.file_id', '=', 'information_detail_document.document_name');
                            $query->on('df.move_to_trash', '=', DB::raw("0"));
                        })
                    ->select("information_detail_document.*", "df.file_id", "df.csv_excel_file_id", "df.mime_type", "df.size")                    
                    ->where('information_id', $id);
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $informationDocument = $informationDocument->leftjoin("user as u", "u.id", "document.$sortBy");
                $sortBy = 'userfullname';
            }

            if ($request->has('search')) {
                $search = $request->get('search');
                $informationDocument = search($informationDocument, $search);
            }

            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $informationDocument = $informationDocument->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $informationDocument->count();

                $informationDocument = $informationDocument->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $informationDocument = $informationDocument->get();

                $filteredRecords = count($informationDocument);

                $pager = ['sortBy' => $request->get('sortBy'),
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
            return createResponse(config('httpResponse.SUCCESS'), "Document document list.", ['data' => $informationDocument], $pager);
        } catch (\Exception $e) {
            app('log')->error("Document document listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while Document document listing", ['error' => 'Server error.']);
        }
    }

     public function store(Request $request, $id) {
       // try {
            $validator = app('validator')->make($request->all(), [
                'document_file' => 'required'
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $informationDetail = \App\Models\Backend\InformationDetail::find($id);
                $fileName = self::uploadDocument($request, $informationDetail->id);
               
            
            return createResponse(config('httpResponse.SUCCESS'), 'Document has been added successfully', ['data' => $informationDetail]);
        /*} catch (\Exception $e) {
            app('log')->error("Document add fail : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add Document.', ['error' => 'Could not add Document.']);
        }*/
    }

    public function uploadDocument(Request $request, $id) {
        //try {
        $entityDetail = \App\Models\Backend\InformationDetail::leftjoin("information as i","i.id","information_detail.information_id")
                ->leftJoin('entity AS e', 'e.id', '=', 'i.entity_id')
                ->select('e.id', 'e.code', 'e.trading_name')
                ->where("information_detail.id",$id)->first();

        $folderName = 'InformationDocuments';
        $file = $request->file('document_file');

        //$fileName = rand(1, 2000000) . strtotime(date('Y-m-d H:i:s')) . '.' . $file->getClientOriginalExtension();
        $fileName = $file->getClientOriginalName();
        
        $entityCode = $entityDetail->code;
        
        $commanFolder = '/bdms/';
        $uploadPath = storageEfs() . $commanFolder .$entityCode;       

         if (date("m") >= 7) {
            $dir = date("Y") . "-" . date('Y', strtotime('+1 years'));
            if (!is_dir($uploadPath .'/'. $dir)) {
                mkdir($uploadPath .'/'. $dir, 0777, true);
            }
        } else if (date("m") <= 6) {
            $dir = date('Y', strtotime('-1 years')) . "-" . date("Y");
            if (!is_dir($uploadPath .'/'. $dir)) {
                mkdir($uploadPath .'/'. $dir, 0777, true);
            }
        }
        
        $document_path = $uploadPath . '/' . $dir . '/' . $folderName;

        if (!is_dir($document_path)) {
            mkdir($document_path, 0777, true);
        }
        $informationDocumentUploaded = 0;
        if ($file->move($document_path, $fileName)) {
            $informationDocumentUploaded = 1;
            $data['document_name'] = $fileName;
            $data['document_path'] = $commanFolder . $entityCode . '/' . $dir . '/' . $folderName;
            $data['created_by'] = app('auth')->guard()->id();
            $data['created_on'] = date('Y-m-d H:i:s');
            $data['information_detail_id'] = $id;
            $informationDocument = \App\Models\Backend\InformationDetailDocument::insert($data);
        }

        if ($informationDocumentUploaded == 1)
            return createResponse(config('httpResponse.SUCCESS'), 'Document has been uploaded successfully', ['data' => 'Document has been uploaded successfully']);

        return createResponse(config('httpResponse.SERVER_ERROR'), 'upload information document', ['error' => $informationDocumentUploaded]);

        /* } catch (\Exception $e) {
          app('log')->error("Document upload failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not upload information document', ['error' => 'Could not upload information document']);
          } */
    }
    
     
    /**
     * Created by: Pankaj
     * Created on: March 27, 2018
     * Download information documents detail
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function downloadDocument(Request $request, $id) {
        //try {
            $infoDocumentDetail = \App\Models\Backend\InformationDetailDocument::find($id);
            if (!$infoDocumentDetail)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The info document does not exist', ['error' => 'The info document does not exist']);
            if (file_exists(storageEfs($infoDocumentDetail->document_path .'/'. $infoDocumentDetail->document_name))) {
                $file = storageEfs() . $infoDocumentDetail->document_path .'/'. $infoDocumentDetail->document_name;
            } else {
                $file = storageEfs() . $infoDocumentDetail->document_path .'/'. $infoDocumentDetail->document_name;
            }

            return response()->download($file);
        /*} catch (\Exception $e) {
            app('log')->error("Docuement download failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not download info docuement.', ['error' => 'Could not download info document.']);
        }*/
    }

    /* Created by: Pankaj
     * Created on: July 31, 2020
     * Reason: Destory additional info data.
     */

    public function destroy(Request $request, $id) {
        try {
            $infoDetail = \App\Models\Backend\InformationDetailDocument::find($id);
            // Check weather additional info exists or not
            if (!isset($infoDetail))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Info Document does not exist', ['error' => 'Info Document does not exist']);

            $infoDetail->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Info Document has been deleted successfully', ['message' => 'Info Document has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Info Document deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete Info Document.', ['error' => 'Could not delete Info Document.']);
        }
    }

}

?>
<?php

namespace App\Http\Controllers\Backend\Query;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class QueryAdditionalDocumentController extends Controller {

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

            $QueryDocument = \App\Models\Backend\QueryAdditionalDocument::with('created_by:id,userfullname,email')
                    ->leftJoin('directory_entity_file as df', function($query) {
                        $query->on('df.file_id', '=', 'query_additional_document.document_name');
                        $query->on('df.move_to_trash', '=', DB::raw("0"));
                    })
                    ->select("query_additional_document.id", "query_additional_document.document_path", "query_additional_document.is_drive", "query_additional_document.is_client", DB::raw("IF(query_additional_document.is_drive=0,query_additional_document.document_name,df.file_name) as document_name"), "df.file_id", "df.csv_excel_file_id", "df.mime_type", "df.size")
                    ->where('query_add_id', $id);
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $QueryDocument = $QueryDocument->leftjoin("user as u", "u.id", "document.$sortBy");
                $sortBy = 'userfullname';
            }

            if ($request->has('search')) {
                $search = $request->get('search');
                $QueryDocument = search($QueryDocument, $search);
            }

            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $QueryDocument = $QueryDocument->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $QueryDocument->count();

                $QueryDocument = $QueryDocument->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $QueryDocument = $QueryDocument->get();

                $filteredRecords = count($QueryDocument);

                $pager = ['sortBy' => $request->get('sortBy'),
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
            return createResponse(config('httpResponse.SUCCESS'), "Document document list.", ['data' => $QueryDocument], $pager);
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

        $queryDetail = \App\Models\Backend\QueryAdditionalInfo::find($id);
        $fileName = self::uploadDocument($request, $queryDetail->id);

        return createResponse(config('httpResponse.SUCCESS'), 'Document has been added successfully', ['data' => $queryDetail]);
        /* } catch (\Exception $e) {
          app('log')->error("Document add fail : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add Document.', ['error' => 'Could not add Document.']);
          } */
    }

    public function uploadDocument(Request $request, $id) {
        //try {
        $entityDetail = \App\Models\Backend\QueryAdditionalInfo::leftjoin("query as i", "i.id", "query_additional_info.query_id")
                        ->leftJoin('entity AS e', 'e.id', '=', 'i.entity_id')
                        ->select('e.id', 'e.code', 'e.trading_name')
                        ->where("query_additional_info.id", $id)->first();

        $folderName = 'QueryDocuments';
        $file = $request->file('document_file');

        //$fileName = rand(1, 2000000) . strtotime(date('Y-m-d H:i:s')) . '.' . $file->getClientOriginalExtension();
        $fileName = $file->getClientOriginalName();
        $commanFolder = '/bdms/';
        $entityCode = $entityDetail->code;
        $uploadPath = storageEfs() . $commanFolder . $entityCode;      

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
        $QueryDocumentUploaded = 0;
        if ($file->move($document_path, $fileName)) {
            $QueryDocumentUploaded = 1;
            $data['document_name'] = $fileName;
            $data['document_path'] = $commanFolder .$entityCode. '/' . $dir . '/' . $folderName;
            $data['created_by'] = app('auth')->guard()->id();
            $data['created_on'] = date('Y-m-d H:i:s');
            $data['query_add_id'] = $id;
            $QueryDocument = \App\Models\Backend\QueryAdditionalDocument::insert($data);
        }

        if ($QueryDocumentUploaded == 1)
            return createResponse(config('httpResponse.SUCCESS'), 'Document has been uploaded successfully', ['data' => 'Document has been uploaded successfully']);

        return createResponse(config('httpResponse.SERVER_ERROR'), 'upload query document', ['error' => $QueryDocumentUploaded]);

        /* } catch (\Exception $e) {
          app('log')->error("Document upload failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not upload query document', ['error' => 'Could not upload query document']);
          } */
    }

    /**
     * Created by: Pankaj
     * Created on: March 27, 2018
     * Download query documents detail
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function downloadDocument(Request $request, $id) {
        // try {
        $queryDocumentDetail = \App\Models\Backend\QueryAdditionalDocument::find($id);
        if (!isset($queryDocumentDetail))
            return createResponse(config('httpResponse.NOT_FOUND'), 'The Query document does not exist', ['error' => 'The Query document does not exist']);
        if (file_exists(storageEfs($queryDocumentDetail->document_path . '/' . $queryDocumentDetail->document_name))) {
            $file = storageEfs() . $queryDocumentDetail->document_path . '/' . $queryDocumentDetail->document_name;
        } else {
            $file = storageEfs() . $queryDocumentDetail->document_path . '/' . $queryDocumentDetail->document_name;
        }

        return response()->download($file);
        /* } catch (\Exception $e) {
          app('log')->error("Docuement download failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not download Query docuement.', ['error' => 'Could not download Query document.']);
          } */
    }

    /* Created by: Pankaj
     * Created on: July 31, 2020
     * Reason: Destory additional Query data.
     */

    public function destroy(Request $request, $id) {
        try {
            $QueryAdditionalInfo = \App\Models\Backend\QueryAdditionalDocument::find($id);
            // Check weather additional Query exists or not
            if (!isset($QueryAdditionalInfo))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Query Document does not exist', ['error' => 'Query Document does not exist']);

            $QueryAdditionalInfo->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Query Document has been deleted successfully', ['message' => 'Query Document has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Query Document deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete Query Document.', ['error' => 'Could not delete Query Document.']);
        }
    }

}

?>
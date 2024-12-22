<?php

namespace App\Http\Controllers\Backend\Worksheet;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\WorksheetDocument;
use DB;

class CommanController extends Controller {

    /**
     * Created by: Jayesh Shigrakhiya
     * Created on: Aug 24, 2018
     * Get worksheet documents detail
     * @param  Illuminate\Http\Request  $request
     * * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
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

            $worksheetDocument = WorksheetDocument::with('created_by:id,userfullname,email')
                    ->leftJoin('directory_entity_file as df', function($query) {
                        $query->on('df.file_id', '=', 'worksheet_document.document_name');
                        $query->on('df.move_to_trash', '=', DB::raw("0"));
                    })
                    ->select("worksheet_document.*", "df.file_id", "df.csv_excel_file_id", "df.mime_type", "df.size")
                    ->where('worksheet_id', $id);
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $worksheetDocument = $worksheetDocument->leftjoin("user as u", "u.id", "document.$sortBy");
                $sortBy = 'userfullname';
            }

            if ($request->has('search')) {
                $search = $request->get('search');
                $worksheetDocument = search($worksheetDocument, $search);
            }

            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $worksheetDocument = $worksheetDocument->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $worksheetDocument->count();

                $worksheetDocument = $worksheetDocument->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $worksheetDocument = $worksheetDocument->get(['worksheet_document.*']);

                $filteredRecords = count($worksheetDocument);

                $pager = ['sortBy' => $request->get('sortBy'),
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
            $worksheetDocument = WorksheetDocument::arrangeData($worksheetDocument);
            return createResponse(config('httpResponse.SUCCESS'), "Worksheet document list.", ['data' => $worksheetDocument], $pager);
        } catch (\Exception $e) {
            app('log')->error("Worksheet document listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while worksheet document listing", ['error' => 'Server error.']);
        }
    }

    /**
     * Created by: Jayesh Shigrakhiya
     * Created on: Aug 24, 2018
     * Store worksheet documents detail
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function uploadDocument(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'worksheet_id' => 'required|numeric',
                'document_type' => 'required|in:1,2',
                'is_sent' => 'required|in:1,0'], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $id = $request->get('worksheet_id');
            $entityDetail = \App\Models\Backend\Worksheet::select('e.code')->leftJoin('entity AS e', 'e.id', '=', 'worksheet.entity_id')->find($id);

            $file = $request->file('document_file');
            //$fileName = rand(1, 2000000) . strtotime(date('Y-m-d H:i:s')) . '.' . $file->getClientOriginalExtension();
            $fileName = $file->getClientOriginalName();
            $commanFolder = '/uploads/documents/';
            $uploadPath = storageEfs() . $commanFolder;
            if (date("m") >= 7) {
                $dir = date("Y") . "-" . date('Y', strtotime('+1 years'));
                if (!is_dir($uploadPath . $dir)) {
                    mkdir($uploadPath . $dir, 0777, true);
                }
            } else if (date("m") <= 6) {
                $dir = date('Y', strtotime('-1 years')) . "-" . date("Y");
                if (!is_dir($uploadPath . $dir)) {
                    mkdir($uploadPath . $dir, 0777, true);
                }
            }

            $mainFolder = $entityDetail->code;
            if (!is_dir($uploadPath . $dir . '/' . $mainFolder))
                mkdir($uploadPath . $dir . '/' . $mainFolder, 0777, true);

            $location = 'Worksheet';
            $document_path = $uploadPath . $dir . '/' . $mainFolder . '/' . $location;

            if (!is_dir($document_path))
                mkdir($document_path, 0777, true);
            @chmod($document_path, 0777);
            $worksheetDocumentUploaded = 0;
            if ($file->move($document_path, $fileName)) {
                $worksheetDocumentUploaded = 1;
                $data['worksheet_id'] = $id;
                $data['document_title'] = $file->getClientOriginalName();
                $data['document_name'] = $fileName;
                $data['document_path'] = $commanFolder . $dir . '/' . $mainFolder . '/' . $location . '/';
                $data['document_type'] = $request->get('document_type');
                $data['is_sent'] = $request->get('is_sent');
                $data['created_by'] = app('auth')->guard()->id();
                $data['created_on'] = date('Y-m-d H:i:s');
                WorksheetDocument::insert($data);
            }

            if ($worksheetDocumentUploaded == 1)
                return createResponse(config('httpResponse.SUCCESS'), 'Document has been uploaded successfully', ['data' => 'Document has been uploaded successfully']);

            return createResponse(config('httpResponse.SERVER_ERROR'), 'upload worksheet document', ['error' => $worksheetDocumentUploaded]);
        } catch (\Exception $e) {
            app('log')->error("Document upload failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not upload worksheet document', ['error' => 'Could not upload worksheet document']);
        }
    }

    public function uploadDocumentOnDrive(Request $request) {
        //try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'entity_id' => 'required',
            'document_insert_type' => 'required|in:1,2,3,4,5',
            'document_type' => 'required|in:0,1,2',
            'document_file' => 'required'], []);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

        $entityDetail = \App\Models\Backend\Entity::select('code')->find($request->input('entity_id'));
        $fileId = $request->get('document_file');
        $fileArray = explode(",", $fileId);

        // Delete File
        for ($i = 0; $i < count($fileArray); $i++) {
            $fileDetail = \App\Models\Backend\DirectoryEntityFile::where("file_id", $fileArray[$i])->first();

            if ($fileDetail->size <= 25000000) {
                $data['document_name'] = $fileDetail->file_id;
                $data['is_drive'] = 1;
                $data['document_path'] = $fileDetail->path;
                $data['created_by'] = app('auth')->guard()->id();
                $data['created_on'] = date('Y-m-d H:i:s');
                if ($request->input('document_insert_type') == 1) {
                    $id = $request->get('worksheet_id');
                    $data['worksheet_id'] = $id;
                    $data['is_sent'] = $request->get('is_sent');
                    $data['document_title'] = $fileDetail->file_name;
                    $data['document_type'] = $request->get('document_type');
                    WorksheetDocument::insert($data);
                } else if ($request->input('document_insert_type') == 2) {
                    $id = $request->get('information_detail_id');
                    $data['information_detail_id'] = $id;
                    \App\Models\Backend\InformationDetailDocument::insert($data);
                } else if ($request->input('document_insert_type') == 3) {
                    $id = $request->get('query_detail_id');
                    $data['query_detail_id'] = $id;
                    \App\Models\Backend\QueryDetailDocument::insert($data);
                } else if ($request->input('document_insert_type') == 4) {
                    $id = $request->get('information_add_id');
                    $data['information_add_id'] = $id;
                    \App\Models\Backend\InformationAdditionalDocument::insert($data);
                } else if ($request->input('document_insert_type') == 5) {
                    $id = $request->get('query_add_id');
                    $data['query_add_id'] = $id;
                    \App\Models\Backend\QueryAdditionalDocument::insert($data);
                }
            }
        }

        return createResponse(config('httpResponse.SUCCESS'), 'Document has been uploaded successfully', ['data' => 'Document has been uploaded successfully']);


        /* } catch (\Exception $e) {
          app('log')->error("Document upload failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not upload worksheet document', ['error' => 'Could not upload worksheet document']);
          } */
    }

    /**
     * Created by: Jayesh Shigrakhiya
     * Created on: Aug 24, 2018
     * Download worksheet documents detail
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function downloadDocument($id) {
        try {
            $worksheetDocumentDetail = WorksheetDocument::find($id);
            if (!$worksheetDocumentDetail)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The worksheet document does not exist', ['error' => 'The worksheet document does not exist']);
            if (file_exists(storageEfs($worksheetDocumentDetail->document_path . $worksheetDocumentDetail->document_title))) {
                $file = storageEfs() . $worksheetDocumentDetail->document_path . $worksheetDocumentDetail->document_title;
            } else {
                $file = storageEfs() . $worksheetDocumentDetail->document_path . $worksheetDocumentDetail->document_name;
            }

            return response()->download($file);
        } catch (\Exception $e) {
            app('log')->error("Docuement download failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not download worksheet docuement.', ['error' => 'Could not download worksheet document.']);
        }
    }

    /**
     * Created by: Jayesh Shigrakhiya
     * Created on: Aug 24, 2018
     * Remove worksheet documents detail
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function removeDocument($id) {
        try {
            $worksheetDocumentDetail = WorksheetDocument::find($id);
            if (!$worksheetDocumentDetail)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The worksheet document does not exist', ['error' => 'The worksheet document does not exist']);

            $worksheetDocumentDetail->is_deleted = 1;
            $worksheetDocumentDetail->deleted_by = app('auth')->guard()->id();
            $worksheetDocumentDetail->deleted_on = date('Y-m-d H:i:s');
            $worksheetDocumentDetail->save();

            return createResponse(config('httpResponse.SUCCESS'), 'Document has been deleted successfully', ['message' => 'Document has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Docuement download failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete worksheet docuement.', ['error' => 'Could not delete worksheet document.']);
        }
    }

    /**
     * Created by: Jayesh Shigrakhiya
     * Created on: Feb 08, 2018
     * Update worksheet document status for client
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function updateDocument(Request $request, $id) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'is_sent' => 'required|in:0,1'], ['is_sent.required' => 'Document update field is required']);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $worksheetDocumentDetail = WorksheetDocument::find($id);
            if (empty($worksheetDocumentDetail))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The worksheet document does not exist', ['error' => 'The worksheet document does not exist']);

            $worksheetDocumentDetail->is_sent = $request->get('is_sent');
            $worksheetDocumentDetail->save();

            return createResponse(config('httpResponse.SUCCESS'), 'Document has been updated successfully', ['message' => 'Document has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Docuement download failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update worksheet docuement.', ['error' => 'Could not update worksheet document.']);
        }
    }

    /**
     * Created by: Jayesh Shigrakhiya
     * Created on: Feb 22, 2019
     * Worksheet list
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function worksheetGet(Request $request, $id) {
        try {

            $worksheet = \App\Models\Backend\Worksheet::select(app('db')->raw('CONCAT(task_id, "::", worksheet.id, "::", worksheet.worksheet_master_id) as task_id'), app('db')->raw('CONCAT(ma.name, "::", ta.name, "::", start_date, "-", end_date) as name'))->leftJoin('master_activity as ma', 'ma.id', '=', 'worksheet.master_activity_id')->leftJoin('task as ta', 'ta.id', '=', 'worksheet.task_id')->where('worksheet.service_id', $request->get('service_id'))->where('entity_id', $id)->where('status_id', '!=', 4)->get();

            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet fetch succesfull', ['data' => $worksheet]);
        } catch (\Exception $e) {
            app('log')->error("Worksheet list : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not list worksheet.', ['error' => 'Could not list worksheet.']);
        }
    }

}

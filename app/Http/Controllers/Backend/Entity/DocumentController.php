<?php

namespace App\Http\Controllers\Backend\Entity;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\Document;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * This is a client class controller.
 * 
 */
class DocumentController extends Controller {

    /**
     * Get clients detail
     *
     * @param  Illuminate\Http\Request  $request
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

            $document = Document::with('created_by:id,userfullname,email')->where('entity_id', $id);
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $document = $document->leftjoin("user as u", "u.id", "document.$sortBy");
                $sortBy = 'userfullname';
            }

            if ($request->has('search')) {
                $search = $request->get('search');
                $document = search($document, $search);
            }

            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $document = $document->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $document->count();

                $document = $document->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $document = $document->get(['document.*']);

                $filteredRecords = count($document);

                $pager = ['sortBy' => $request->get('sortBy'),
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Document list.", ['data' => $document], $pager);
        } catch (\Exception $e) {
            app('log')->error("document listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing document", ['error' => 'Server error.']);
        }
    }

    /**
     * Store document details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $id) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'document' => 'required|mimes:jpg,jpeg,png,pdf,csv,xlsx,xls',
                'type' => 'required',
                'notes' => 'required_if:type,2',
                'module_id' => 'required|numeric',
                    ], ['notes.required_if' => 'The notes field is required when type is other']);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $module_id = $request->get('module_id');
            $entity = \App\Models\Backend\Entity::select('code')->where('id', $id)->first();
            $module = \App\Models\Backend\Module::select('name')->where('id', $module_id)->first();

            $data['entity_id'] = $id;
            $data['entity_code'] = $entity->code;
            $data['inputname'] = 'document';
            $data['location'] = $module->name;
            $data['module_id'] = $module_id;
            $documentUploaded = uploadDocument($request, $data);

            if ($documentUploaded == 1)
                return createResponse(config('httpResponse.SUCCESS'), 'Document has been uploaded successfully', ['data' => 'Document has been uploaded successfully']);

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not upload document', ['error' => $documentUploaded]);
        } catch (\Exception $e) {
            app('log')->error("Client creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not upload document', ['error' => 'Could not upload document']);
        }
    }

    /**
     * get particular document details
     *
     * @param  int  $id   //Client id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $documentDetail = Document::with('created_by:id,userfullname,email')->find($id);

            if (!isset($documentDetail))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The document does not exist', ['error' => 'The document does not exist']);

            //send client information
            return createResponse(config('httpResponse.SUCCESS'), 'Document data', ['data' => $documentDetail]);
        } catch (\Exception $e) {
            app('log')->error("Document details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get document detail.', ['error' => 'Could not get document detail.']);
        }
    }

    /**
     * update document details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // Client id
     * @return Illuminate\Http\JsonResponse
     */
    public function download($id) {
        try {
            $documentDetail = Document::find($id);

            if (!$documentDetail)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The document does not exist', ['error' => 'The document does not exist']);

            //$file = storage_path() . $documentDetail->documentpath . $documentDetail->filename;
            if(File::exists(storage_path($documentDetail->documentpath. $documentDetail->original_name))){
            $file = storage_path() . $documentDetail->documentpath . $documentDetail->original_name;
            }else{
               $file = storage_path() . $documentDetail->documentpath . $documentDetail->filename; 
            }
            return response()->download($file);
        } catch (\Exception $e) {
            app('log')->error("Docuement download failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not download docuement.', ['error' => 'Could not download document.']);
        }
    }

    /**
     * update document details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // Client id
     * @return Illuminate\Http\JsonResponse
     */
    public function downloadZip(REQUEST $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'module_id' => 'required'], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $module = Document::where('module_id', $request->get('module_id'))->where('entity_id', $id)->get()->toArray();
            $entityDetail = \App\Models\Backend\Entity::select('code', 'name')->find($id);

            $zip = new \ZipArchive();
            $storagePath = storage_path('templocation/' . $entityDetail->code);
            if (!is_dir($storagePath)) {
                mkdir($storagePath, 0777, true);
            }

            $zipfile = $storagePath . '/' . $entityDetail->name . '.zip';

            if ($zip->open($zipfile, \ZipArchive::CREATE) === TRUE) {
                // Add File in ZipArchive
                foreach ($module as $key => $value) {
                    $fileName = $value['original_name'];
                    $path = storage_path($value['documentpath'] . $value['filename']);
                    $zip->addFile($path, $fileName);
                }
                // Close ZipArchive
                $zip->close();
            }
            $headers = array('Content-Type' => 'application/octet-stream',
                'Content-disposition: attachment; filename = ' . $zipfile);

            //return response()->download($zipfile)->deleteFileAfterSend(true);
            $response = response()->download($zipfile);
            register_shutdown_function('removeDirWithFiles', $storagePath);
            return $response;
        } catch (\Exception $e) {
            app('log')->error("Docuement download failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not download docuement zip.', ['error' => 'Could not download document zip.']);
        }
    }

    /**
     * update document details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // Client id
     * @return Illuminate\Http\JsonResponse
     */
    public function destroy($id) {
        try {
            $documentDetail = Document::find($id);
            // Check weather client exists or not
            if (!isset($documentDetail))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Document does not exist', ['error' => 'Document does not exist']);

            $file = storage_path() . $documentDetail->documentpath . $documentDetail->filename;
            if (File::exists($file) && File::delete($file)) {
                $documentDetail->delete();
            } else {
                return createResponse(config('httpResponse.NOT_FOUND'), 'Document does not remove from directory', ['error' => 'Document does not remove from directory']);
            }

            return createResponse(config('httpResponse.SUCCESS'), 'Document has been deleted successfully', ['message' => 'Document has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Client deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete document.', ['error' => 'Could not delete document.']);
        }
    }

    public function getmodule() {
        try {
            $moduleDetail = \App\Models\Backend\Module::select('id', 'name')->get()->pluck('name', 'id')->toArray();
            if (!$moduleDetail)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The document does not exist', ['error' => 'The document does not exist']);

            return createResponse(config('httpResponse.SUCCESS'), "Module list.", ['data' => $moduleDetail]);
        } catch (\Exception $e) {
            app('log')->error("Docuement download failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not download docuement.', ['error' => 'Could not download document.']);
        }
    }

}

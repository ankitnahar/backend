<?php

namespace App\Http\Controllers\Backend\GoogleDrive;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use DB;

class GoogleDriveFolderController extends Controller {
    /* List of Directory and directory File */

    public function listDirectory(Request $request) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'entity_id' => 'required',
            'parent_id' => 'required',
            'trash' => 'in:1,0'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        $userHierarchy = getLoginUserHierarchy();
        if ($userHierarchy->designation_id != 7) {
            $serviceRight = ['8', '9', '10', '11'];
            $teamRight = $userHierarchy->team_id != '' ? $userHierarchy->team_id : '0';
            if($userHierarchy->other_right!= ''){
                $teamRight = $teamRight.",".$userHierarchy->other_right;
                }
            $searchForValue = ',';
            if (strpos($teamRight, $searchForValue) !== false) {                
                $teamRight = explode(",", $teamRight);
                $serviceRight = array_merge($serviceRight, $teamRight);
            }else {
                array_push($serviceRight, $teamRight);
            }
        } else {
            $serviceRight = ['1', '2', '8', '9', '10', '11'];
        }
        // list move to Trash File
        if ($request->has('trash')) {
            $directory = array();
            $directoryFile = \App\Models\Backend\DirectoryEntityFile::where('entity_id', $request->input('entity_id'))
                            ->where('move_to_trash', $request->input('trash'))
                            ->whereIn('service_id', $serviceRight)
                            ->orderBy("file_name", "asc")->get()->toArray();
        } else {
            $subclienList = array();
            if ($request->input('parent_id') == 0 && $request->input('subclient_id') == 0) {
                $subClient = \App\Models\Backend\SubClient::where("entity_id", $request->input('entity_id'))->where("is_active", "1");
                $entityFolderId = \App\Models\Backend\Entity::where("id", $request->input('entity_id'))->select('folder_id')->first();
                if ($subClient->count() > 0 && $entityFolderId->folder_id != '') {
                    $subClient = $subClient->orderBy("subclient", "asc")->get();
                    $subclienList = self::createSubClientFolder($request->input('entity_id'), $subClient, $entityFolderId->folder_id);
                }
            }
            // get All folder
            $directory = \App\Models\Backend\DirectoryEntity::where("parent_id", $request->input('parent_id'));
            if ($request->input('subclient_id') > 0) {
                $directory = $directory->where("subclient_id", $request->input('subclient_id'));
            } else if ($request->input('parent_id') == 0) {
                $directory = $directory->where('entity_id', $request->input('entity_id'))
                                ->where("subclient_id", "0")->whereIn('service_id', $serviceRight);
            }
            $directory = $directory->orderBy("sort_order", "asc")->get()->toArray();
            //echo getSQL($directory);exit;
            // get all file
            $directoryFile = \App\Models\Backend\DirectoryEntityFile::where("directory_entity_id", $request->input('parent_id'))
                            ->where('move_to_trash', "0")->orderBy("file_name", "asc")->get()->toArray();

            if (!empty($subclienList)) {
                $directory = array_merge($directory, $subclienList);
            }
        }
        return createResponse(config('httpResponse.SUCCESS'), "Fetch Drive Sucessfully", ['data' => $directory, 'fileList' => $directoryFile]);
        /* } catch (\Exception $e) {
          app('log')->error("Directory listing failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing directory", ['error' => 'Server error.']);
          } */
    }

    /*
     * Create Folder by User
     */

    public function createCustomeFolder(Request $request) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'folder_name' => 'required',
            'parent_folder_id' => 'required'
                ], []);
        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        $folderName = $request->input('folder_name');
        $parentFolderId = $request->input('parent_folder_id');
        $parentDetail = \App\Models\Backend\DirectoryEntity::where("folder_id", $parentFolderId)->first();
        $checkFolder = \App\Models\Backend\DirectoryEntity::where("parent_id", $parentDetail->id)->where("directory_name", $folderName)->count();

        if ($checkFolder > 0) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => "You can not create folder with this name, Already Exits"]);



        //$folderDetail = \Storage::disk('google')->makeDirectory($parentFolderId . '/' . $folderName);
        //$dir = self::getId($parentFolderId, $folderName);
        $service = \Storage::disk('google')->getAdapter()->getService();
        $folder_meta = new \Google_Service_Drive_DriveFile(array(
            'parents' => [$parentFolderId],
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder'));
        $folder = $service->files->create($folder_meta, array(
            'fields' => 'id'));
        $dir = $folder->id;
        $makeFolder = 0;
        if ($parentDetail->make_folder > 0) {
            $makeFolder = $parentDetail->make_folder - 1;
        }
        if ($parentDetail->emptyFolder == 1) {
            \App\Models\Backend\DirectoryEntity::where("id", $parentDetail->id)->update(["emptyFolder" => "0"]);
        }
        if ($dir == null) {
            return createResponse(config('httpResponse.UNPROCESSED'), "Folder not Create Please try again later", ['error' => "Folder not Create Please try again later"]);
        }
        $filePath = GoogleDriveFileController::filePath($parentDetail->id);
        $newEntityFolder = \App\Models\Backend\DirectoryEntity::create(
                        array('entity_id' => $parentDetail->entity_id,
                            'parent_id' => $parentDetail->id,
                            'year' => $parentDetail->year,
                            'service_id' => $parentDetail->service_id,
                            'subclient_id' => $parentDetail->subclient_id,
                            'directory_id' => 0,
                            'directory_name' => trim($request->input('folder_name')),
                            'folder_id' => $dir,
                            'make_folder' => $makeFolder,
                            'directory_path' => $filePath,
                            "created_by" => app('auth')->guard()->id(),
                            "created_on" => date('Y-m-d H:i:s'),
                            "modified_by" => app('auth')->guard()->id(),
                            "modified_on" => date('Y-m-d H:i:s'))
        );
        \App\Models\Backend\DirectoryEntity::where("id", $newEntityFolder->id)->update(["sort_order" => $newEntityFolder->id]);

        return createResponse(config('httpResponse.SUCCESS'), "Folder create sucessfully", ['message' => 'Folder create sucessfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Directory creation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing directory", ['error' => 'Server error.']);
          } */
    }

    public static function createFolderForInformation($folderName, $parentFolderId, $type, $id) {
        // $folderName = $request->input('folder_name');
        // $parentFolderId = $request->input('parent_folder_id');
        $parentDetail = \App\Models\Backend\DirectoryEntity::where("folder_id", $parentFolderId)->first();
        $checkFolder = \App\Models\Backend\DirectoryEntity::where("parent_id", $parentDetail->id)->where("directory_name", $folderName);

        if ($checkFolder->count() > 0) // Return error message if validation fails
        {
            $checkFolder = $checkFolder->first();
            if ($type == 'information') {
            \App\Models\Backend\Information::where("id", $id)->update(["folder_id" => $checkFolder->folder_id]);
        } else {
            \App\Models\Backend\Query::where("id", $id)->update(["folder_id" => $checkFolder->folder_id]);
        }
        return;
        }
        //$folderDetail = \Storage::disk('google')->makeDirectory($parentFolderId . '/' . $folderName);
        //$dir = self::getId($parentFolderId, $folderName);
        $service = \Storage::disk('google')->getAdapter()->getService();
        $folder_meta = new \Google_Service_Drive_DriveFile(array(
            'parents' => [$parentFolderId],
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder'));
        $folder = $service->files->create($folder_meta, array(
            'fields' => 'id'));
        $dir = $folder->id;
        $makeFolder = 0;
        if ($parentDetail->make_folder > 0) {
            $makeFolder = $parentDetail->make_folder - 1;
        }
        if ($parentDetail->emptyFolder == 1) {
            \App\Models\Backend\DirectoryEntity::where("id", $parentDetail->id)->update(["emptyFolder" => "0"]);
        }
        if ($dir == null) {
            return createResponse(config('httpResponse.UNPROCESSED'), "Folder not Create Please try again later", ['error' => "Folder not Create Please try again later"]);
        }
        $filePath = GoogleDriveFileController::filePath($parentDetail->id);
        $newEntityFolder = \App\Models\Backend\DirectoryEntity::create(
                        array('entity_id' => $parentDetail->entity_id,
                            'parent_id' => $parentDetail->id,
                            'year' => $parentDetail->year,
                            'service_id' => $parentDetail->service_id,
                            'subclient_id' => $parentDetail->subclient_id,
                            'directory_id' => 0,
                            'directory_name' => trim($folderName),
                            'folder_id' => $dir,
                            'make_folder' => $makeFolder,
                            'directory_path' => $filePath,
                            "created_by" => app('auth')->guard()->id(),
                            "created_on" => date('Y-m-d H:i:s'),
                            "modified_by" => app('auth')->guard()->id(),
                            "modified_on" => date('Y-m-d H:i:s'))
        );
        \App\Models\Backend\DirectoryEntity::where("id", $newEntityFolder->id)->update(["sort_order" => $newEntityFolder->id]);
        if ($type == 'information') {
            \App\Models\Backend\Information::where("id", $id)->update(["folder_id" => $dir]);
        } else {
            \App\Models\Backend\Query::where("id", $id)->update(["folder_id" => $dir]);
        }
    }

    /*
     * Rename folder
     */

    public function renameFolder(Request $request) {
        // try {
        $validator = app('validator')->make($request->all(), [
            'folder_id' => 'required',
            'folder_name' => 'required'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        $folderId = $request->input('folder_id');
        // Rename for Custom Folder
        $folderDetail = \App\Models\Backend\DirectoryEntity::where("folder_id", $folderId)->first();

        if ($folderDetail->directory_id == 0) {
            $folderName = trim($request->get('folder_name'));
            \Storage::disk('google')->move($request->get('folder_id'), $folderName);
            \App\Models\Backend\DirectoryEntity::where("folder_id", $folderId)->update(["directory_name" => $folderName,
                "modified_by" => app('auth')->guard()->id(),
                "modified_on" => date('Y-m-d H:i:s')]);
            return createResponse(config('httpResponse.SUCCESS'), "Folder rename sucessfully", ['message' => 'Folder rename sucessfully']);
        } else {
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => "Can't Change Master Folder Name"]);
        }
        /* } catch (\Exception $e) {
          app('log')->error("Directory rename failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while rename directory", ['error' => 'Server error.']);
          } */
    }

    /*
     * Delete Folder if file not there
     */

    public function deleteFolder(Request $request) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'folder_id' => 'required'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        $folderId = $request->input('folder_id');
        $fileArray = explode(",", $request->input('folder_id'));

        // Delete File
        for ($i = 0; $i < count($fileArray); $i++) {
            // check Custom Folder
            $folderDetail = \App\Models\Backend\DirectoryEntity::where("folder_id", $folderId)->first();
            $checkFileANDFolder = self::checkFolderANDFile($folderDetail->id);

            if ($folderDetail->directory_id == 0 && $checkFileANDFolder == true) {
                $checkFileANDFolder = self::checkFolderANDFile($folderDetail->parent_id);
                if ($checkFileANDFolder == true) {
                    \App\Models\Backend\DirectoryEntity::where("id", $folderDetail->parent_id)->update(["emptyFolder" => 1]);
                }
                \Storage::disk('google')->deleteDirectory($request->get('folder_id'));
                \App\Models\Backend\DirectoryEntity::where("folder_id", $folderId)->delete();

                return createResponse(config('httpResponse.SUCCESS'), "Folder delete sucessfully", ['message' => 'Folder delete sucessfully']);
            } else {
                return createResponse(config('httpResponse.UNPROCESSED'), "Please Remove Child Folder and File", ['error' => "Please Remove Child Folder and File"]);
            }
        }

        return createResponse(config('httpResponse.SUCCESS'), "Folder delete sucessfully", ['message' => 'Folder delete sucessfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Directory delete failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while delete directory", ['error' => 'Server error.']);
          } */
    }

    public static function functionDeleteMasterFolder(Request $request) {
        $validator = app('validator')->make($request->all(), [
            'folder_id' => 'required'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        \Storage::disk('google')->deleteDirectory($request->get('folder_id'));
    }

    /*
     * Populate folder on particular entity
     */

    public function populateFolder(Request $request) {
        //try {
        ini_set('max_execution_time', '0');
        $validator = app('validator')->make($request->all(), [
            'year' => 'required',
            'entity_id' => 'required',
            'service_id' => 'required'
                ], []);
        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        $yearRight = checkButtonRights(182, 'year_folder');
        if ($yearRight == true) {
            $entityId = $request->input('entity_id');
            $service_id = $request->input('service_id');

            $entityFolder = \App\Models\Backend\Entity::select('id', 'folder_id', 'code')->where("id", $entityId)->first();
            if ($entityFolder->folder_id == '') {
                // create client folder

                \Storage::disk('google')->makeDirectory($entityFolder->code);
                $dir = self::getId("/", $entityFolder->code);
                if ($dir['path']) {
                    \App\Models\Backend\Entity::where("id", $entityId)->update(["folder_id" => $dir['path']]);
                    $directoryHierarchy = \App\Models\Backend\DirectoryMaster::get()->toArray();
                    $entityFolder->folder_id = $dir['path'];
                    // self::directoryHierarchy($directoryHierarchy, 0, $dir['path'], $entityId, $request->input('year'));
                }
            }
            $folder_id = $entityFolder->folder_id;
            $parentId = 0;
            if ($service_id == 1) {
                $subService = \App\Models\Backend\BillingBKRPH::leftJoin('billing_services as bs', function($query) {
                                    $query->on('bs.id', '=', 'billing_bk_rph.billing_id');
                                    $query->on('bs.is_active', '=', DB::raw("1"));
                                    $query->on('bs.is_latest', '=', DB::raw("1"));
                                })
                        ->select("billing_bk_rph.*")
                        ->where("billing_bk_rph.is_latest", "1")
                        ->where("bs.entity_id", $entityId);
                $agreeService[] = 1;
                $childService = array();
                if ($subService->count() != 0) {
                    $subService = $subService->get();
                    foreach ($subService as $row) {
                        if ($row->contract_signed_date == '0000-00-00') {
                            $childService[] = $row->service_id;
                        } else {
                            $agreeService[] = $row->service_id;
                        }
                    }
                } else {
                    $childService = [8, 9, 10, 11];
                }
                if ($request->input('subclient_folder_id') > 0) {
                    $subclientId = \App\Models\Backend\SubClient::where("folder_id", $request->input('subclient_folder_id'))->first();
                }
                $entityYear = \App\Models\Backend\DirectoryEntity::where('entity_id', $entityId)
                                ->where('year', $request->input('year'))->where("service_id", 1)->where("directory_id", "<", "285");
                if ($request->input('subclient_folder_id') > 0 && $subclientId->id > 0) {
                    $entityYear = $entityYear->where("subclient_id", $subclientId->id);
                }
                if ($entityYear->count() > 0) {
                    return createResponse(config('httpResponse.UNPROCESSED'), "For this entity or this year folder already geneated", ['error' =>
                        'For this entity or this year folder already geneated']);
                }

                $directoryHierarchy = \App\Models\Backend\DirectoryMaster::whereIn("service_id", $agreeService);

                if (count($childService) > 0) {
                    $directoryHierarchy = $directoryHierarchy->whereNotIn("service_id", $childService);
                }
                $directoryHierarchy = $directoryHierarchy->where("id", "<", "284")->get()->toArray();
            } else {

                $checkPayroll = \App\Models\Backend\DirectoryEntity::where("entity_id", $entityId)->where("service_id", 2);
                $directoryHierarchy = \App\Models\Backend\DirectoryMaster::where("service_id", 2);
                if ($checkPayroll->count() > 0) {
                    $directoryHierarchy = $directoryHierarchy->whereNotIn("id", ['400', '401', '402', '403', '449']);
                    $checkPayroll = $checkPayroll->where("directory_id", "400")->first();
                    $folder_id = $checkPayroll->folder_id;
                    $parentId = "400";
                }
                $directoryHierarchy = $directoryHierarchy->where("id", ">", "399");

                $entityYear = \App\Models\Backend\DirectoryEntity::where('entity_id', $entityId)->where('year', $request->input('year'))->where("service_id", 2);
                if ($entityYear->count() >= 60) {
                    return createResponse(config('httpResponse.UNPROCESSED'), "For this entity or this year folder already geneated", ['error' =>
                        'For this entity or this year folder already geneated']);
                } //else if ($entityYear->count() != 0) {
                //  $entityPayroll = \App\Models\Backend\DirectoryEntity::where('entity_id', $entityId)->where('directory_id', '327')->where("year", $request->input('year'))->first();
                // \Storage::disk('google')->deleteDirectory($entityPayroll->folder_id);
                // \App\Models\Backend\DirectoryEntity::where('entity_id', $entityId)->where("year", $request->input('year'))->whereNotIn("directory_id", ['323', '324', '325', '326'])->delete();
                //}
                $directoryHierarchy = $directoryHierarchy->orderBy("id", "asc")->get()->toArray();
            }
            //echo $subclientId->id;exit;
            if ($request->has('subclient_folder_id') && $request->input('subclient_folder_id') != '' && $subclientId->id > 0) {
                self::directoryLoop($directoryHierarchy, 0, $request->input('subclient_folder_id'), $entityId, $request->input('year'), $subclientId->id);
            } else {
                $entityId = $request->input('entity_id');
                self::directoryLoop($directoryHierarchy, $parentId, $folder_id, $entityId, $request->input('year'), 0);
            }
            return createResponse(config('httpResponse.SUCCESS'), "Folder create sucessfully", ['message' => 'Folder create sucessfully']);
        } else {
            return createResponse(config('httpResponse.UNPROCESSED'), "you don't have right to create folder", ['data' => "you don't have right to create folder"]);
        }
        /* } catch (\Exception $e) {
          app('log')->error("Directory creation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing directory", ['error' => 'Server error.']);
          } */
    }

    /*
     * Create subclient folder on list
     */

    public function createSubClientFolder($entityId, $subClient, $parentFolderId) {
        if ($parentFolderId != '') {
            $s = 1;
            $month = date("m");
            if ($month > 7) {
                $year = date("Y", strtotime("+1 Year"));
            } else {
                $year = date("Y");
            }
            foreach ($subClient as $sub) {
                $folderId = $sub->folder_id;
                if ($sub->folder_id == '' && $sub->folder_id == null) {
                    $sub->subclient = preg_replace('/[^A-Za-z0-9\-]/', ' ', $sub->subclient);
                    //$folderDetail = \Storage::disk('google')->makeDirectory($parentFolderId . '/' . $sub->subclient);
                    //$dir = self::getId($parentFolderId, $sub->subclient);
                    $service = \Storage::disk('google')->getAdapter()->getService();
                    $folder_meta = new \Google_Service_Drive_DriveFile(array(
                        'parents' => [$parentFolderId],
                        'name' => $sub->subclient,
                        'mimeType' => 'application/vnd.google-apps.folder'));
                    $folder = $service->files->create($folder_meta, array(
                        'fields' => 'id'));
                    \App\Models\Backend\SubClient::where("id", $sub->id)->update(["folder_id" => $folder->id]);
                    $folderId = $folder->id;
                }
                $checkSubclientFolder = \App\Models\Backend\DirectoryEntity::where("entity_id", $entityId)->where("subclient_id", $sub->id)->where("year", $year);
                $checksubfolder = \App\Models\Backend\DirectoryServiceCreation::where("entity_id", $entityId)->where("subclient_id", $sub->id)->where("year", $year);
                if ($checkSubclientFolder->count() == 0 && $folderId > 0 && $checksubfolder->count() == 0) {
                    $datainsert = array('entity_id' => $entityId,
                        "service_id" => 1,
                        "year" => $year,
                        "subclient_id" => $sub->id,
                        "folder_id" => $folderId,
                        "created_on" => date('y-m-d h:i:s'),
                        "created_by" => loginUser());
                    \App\Models\Backend\DirectoryServiceCreation::insert($datainsert);
                }
                $subclientList[] = array(
                    "id" => 0,
                    "entity_id" => $entityId,
                    "subclient_id" => $sub->id,
                    'parent_id' => 0,
                    'year' => '',
                    'directory_id' => '',
                    'directory_name' => $sub->subclient,
                    'directory_path' => '',
                    'make_folder' => 0,
                    'emptyFolder' => 0,
                    'sort_order' => $s,
                    'folder_id' => $folderId,
                    'created_on' => $sub->created_on,
                    'created_by' => $sub->created_by,
                    'modified_on' => $sub->modified_on,
                    'modified_by' => $sub->modified_by);
                $s++;
            }
        }
        return $subclientList;
    }

    /*
     * Get Folder and file Meta data from google drive
     */

    public static function getId($folderId, $name, $type = 'dir') {
        $dir = $folderId;
        $recursive = false; // Get subdirectories also?
        $contents = collect(\Storage::disk('google')->listContents($dir, $recursive));

        if ($type == 'file') {
            return $dir = $contents->where('type', '=', 'file')
                    ->where('filename', '=', pathinfo($name, PATHINFO_FILENAME))
                    ->where('extension', '=', pathinfo($name, PATHINFO_EXTENSION))
                    ->first();
        }
        return $dir = $contents->where('type', '=', 'dir')
                ->where('name', '=', $name)
                ->first();
    }

    /*
     * Create all entity and subclient year folder on one click
     */

    public static function entityYearwiseFolder() {
        $entityList = \App\Models\Backend\Entity::leftJoin('billing_services as bs', function($query) {
                                    $query->on('bs.entity_id', '=', 'entity.id');
                                })
                        ->whereIn("bs.service_id", [1, 2])
                        ->where("bs.is_active", "1")
                        ->where("bs.is_updated", "1")
                        ->where("bs.is_latest", "1")
                        ->where("entity.discontinue_stage", "!=", "2")->get();
        $month = date("m");
        if ($month > 5) {
            $year = date("Y", strtotime("+1 Year"));
        } else {
            $year = date("Y");
        }
        foreach ($entityList as $billing) {
            $checkDirectoryAlready = \App\Models\Backend\DirectoryServiceCreation::where("entity_id", $billing->entity_id)
                            ->where("service_id", $billing->service_id)->where("year", $year);
            if ($checkDirectoryAlready->count() == 0) {

                $checkbkService = \App\Models\Backend\DirectoryEntity::where("entity_id", $billing->entity_id)->where("year", $year)->where("service_id", $billing->service_id);

                if ($checkbkService->count() == 0) {
                    $datainsert = array('entity_id' => $billing->entity_id,
                        "service_id" => $billing->service_id,
                        "year" => $year,
                        "folder_id" => 0,
                        "created_on" => date('Y-m-d h:i:s'),
                        "created_by" => loginUser());

                    \App\Models\Backend\DirectoryServiceCreation::insert($datainsert);
                }
            }
            /*$subclientList = \App\Models\Backend\SubClient::where("entity_id", $billing->entity_id)->where("is_active", "1");
            if ($subclientList->count() > 0) {
                foreach ($subclientList->get() as $sub) {
                    $checkSubclientFolder = \App\Models\Backend\DirectoryEntity::where("entity_id", $billing->entity_id)->where("subclient_id", $sub->id)->where("year", $year);
                    $checksubfolder = \App\Models\Backend\DirectoryServiceCreation::where("entity_id", $billing->entity_id)->where("subclient_id", $sub->id)->where("year", $year);
                    if ($checkSubclientFolder->count() == 0 && $sub->folder_id > 0 && $checksubfolder->count() == 0) {
                        $datainsert1 = array('entity_id' => $billing->entity_id,
                            "service_id" => 1,
                            "year" => $year,
                            "subclient_id" => $sub->id,
                            "folder_id" => $sub->folder_id,
                            "created_on" => date('Y-m-d h:i:s'),
                            "created_by" => loginUser());
                        //showArray($datainsert1);
                        \App\Models\Backend\DirectoryServiceCreation::insert($datainsert1);
                    }
                }
            }*/
        }
    }

    public static function yearFolderCreate($entityId = 0) {
        ini_set('max_execution_time', '0');
        $entityList = \App\Models\Backend\DirectoryServiceCreation::leftjoin("entity as e", "e.id", "directory_service_creation.entity_id")
                        ->select("e.code", "directory_service_creation.*")
                        ->where("directory_service_creation.is_deleted", "0")->skip(0)->take(5);
        $entityList = $entityList->get();
        //showArray($entityList);exit;
        foreach ($entityList as $e) {
            $entityId = $e->entity_id;
            $code = $e->code;
            $service_id = $e->service_id;
            $folder_id = $e->folder_id;
            $year = $e->year;
            $subclient_id = $e->subclient_id;
            $entityFolderId = \App\Models\Backend\Entity::select("folder_id")->where("id", $entityId)->first();
            if ($entityFolderId->folder_id == '') {
                // create client folder
                \Storage::disk('google')->makeDirectory($code);
                $dir = self::getId("/", $code);
                // showArray($dir);exit;
                if ($dir['path']) {
                    \App\Models\Backend\Entity::where("id", $entityId)->update(["folder_id" => $dir['path']]);
                    //$directoryHierarchy = \App\Models\Backend\DirectoryMaster::get()->toArray();
                    $folder_id = $dir['path'];
                    // self::directoryHierarchy($directoryHierarchy, 0, $dir['path'], $entityId, $request->input('year'));
                }
            } else if ($folder_id == 0) {
                $folder_id = $entityFolderId->folder_id;
            }
            $parentId = 0;

            if ($service_id == 1) {
                // check for subclient

                $subService = \App\Models\Backend\BillingBKRPH::leftJoin('billing_services as bs', function($query) {
                                    $query->on('bs.id', '=', 'billing_bk_rph.billing_id');
                                    $query->on('bs.is_active', '=', DB::raw("1"));
                                    $query->on('bs.is_latest', '=', DB::raw("1"));
                                })
                        ->select("billing_bk_rph.*")
                        ->where("billing_bk_rph.is_latest", "1")
                        ->where("bs.entity_id", $entityId);
                $agreeService[] = 1;
                $childService = array();
                if ($subService->count() != 0) {
                    $subService = $subService->get();
                    foreach ($subService as $row) {
                        if ($row->contract_signed_date == '0000-00-00') {
                            $childService[] = $row->service_id;
                        } else {
                            $agreeService[] = $row->service_id;
                        }
                    }
                } else {
                    $childService = [8, 9, 10, 11];
                }
                /* $subClient = \App\Models\Backend\SubClient::where("entity_id", $entityId)->where("is_active", "1");
                  if ($subClient->count() > 0) {
                  foreach ($subClient->get() as $sub) {
                  if ($sub->folder_id != '') {
                  self::directoryHierarchy($directoryHierarchy, 0, $sub->folder_id, $entityId, $year, $sub->id);
                  }
                  }
                  } */
                $checkBK = \App\Models\Backend\DirectoryEntity::where("entity_id", $entityId)->where("service_id", 1)->where("year", $year)->where("subclient_id",$subclient_id);
                if ($checkBK->count() == 0 || ($subclient_id > 0)) {
                    $directoryHierarchy = \App\Models\Backend\DirectoryMaster::whereIn("service_id", $agreeService);

                    if (count($childService) > 0) {
                        $directoryHierarchy = $directoryHierarchy->whereNotIn("service_id", $childService);
                    }

                    $directoryHierarchy = $directoryHierarchy->where("id", "<", "284")->orderBy("id", "asc")->get()->toArray();
                } else {
                    continue;
                }
            } else if ($service_id == 2) {
                $checkPayroll = \App\Models\Backend\DirectoryEntity::where("entity_id", $entityId)->where("service_id", 2)->where("year", '0000');
                $directoryHierarchy = \App\Models\Backend\DirectoryMaster::where("service_id", 2);
                if ($checkPayroll->count() > 0) {
                    $directoryHierarchy = $directoryHierarchy->whereNotIn("id", ['400', '401', '402', '403', '449']);
                    $checkPayroll = $checkPayroll->where("directory_id", "400")->first();
                    $folder_id = $checkPayroll->folder_id;
                    $parentId = "400";
                }
                $directoryHierarchy = $directoryHierarchy->where("id", ">", "399")->orderBy("id", "asc")->get()->toArray();
            } else {

                $checkService = \App\Models\Backend\DirectoryEntity::where("entity_id", $entityId)->where("service_id", $service_id)->where("year", $year);
                if ($subclient_id > 0) {
                    $parentId = 1;
                    $checkService = $checkService->where("subclient_id", $subclient_id);
                }
                if ($checkService->count() == 0) {
                    $directoryHierarchy = \App\Models\Backend\DirectoryMaster::where("service_id", $service_id)->get()->toArray();
                } else {
                    continue;
                }
            }
            \App\Models\Backend\DirectoryServiceCreation::where("id", $e->id)->update(["is_deleted" => 1]);
            if ($subclient_id > 0) {
                $parentDetail = \App\Models\Backend\DirectoryEntity::where("id", $folder_id);
                if ($parentDetail->count() > 0) {
                    $parentDetail = $parentDetail->first();
                    $parentFolderId = $parentDetail->folder_id;
                } else {
                    $parentFolderId = $folder_id;
                }
                self::directoryHierarchy($directoryHierarchy, $parentId, $parentFolderId, $entityId, $year, $subclient_id);
            } else {
                self::directoryLoop($directoryHierarchy, $parentId, $folder_id, $entityId, $year, 0);
            }
        }
    }

    /*
     * Recursive function for create multiple folder creation
     */

    public static function directoryHierarchy($directory, $parentId, $parentFolderId, $entityId, $year, $subclientId = 0) {

        foreach ($directory as $row) {
            $d = $row;
            if ($d['id'])
                if ($parentId == 0 && $d['id'] == 2) {
                    $directoryPermanent = \App\Models\Backend\DirectoryEntity::where('entity_id', $entityId)->where("directory_id", "2");
                    if ($subclientId > 0) {
                        $directoryPermanent = $directoryPermanent->where("subclient_id", $subclientId);
                    }
                    if ($directoryPermanent->count() > 0) {
                        continue;
                    }
                }
            if ($d['parent_id'] == $parentId) {
                $p = self::createFolder($d['directory_name'], $parentFolderId, $entityId, $year, $d, $subclientId, $d['service_id']);
                self::directoryHierarchy($directory, $d['id'], $p, $entityId, $year, $subclientId); // should work better
            }
        }
        return;
    }

    public static function directoryLoop($directory, $parentId, $parentFolderId, $entityId, $year, $subclientId = 0) {

        foreach ($directory as $d) {
            if ($parentId == 0 && $d['id'] == 2) {
                $directoryPermanent = \App\Models\Backend\DirectoryEntity::where('entity_id', $entityId)->where("directory_id", "2");
                if ($subclientId > 0) {
                    $directoryPermanent = $directoryPermanent->where("subclient_id", $subclientId);
                }
                if ($directoryPermanent->count() > 0) {
                    continue;
                }
            }

            if ($d['parent_id'] > 0 && ($d['parent_id'] != 2 && $d['parent_id'] != 400 && $d['parent_id'] != 401 && $d['parent_id'] != 402 && $d['parent_id'] != 403)) {
                $parentDetail = \App\Models\Backend\DirectoryEntity::where("directory_id", $d['parent_id'])->where("entity_id", $entityId)->where("year", $year)->first();
                $parentFolderId = $parentDetail->folder_id;
            } else if ($d['parent_id'] == 2 || $d['parent_id'] == 400 || $d['parent_id'] == 401 || $d['parent_id'] == 402 || $d['parent_id'] == 403) {

                $parentDetail = \App\Models\Backend\DirectoryEntity::where("directory_id", $d['parent_id'])->where("entity_id", $entityId);
                if ($d['parent_id'] == 2) {
                    $parentDetail = $parentDetail->where("year", "0000")->first();
                }else{
                $parentDetail = $parentDetail->first();
                }
                $parentFolderId = $parentDetail->folder_id;
            }
            if ($parentFolderId != '') {
                $p = self::createFolder($d['directory_name'], $parentFolderId, $entityId, $year, $d, $subclientId, $d['service_id']);
            }
        }
        return;
    }

    public static function createFolder($folderName, $parentFolderId, $entityId, $year, $d, $subclient_id, $service_id) {
        $lastYear = $year - 1;
        $folderName = str_replace('LASTYEAR', $lastYear, $folderName);
        $folderName = str_replace('YEAR', $year, $folderName);
        $folderName = trim($folderName);
        $service = \Storage::disk('google')->getAdapter()->getService();
        $folder_meta = new \Google_Service_Drive_DriveFile(array(
            'parents' => [$parentFolderId],
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder'));
        $folder = $service->files->create($folder_meta, array(
            'fields' => 'id'));
        $parentDetail = \App\Models\Backend\DirectoryEntity::where("folder_id", $parentFolderId)->first();
        $parentId = 0;
        if ($parentDetail && $parentDetail->id > 0) {
            $parentId = $parentDetail->id;
        }
        if ($d['id'] == 2 || $d['id'] == 400 || $d['id'] == 401 || $d['id'] == 402 || $d['id'] == 403) {
            $year = 0;
        }
        $filePath = GoogleDriveFileController::filePath($parentId);
        \App\Models\Backend\DirectoryEntity::create([
            'entity_id' => $entityId,
            'subclient_id' => $subclient_id,
            'parent_id' => $parentId,
            'service_id' => $service_id,
            'year' => $year,
            'directory_id' => $d['id'],
            'directory_name' => $folderName,
            'folder_id' => $folder->id,
            'directory_path' => $filePath,
            'make_folder' => $d['is_dynamic_folder'],
            "emptyFolder" => $d['emptyFolder'],
            "sort_order" => $d['sort_order'],
            "created_by" => app('auth')->guard()->id(),
            "created_on" => date('Y-m-d H:i:s'),
            "modified_by" => app('auth')->guard()->id(),
            "modified_on" => date('Y-m-d H:i:s')
        ]);
        return $folder->id;
    }

    /* List of Folder in Particular entity and subclient year wise */

    public function folderList(Request $request) {
        //try{
        $validator = app('validator')->make($request->all(), [
            'entity_id' => 'required'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        $userHierarchy = getLoginUserHierarchy();
        if ($userHierarchy->designation_id != 7) {
            $serviceRight = ['1', '8', '9', '10', '11'];
            $teamRight = $userHierarchy->team_id != '' ? $userHierarchy->team_id : '0';
            $teamRight = explode(",", $teamRight);
            array_push($serviceRight, $teamRight);
        } else {
            $serviceRight = ['1', '2', '8', '9', '10', '11'];
        }
        $folderList = \App\Models\Backend\DirectoryEntity::where("entity_id", $request->input("entity_id"))
                ->whereIn("service_id", $serviceRight);

        if ($request->has("subclient_id") && $request->input("subclient_id") > 0) {
            $folderList = $folderList->where("subclient_id", $request->input("subclient_id"));
        } else {
            $folderList = $folderList->whereRaw("subclient_id = 0");
        }
        $folderList = $folderList->get();
        return createResponse(config('httpResponse.SUCCESS'), "Fetch Folder list  Sucessfully", ['data' => $folderList]);
        /* } catch (\Exception $e) {
          app('log')->error("Directory listing failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing directory", ['error' => 'Server error.']);
          } */
    }

    public function backlogFolder(Request $request) {
        //try{
        $validator = app('validator')->make($request->all(), [
            'entity_id' => 'required',
            'year' => 'required'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);
        $backlogRight = checkButtonRights(182, 'backlog');
        $year = $request->input('year');
        $entityId = $request->input('entity_id');
        $subclient_id = $request->input('subclient_id');
        $checkBacklog = \App\Models\Backend\DirectoryEntity::where("directory_id", "285")->where("year", $year)->where("entity_id", $entityId);
        if ($subclient_id > 0) {
            $checkBacklog = $checkBacklog->where("subclient_id", $subclient_id);
        }
        $checkBacklog = $checkBacklog->count();
        if ($checkBacklog > 0)
            return createResponse(config('httpResponse.UNPROCESSED'), "backlog folder already created for this year", ['data' => "backlog folder already created for this year"]);

        if ($backlogRight == true) {

            $checkBacklogFolder = \App\Models\Backend\DirectoryEntity::where("entity_id", $entityId)->where("directory_id", "284");
            if ($subclient_id > 0) {
                $checkBacklogFolder = $checkBacklogFolder->where("subclient_id", $subclient_id);
            }
            if ($checkBacklogFolder->count() == 0) {
                if ($subclient_id > 0) {
                    $folder_id = \App\Models\Backend\SubClient::where("id", $subclient_id)->select("folder_id")->first();
                } else {
                    $folder_id = \App\Models\Backend\Entity::where("id", $entityId)->select("folder_id")->first();
                }
                $directoryHierarchy = \App\Models\Backend\DirectoryMaster::where("id", ">=", "284")->where("service_id", "=", "1")->get()->toArray();
                $parentId = 0;
            } else {
                $folder_id = $checkBacklogFolder->first();
                $directoryHierarchy = \App\Models\Backend\DirectoryMaster::where("id", ">=", "285")->where("service_id", "=", "1")->get()->toArray();
                $parentId = 284;
            }

            self::directoryHierarchy($directoryHierarchy, $parentId, $folder_id->folder_id, $entityId, $year, $subclient_id);

            return createResponse(config('httpResponse.SUCCESS'), "Backlog Folder created Sucessfully", ['data' => 'Backlog Folder created Sucessfully']);
        } else {
            return createResponse(config('httpResponse.UNPROCESSED'), "you don't have right to create backlog folder", ['data' => "you don't have right to create backlog folder"]);
        }
        /* } catch (\Exception $e) {
          app('log')->error("Directory listing failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing directory", ['error' => 'Server error.']);
          } */
    }

    public static function checkFolderANDFile($id) {

        $folderFile = \App\Models\Backend\DirectoryEntityFile::where("directory_entity_id", $id)->where("move_to_trash", "0");
        $checkfolder = \App\Models\Backend\DirectoryEntity::where("parent_id", $id);
        if ($folderFile->count() == 0 && $checkfolder->count() == 0) {
            return true;
        }
        return false;
    }

    public static function RenameMasterFolder() {
        $getPayroll = \App\Models\Backend\DirectoryEntity::where("service_id", "2")->where("directory_id", "404")->where("directory_name", "Payroll FY-2021P")->get();
        //showArray($getPayroll);exit;
        foreach ($getPayroll as $p) {
            echo $p->folder_id . '<br/>';
            $folderName = trim($p->directory_name);
            \Storage::disk('google')->move($p->folder_id, $folderName);
            $p->update(["directory_name" => $folderName]);
            //sleep(2);            
            exit;
        }
    }

}

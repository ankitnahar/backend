<?php

namespace App\Http\Controllers\Backend\GoogleDrive;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use DB;
use ZipArchive;

class GoogleDriveFileController extends Controller {
    /*
     * Upload file in Directory
     */

    public function uploadFile(Request $request) {
        // try {
        $validator = app('validator')->make($request->all(), [
            'file_name' => 'required',
            'folder_id' => 'required'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        $folderId = $request->get('folder_id');
        $file = $request->file('file_name');
        $fileNameWithExt = $file->getClientOriginalName();
        if (strlen($fileNameWithExt) > 255) {
            return createResponse(config('httpResponse.UNPROCESSED'), "File Name Should be lessthen 255 charcter length.", ['error' => 'File Name Should be lessthen 255 charcter length']);
        }
        $parentDetail = \App\Models\Backend\DirectoryEntity::where("folder_id", $folderId)->first();
        $checkFile = \App\Models\Backend\DirectoryEntityFile::where("directory_entity_id", $parentDetail->id)->where("move_to_trash", "0")->where("file_name", $fileNameWithExt)->count();
        if ($checkFile > 0) {
            return createResponse(config('httpResponse.UNPROCESSED'), "This File Name File Already there", ['error' => 'This File Name File Already there']);
        }
        $checkFileInTrash = \App\Models\Backend\DirectoryEntityFile::where("directory_entity_id", $parentDetail->id)->where("move_to_trash", "1")->where("file_name", $fileNameWithExt);
       if ($checkFileInTrash->count() > 0) {
           foreach($checkFileInTrash->get() as $f){
            $fileId = $f->file_id;
            \Storage::disk('google')->delete($fileId);
            \App\Models\Backend\DirectoryEntityFile::where("file_id", $fileId)->delete();
           }
       }

        $fname = pathinfo($fileNameWithExt, PATHINFO_FILENAME);
        $fileExtension = $file->getClientOriginalExtension();
        $filename = $fname . "." . $fileExtension;
        $size = $file->getClientSize();
        $fileSize = $size / 1000;
        
        $uploadPath = public_path('/drivefiles');
        if ($file->move($uploadPath, $filename)) {
            $fileNameArray = explode(".", $filename);

            $filePath = public_path('/drivefiles/' . $filename);
            chmod(public_path('/drivefiles/' . $filename), 0777);
            //$filePath = File::get($filePath);
            if ($fileSize < 5000) {
                if ($fileNameArray[1] == 'txt') {
                    $fileData = file_get_contents($uploadPath . '/' . $filename);
                    //showArray($fileData);exit;
                    $filename = $fileNameArray[0] . '.docx';
                    file_put_contents($uploadPath . '/' . $filename, $fileData);
                    unlink(public_path($fileNameArray[0] . '.txt'));
                    $fileData = file_get_contents($uploadPath . '/' . $filename);
                    $upload = \Storage::disk('google')->put($folderId . '/' . $filename, $fileData);
                } else {
                    $fileContent = file_get_contents($filePath);
                    $upload = \Storage::disk('google')->put($folderId . "/" . $fname . "." . $fileExtension, $fileContent);
                }
            } else {
                $upload = \Storage::disk('google')->put($folderId . '/' . $filename, fopen(public_path('/drivefiles/' . $filename), "r+", "public"));
            }


            $dir = GoogleDriveFolderController::getId($folderId, $filename, 'file');
            $dirCsv = '';
            if ($dir['extension'] == 'csv') {
                $fileName = explode(".", $filename);
                $fileData = file_get_contents($uploadPath . "/" . $fname . "." . $fileExtension);
                $upload = \Storage::disk('google')->put($folderId . '/' . $fileName[0] . '.xlsx', $fileData);
                $dirCSVFile = GoogleDriveFolderController::getId($folderId, $fileName[0] . '.xlsx', 'file');
                $dirCsv = $dirCSVFile['basename'];
            }
            $filePath = self::filePath($parentDetail->id);
            $fileId = \App\Models\Backend\DirectoryEntityFile::create(
                            ["directory_entity_id" => $parentDetail->id,
                                "entity_id" => $parentDetail->entity_id,
                                "service_id" => $parentDetail->service_id,
                                "file_name" => $filename,
                                "file_id" => $dir['basename'],
                                "csv_excel_file_id" => $dirCsv,
                                "mime_type" => $dir['mimetype'],
                                "path" => $filePath,
                                "size" => $size,
                                "extention" => $dir['extension'],
                                "created_by" => app('auth')->guard()->id(),
                                "created_on" => date('Y-m-d H:i:s'),
                                "modified_by" => app('auth')->guard()->id(),
                                "modified_on" => date('Y-m-d H:i:s')]);

            unlink($uploadPath . '/' . $filename);
            if ($parentDetail->emptyFolder == 1) {
                \App\Models\Backend\DirectoryEntity::where("id", $parentDetail->id)->update(["emptyFolder" => 0]);
            }
        }
        return createResponse(config('httpResponse.SUCCESS'), "File Upload Sucessfully", ['data' => $fileId]);
        /* } catch (\Exception $e) {
          app('log')->error("Directory creation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing directory", ['error' => 'Server error.']);
          } */
    }

    public function uploadFileFromDrive(Request $request) {
        // try {
        $validator = app('validator')->make($request->all(), [
            'file_array' => 'required|json',
            'folder_id' => 'required',
            'oAuthToken' => 'required'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        $fileData = \GuzzleHttp\json_decode($request->input('file_array'));

        foreach ($fileData as $f) {
            $oAuthToken = $request->input('oAuthToken');
            $fileId = $f->id;
            $getUrl = 'https://www.googleapis.com/drive/v2/files/' . $fileId . '?alt=media';
            $authHeader = 'Authorization: Bearer ' . $oAuthToken;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $getUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array($authHeader));
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            $data = curl_exec($ch);
            // $error = curl_error($ch);

            $extention = config('constant.mimetype');
            $fileNa = array();
            if ($f->serviceId == "spread" || $f->serviceId == "doc") {
                $name = $f->name;
                $ex = $extention[$f->mimeType];
                $filename = $f->name . '.' . $ex;
            } else {
                $fileNa = explode(".", $f->name);
                if (count($fileNa) > 0) {
                    $filename = $fileNa[0] . '.' . $fileNa[count($fileNa) - 1];
                    $name = $fileNa[0];
                    $ex = $fileNa[count($fileNa) - 1];
                }
            }
            if (strlen($filename) > 255) {
                return createResponse(config('httpResponse.UNPROCESSED'), "File Name Should be lessthen 255 charcter length.", ['error' => 'File Name Should be lessthen 255 charcter length']);
            }
            $folderId = $request->get('folder_id');
            $parentDetail = \App\Models\Backend\DirectoryEntity::where("folder_id", $folderId)->first();
            $checkFile = \App\Models\Backend\DirectoryEntityFile::where("directory_entity_id", $parentDetail->id)->where("move_to_trash", "0")->where("file_name", $filename)->count();
            if ($checkFile > 0) {
                return createResponse(config('httpResponse.UNPROCESSED'), "This File Name File Already there", ['error' => 'This File Name File Already there']);
            }
            //echo $filename;
            $uploadPath = public_path('/drivefiles');
            file_put_contents($uploadPath . '/' . $filename, $data);
            chmod($uploadPath . '/' . $filename, 0777);
            $size = $f->sizeBytes / 1000;

            //$filep = $uploadPath. '/' . $filename;
            $filData = file_get_contents($uploadPath . '/' . $filename);
            //$filData = File::get(public_path('drivefiles/'.$filename));

            $upload = \Storage::disk('google')->put($folderId . '/' . $name . "." . $ex, $filData);


            $dir = GoogleDriveFolderController::getId($folderId, $filename, 'file');
            $dirCsv = '';
            if ($dir['extension'] == 'csv') {
                $fileData = file_get_contents($uploadPath . '/' . $filename);
                $fileName = explode(".", $filename);
                $upload = \Storage::disk('google')->put($folderId . '/' . $fileName[0] . '.xlsx', $fileData);
                $dirCSVFile = GoogleDriveFolderController::getId($folderId, $fileName[0] . '.xlsx', 'file');
                $dirCsv = $dirCSVFile['basename'];
            }
            $filePath = self::filePath($parentDetail->id);
            $fileId = \App\Models\Backend\DirectoryEntityFile::create(
                            ["directory_entity_id" => $parentDetail->id,
                                "entity_id" => $parentDetail->entity_id,
                                "file_name" => $filename,
                                "file_id" => $dir['basename'],
                                "csv_excel_file_id" => $dirCsv,
                                "mime_type" => $dir['mimetype'],
                                "path" => $filePath,
                                "size" => $size,
                                "extention" => $dir['extension'],
                                "created_by" => app('auth')->guard()->id(),
                                "created_on" => date('Y-m-d H:i:s'),
                                "modified_by" => app('auth')->guard()->id(),
                                "modified_on" => date('Y-m-d H:i:s')]);

            unlink($uploadPath . '/' . $filename);
            if ($parentDetail->emptyFolder == 1) {
                \App\Models\Backend\DirectoryEntity::where("id", $parentDetail->id)->update(["emptyFolder" => 0]);
            }
        }


        return createResponse(config('httpResponse.SUCCESS'), "File Upload Sucessfully", ['data' => 'File Upload Sucessfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Directory creation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing directory", ['error' => 'Server error.']);
          } */
    }

    public function createFile(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'file_name' => 'required',
                'folder_id' => 'required'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);
            $parentDetail = \App\Models\Backend\DirectoryEntity::where("folder_id", $request->input('folder_id'))->first();
            $filename = $request->input('file_name');
            if (strlen($filename) > 255) {
                return createResponse(config('httpResponse.UNPROCESSED'), "File Name Should be lessthen 255 charcter length.", ['error' => 'File Name Should be lessthen 255 charcter length']);
            }
            $checkFile = \App\Models\Backend\DirectoryEntityFile::where("directory_entity_id", $parentDetail->id)->where("move_to_trash", "0")->where("file_name", $filename)->count();
            if ($checkFile > 0) {
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => "File name already exist"]);
            }
            $fileExt = explode(".", $filename);
            if ($fileExt[1] == 'pptx') {
                $fileData = file_get_contents(public_path() . '/blankppt/blank.pptx');

                $file = \Storage::disk('google')->put($request->input('folder_id') . '/' . $filename, $fileData);
            } else {
                $file = \Storage::disk('google')->put($request->input('folder_id') . '/' . $filename, '  ');
            }



            $dir = GoogleDriveFolderController::getId($request->input('folder_id'), $filename, 'file');
            $filePath = self::filePath($parentDetail->id);
            $fileId = \App\Models\Backend\DirectoryEntityFile::create(
                            ["directory_entity_id" => $parentDetail->id,
                                "entity_id" => $parentDetail->entity_id,
                                "file_name" => $filename,
                                "file_id" => $dir['basename'],
                                "mime_type" => $dir['mimetype'],
                                "path" => $filePath,
                                "size" => 0,
                                "extention" => $dir['extension'],
                                "created_by" => app('auth')->guard()->id(),
                                "created_on" => date('Y-m-d H:i:s'),
                                "modified_by" => app('auth')->guard()->id(),
                                "modified_on" => date('Y-m-d H:i:s')]);
            if ($parentDetail->emptyFolder == 1) {
                \App\Models\Backend\DirectoryEntity::where("id", $parentDetail->id)->update(["emptyFolder" => 0]);
            }

            return createResponse(config('httpResponse.SUCCESS'), "File create sucessfully", ['message' => 'File create sucessfully']);
        } catch (\Exception $e) {
            app('log')->error("Directory export failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while export directory", ['error' => 'Server error.']);
        }
    }

    /*
     * Get File Path
     */

    public static function filePath($id) {
        $filepath = '';
        $folderData = DB::select("CALL get_directory_hierarchy($id)");
        foreach ($folderData as $f) {
            $filepath = $filepath . '/' . $f->directory_name;
        }
        return $filepath;
    }

    /*
     * Rename File name
     */

    public function renameFile(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'file_id' => 'required',
                'file_name' => 'required',
                'folder_id' => 'required'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            if (strlen($request->input('file_name')) > 255) {
                return createResponse(config('httpResponse.UNPROCESSED'), "File Name Should be lessthen 255 charcter length.", ['error' => 'File Name Should be lessthen 255 charcter length']);
            }

            $fileId = $request->input('file_id');
            // Rename for Custom Folder
            $fileDetail = \App\Models\Backend\DirectoryEntityFile::where("file_id", $fileId);

            if ($fileDetail->count() > 0) {
                $fileDetail = $fileDetail->first();
                \Storage::disk('google')->move($request->get('folder_id') . '/' . $fileId, $request->get('folder_id') . '/' . $request->get('file_name'));
                \App\Models\Backend\DirectoryEntityFile::where("file_id", $fileId)->update(["file_name" => $request->input('file_name'),
                    "modified_by" => app('auth')->guard()->id(),
                    "modified_on" => date('Y-m-d H:i:s')]);
                return createResponse(config('httpResponse.SUCCESS'), "File rename sucessfully", ['message' => 'File rename sucessfully']);
            } else {
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => "Can't Change Master Folder Name"]);
            }
        } catch (\Exception $e) {
            app('log')->error("File rename failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while rename file", ['error' => 'Server error.']);
        }
    }

    /*
     * Download File
     */

    public function downloadFile(Request $request) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'file_id' => 'required'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);
        $fileId = $request->input('file_id');
        $fileArray = explode(",", $request->input('file_id'));

        // Delete File
        for ($i = 0; $i < count($fileArray); $i++) {
            $fileId = $fileArray[$i];
            $rawData = \Storage::disk('google')->get($fileId);
            $fileData = \App\Models\Backend\DirectoryEntityFile::where('file_id', $fileId)->where("move_to_trash", "0")->first();
            $fileName[] = $fileData->file_name;
            $fi = file_put_contents(public_path() . '/DownloadFiles/' . $fileData->file_name, $rawData);
        }
        if (count($fileArray) > 1) {
            return self::createZip($fileName);
        } else {
            return response()->download(public_path() . '/DownloadFiles/' . $fileName[0]);
        }
        /* return response($rawData, 200)
          ->header('ContentType', $file->mimetype)
          ->header('Content-Disposition', "attachment; filename='$file->filename"); */
        //return createResponse(config('httpResponse.SUCCESS'), "Folder create sucessfully", ['message' => 'Folder create sucessfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Directory creation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while download file", ['error' => 'Server error.']);
          } */
    }

    public static function createZip($fileArray) {
        //showArray($fileArray);exit;
        $date = date("Y-m-d H-i-s");
        $zipFileName = 'Documents ' . $date . '.zip';
        $zip = new \ZipArchive();
        $filePath = public_path() . '/DownloadFiles/';
        if ($zip->open(public_path('/DownloadFiles/'.$zipFileName), ZipArchive::CREATE) === TRUE) {
            for ($i = 0; $i < count($fileArray); $i++) {
                $zip->addFile($filePath . $fileArray[$i], $fileArray[$i]);
            }
            $zip->close();
        }

        $filetopath = $filePath . '/' . $zipFileName;
        if (file_exists($filetopath)) {
            $headers = array('Content-Type' => 'application/octet-stream',
                'Content-disposition: attachment; filename = ' . $filetopath);

            $response = response()->download($filetopath);
            register_shutdown_function('removeDirWithFiles', $filetopath);
            return $response;
        }
    }

    /*
     * Move File one folder to other
     */

    public function moveFile(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'file_id' => 'required',
                'folder_id' => 'required'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $folderId = \App\Models\Backend\DirectoryEntity::where('folder_id', $request->input('folder_id'))->first();
            $fileArray = explode(",", $request->input('file_id'));

            // Delete File
            for ($i = 0; $i < count($fileArray); $i++) {
                $fileId = $fileArray[$i];
                $fileData = \App\Models\Backend\DirectoryEntityFile::leftjoin("directory_entity as de", "de.id", "directory_entity_file.directory_entity_id")
                                ->select("directory_entity_file.file_name", "de.folder_id as previous_folder_id", "de.entity_id")
                                ->where('file_id', $fileId)->first();

                \Storage::disk('google')->move($fileData->previous_folder_id . '/' . $fileId, $request->input('folder_id') . '/' . $fileData->file_name);
                $filePath = self::filePath($folderId->id);
                \App\Models\Backend\DirectoryEntityFile::where("file_id", $fileId)->update(
                        ["directory_entity_id" => $folderId->id, "path" => $filePath]);
            }

            if ($folderId->emptyFolder == 1) {
                \App\Models\Backend\DirectoryEntity::where("id", $folderId->id)->update(["emptyFolder" => 0]);
            }

            return createResponse(config('httpResponse.SUCCESS'), "File moved sucessfully", ['message' => 'File moved sucessfully']);
        } catch (\Exception $e) {
            app('log')->error("File Deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while file Deletion", ['error' => 'Server error.']);
        }
    }

    /*
     * Copy File
     */

    public function copyFile(Request $request) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'file_id' => 'required',
            'folder_id' => 'required'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);
        $fileArray = explode(",", $request->input('file_id'));

        // Delete File
        for ($i = 0; $i < count($fileArray); $i++) {
            $fileId = $fileArray[$i];
            $fileData = \App\Models\Backend\DirectoryEntityFile::leftjoin("directory_entity as de", "de.id", "directory_entity_file.directory_entity_id")
                            ->select("directory_entity_file.file_name", "directory_entity_file.size", "de.folder_id as previous_folder_id", "de.entity_id")
                            ->where('file_id', $fileId)->first();
            $folderData = \App\Models\Backend\DirectoryEntity::where('folder_id', $request->input('folder_id'));
            if ($folderData->count() == 0) {// Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Can not paste in this folder", ['error' => 'Can not paste in this folder']);
            }
            $folderData = $folderData->first();
            $filename = $fileData->file_name;
            $checkFileName = \App\Models\Backend\DirectoryEntityFile::where("directory_entity_id", $folderData->id)->where("file_name", $filename)->count();
            if ($checkFileName > 0) {
                $filename = 'Copy ' . $filename . rand(6, 100000);
            }
            if (strlen($filename) > 255) {
                return createResponse(config('httpResponse.UNPROCESSED'), "File Name Should be lessthen 255 charcter length.", ['error' => 'File Name Should be lessthen 255 charcter length']);
            }
            \Storage::disk('google')->copy($fileData->previous_folder_id . '/' . $fileId, $request->input('folder_id') . '/' . $filename);

            $dir = GoogleDriveFolderController::getId($request->input('folder_id'), $filename, 'file');
            $filePath = self::filePath($folderData->id);
            $fileId = \App\Models\Backend\DirectoryEntityFile::create(
                            ["directory_entity_id" => $folderData->id,
                                "entity_id" => $folderData->entity_id,
                                "service_id" => $folderData->service_id,
                                "file_name" => $filename,
                                "file_id" => $dir['basename'],
                                "mime_type" => $dir['mimetype'],
                                "path" => $filePath,
                                "size" => $fileData->size,
                                "created_by" => app('auth')->guard()->id(),
                                "created_on" => date('Y-m-d H:i:s'),
                                "modified_by" => app('auth')->guard()->id(),
                                "modified_on" => date('Y-m-d H:i:s')]);
            if ($folderData->emptyFolder == 1) {
                \App\Models\Backend\DirectoryEntity::where("id", $folderData->id)->update(["emptyFolder" => 0]);
            }
        }
        return createResponse(config('httpResponse.SUCCESS'), "File copy sucessfully", ['message' => 'File copy sucessfully']);
        /* } catch (\Exception $e) {
          app('log')->error("File Deletion failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while file copy", ['error' => 'Server error.']);
          } */
    }

    /*
     * Share file and give edit right
     */

    public function shareFile(Request $request) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'file_id' => 'required',
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);
        $fileId = $request->input('file_id');
        $user = \App\Models\User::select('id', 'email')->find(loginUser());
        $alreadyPermission = \App\Models\Backend\DirectoryFilePermission::where('email', $user->email)->where('file_id', $fileId);
        \App\Models\Backend\DirectoryEntityFile::where('file_id', $fileId)->update(['modified_on' => date('Y-m-d H:i:s'), 'modified_by' => loginUser()]);
        if ($alreadyPermission->count() == 0) {
            // Change permissions
            // - https://developers.google.com/drive/v3/web/about-permissions
            // - https://developers.google.com/drive/v3/reference/permissions
            $service = \Storage::disk('google')->getAdapter()->getService();
            $permission = new \Google_Service_Drive_Permission();
            $fileExtention = \App\Models\Backend\DirectoryEntityFile::where('file_id', $fileId)->first();
            $permission->setRole('writer');
            if ($fileExtention->extention == 'csv') {
                $fileId = $fileExtention->csv_excel_file_id;
            }
            $permission->setType('user');
            //$date = mktime(H, i, s, d, m, Y);
            //$expirationTime = date(DATE_ATOM, strtotime(date("Y-m-d H:i:s"), strtotime("+3 hour")));
            //$permission->setExpirationTime($expirationTime);
            $permission->setEmailAddress($user->email);
            // $permission->setAllowFileDiscovery(false);
            $permissions = $service->permissions->create($fileId, $permission);
            //showarray($permissions->id);exit;
            \App\Models\Backend\DirectoryFilePermission::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'permission_id' => $permissions->id,
                'file_id' => $fileId,
                'created_on' => date('Y-m-d H:i:s'),
                'created_by' => loginUser()
            ]);
        }

        return createResponse(config('httpResponse.SUCCESS'), "Share file sucessfully", ['message' => 'Share file sucessfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Directory share failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while share directory", ['error' => 'Server error.']);
          } */
    }

    public function moveAndrestoreTrash(Request $request) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'file_id' => 'required',
            'trash' => 'required|in:1,0'
                ], []);
        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);
        $files = explode(",", $request->input('file_id'));
        $parentId = 0;
        for ($i = 0; $i < count($files); $i++) {
            $fileFolderId = \App\Models\Backend\DirectoryEntityFile::where("file_id", $files[$i])->first();
            \App\Models\Backend\DirectoryEntityFile::where("file_id", $files[$i])
                    ->update(["move_to_trash" => $request->input('trash'), "modified_on" => date('Y-m-d H:i:s'), "modified_by" => loginUser()]);
            $parentId = $fileFolderId->directory_entity_id;

            if ($request->input('trash') == 1) {
                \App\Models\Backend\WorksheetDocument::where("document_name", $files[$i])->update(["is_deleted" => 1]);
            }
        }
        if ($parentId != 0) {
            $checkFileFolder = GoogleDriveFolderController::checkFolderANDFile($parentId);
            if ($checkFileFolder) {
                \App\Models\Backend\DirectoryEntity::where("id", $parentId)->update(["emptyFolder" => 1]);
            }
        }
        $trashMessage = 'Move to Trash';
        if ($request->input('trash') == 0) {
            $trashMessage = 'Restore';
        }
        return createResponse(config('httpResponse.SUCCESS'), "File " . $trashMessage . " Sucessfully", ['message' => 'File ' . $trashMessage . ' sucessfully']);
        /* } catch (\Exception $e) {
          app('log')->error("File moved failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while file moved", ['error' => 'Server error.']);
          } */
    }

    public function searchFile(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'required',
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);


            $type = $request->has('type') ? $request->input('type') : '';
            $modified_on_from = $request->has('modified_on_from') ? $request->input("modified_on_from") : '';
            $modified_on_to = $request->has('modified_on_to') ? $request->input("modified_on_to") : '';
            $file_name = $request->has('file_name') ? $request->input('file_name') : '';
            $path = $request->has('path') ? $request->input('path') : '';
            $result = \App\Models\Backend\DirectoryEntityFile::
                    where("entity_id", $request->input('entity_id'))->where("move_to_trash", "0");
            if ($file_name != '') {
                $result = $result->whereRaw("file_name LIKE '%$file_name%'");
            }
            if ($type != '') {
                $typeArray = explode(",", $type);
                for ($i = 0; $i < count($typeArray); $i++) {
                    if ($i == 0) {
                        $orResult = "( extention = '$typeArray[$i]'";
                    } else {
                        $orResult = $orResult . " OR extention = '$typeArray[$i]'";
                    }
                }
                $orResult = $orResult . ")";
                $result->whereRaw($orResult);
            }
            if ($modified_on_from != '' && $modified_on_to != '') {
                $result = $result->whereRaw("(DATE(modified_on) >= '$modified_on_from') AND DATE(modified_on) <='$modified_on_to'");
            }
            if($path!=''){
                $result = $result->where("path",$path);
            }
            // echo getSQL($result);exit;
            $count = $result->count();
            if ($count == 0) {
                return createResponse(config('httpResponse.SUCCESS'), "No File Found", ['data' => array(), 'count' => 0]);
            } else {
                $result = $result->get();
                return createResponse(config('httpResponse.SUCCESS'), "File Found", ['data' => $result, 'count' => $count]);
            }
        } catch (\Exception $e) {
            app('log')->error("Directory search failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while search directory", ['error' => 'Server error.']);
        }
    }

    public function exportFile(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'file_id' => 'required',
                'folder_id' => 'required'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);


            $service = \Storage::disk('google')->getAdapter()->getService();
            $mimeType = 'application/pdf';
            $export = $service->files->export($request->input('file_id'), $mimeType);

            return response($export->getBody(), 200, $export->getHeaders());
            // return createResponse(config('httpResponse.SUCCESS'), "File export sucessfully", ['message' => 'File export sucessfully']);
        } catch (\Exception $e) {
            app('log')->error("Directory export failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while export directory", ['error' => 'Server error.']);
        }
    }

    public function convertFile(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'file_id' => 'required',
                'file_name' => 'required'
                    ], []);
            $fileDetail = \App\Models\Backend\DirectoryEntityFile::where("file_id", $request->input('file_id'))->first();
            $parentDetail = \App\Models\Backend\DirectoryEntity::where("id", $fileDetail->directory_entity_id)->first();
            $rawData = \Storage::disk('google')->get($fileDetail->file_id);
            $filename = $request->input('file_name');
            $file = \Storage::disk('google')->put($parentDetail->folder_id . '/' . $filename, $rawData);


            $dir = GoogleDriveFolderController::getId($parentDetail->folder_id, $filename, 'file');
            $filePath = self::filePath($parentDetail->id);
            $fileId = \App\Models\Backend\DirectoryEntityFile::create(
                            ["directory_entity_id" => $parentDetail->id,
                                "entity_id" => $parentDetail->entity_id,
                                "file_name" => $filename,
                                "file_id" => $dir['basename'],
                                "mime_type" => $dir['mimetype'],
                                "path" => $filePath,
                                "size" => $dir['size'],
                                "created_by" => app('auth')->guard()->id(),
                                "created_on" => date('Y-m-d H:i:s'),
                                "modified_by" => app('auth')->guard()->id(),
                                "modified_on" => date('Y-m-d H:i:s')]);
            return createResponse(config('httpResponse.SUCCESS'), "Convert file sucessfully", ['message' => 'Convert file sucessfully']);
        } catch (\Exception $e) {
            app('log')->error("Directory share failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while share directory", ['error' => 'Server error.']);
        }
    }

    /*
     * Revoke All File permission to user at the end of day
     */

    public static function revokePermission() {
        try {
            $filePermission = \App\Models\Backend\DirectoryFilePermission::get()->toArray();
            foreach ($filePermission as $f) {

                $service = \Storage::disk('google')->getAdapter()->getService();

                $permission = new \Google_Service_Drive_Permission();
                $permissions = $service->permissions->listPermissions($f['file_id']);

                foreach ($permissions->permissions as $p) {
                    if ($p['role'] == 'owner') {
                        continue;
                    }
                    $permissions = $service->permissions->delete($f['file_id'], $p['id']);
                }
//
                // }
                \App\Models\Backend\DirectoryFilePermission::where('file_id', $f['file_id'])->delete();
            }
            return createResponse(config('httpResponse.SUCCESS'), "Share file sucessfully", ['message' => 'Share file sucessfully']);
        } catch (\Exception $e) {
            app('log')->error("Directory share failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while share directory", ['error' => 'Server error.']);
        }
    }

    /*
     * File Delete Permanent from cron
     * we will delete file after 30 days
     */

    public static function deleteFilePermanent() {
        try {
            $fileArray = \App\Models\Backend\DirectoryEntityFile::where("move_to_trash", "1")
                            ->whereRaw("DATE(modified_on) < DATE_ADD(NOW(), INTERVAL - 30 DAY)")->get();
            foreach ($fileArray as $file) {
                $fileId = $file->file_id;
                \Storage::disk('google')->delete($fileId);
                \App\Models\Backend\DirectoryEntityFile::where("file_id", $fileId)->delete();
            }
            return createResponse(config('httpResponse.SUCCESS'), "File Delete sucessfully", ['message' => 'File Delete sucessfully']);
        } catch (\Exception $e) {
            app('log')->error("File Deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while file Deletion", ['error' => 'Server error.']);
        }
    }

    public function fileList(Request $request) {
        //try{
        $validator = app('validator')->make($request->all(), [
            'entity_id' => 'required',
            'year' => 'required'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        $fileList = \App\Models\Backend\DirectoryEntityFile::where("entity_id", $request->input("entity_id"))
                ->where("move_to_trash", "0");
        $fileList = $fileList->get();
        return createResponse(config('httpResponse.SUCCESS'), "Fetch Folder list  Sucessfully", ['data' => $fileList]);
        /* } catch (\Exception $e) {
          app('log')->error("Directory listing failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing directory", ['error' => 'Server error.']);
          } */
    }

    public static function completeFile(Request $request) {
        //try{
        $validator = app('validator')->make($request->all(), [
            'file_id' => 'required',
            'is_completed' => 'required'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        \App\Models\Backend\DirectoryEntityFile::where("file_id", $request->input('file_id'))->where("move_to_trash", "0")->update(['is_completed' => $request->input('is_completed')]);
        return createResponse(config('httpResponse.SUCCESS'), "File move to completed Sucessfully", ['data' => 'File move to completed Sucessfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Directory listing failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing directory", ['error' => 'Server error.']);
          } */
    }

    public static function moveToXero(Request $request) {
        //try{
        $validator = app('validator')->make($request->all(), [
            'file_id' => 'required',
            'xero_email_id' => 'required'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);
        $fileArray = explode(",", $request->input('file_id'));

        // Delete File
        for ($i = 0; $i < count($fileArray); $i++) {
            $fileId = $fileArray[$i];
            $rawData = \Storage::disk('google')->get($fileId);
            $fileData = \App\Models\Backend\DirectoryEntityFile::where('file_id', $fileId)->where("move_to_trash", "0")->first();
            $fileName = $fileData->file_name;
            $filePath = storage_path() . '/xero/' . $fileData->file_name;
            $fi = file_put_contents($filePath, $rawData);

            $data['to'] = $request->input('xero_email_id');
            $data['from'] = 'noreply-bdms@befree.com.au';
            $data['from_name'] = 'Noreply';
            $data['subject'] = $fileName;
            $data['content'] = 'test';
            $data['attachment'] = array('path' => $filePath, 'filename' => $fileName);

            $data['filePath'] = $filePath;
            $data['fileName'] = $fileName;
            storeMail('', $data);
           /* $emailSend = \Illuminate\Support\Facades\Mail::send([], [], function($message) use ($data) {
                        $message->from($data['from']);
                        $message->replyTo($data['from']);
                        $message->to($data['to']);

                        if (!empty($data['attachment'])) {
                            $message->attach($data['filePath']);
                        }
                        $message->subject($data['subject']);
                        $message->setBody($data['content'], 'text/html');
                    });*/
            unlink($data['filePath']);
        }

        /* } catch (\Exception $e) {
          app('log')->error("Directory listing failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing directory", ['error' => 'Server error.']);
          } */
    }

}

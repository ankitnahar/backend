<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class RemoveFilesCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'remove:file';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit Invoice create';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
       // try {
             $dfiles = glob(public_path('/DownloadFiles/*')); // get all file names			
            foreach ($dfiles as $file) {// iterate files
                if (is_dir($file)) {
                    if (count(glob($file . "/*")) === 0) {
                        rmdir($file);
                    } else {
                        //echo $file;

                        $Folderfiles = glob($file . '/*');
                        foreach ($Folderfiles as $f) {
                            if (is_file($f))
                                unlink($f);
                            if (is_dir($f)) {
                                if (count(glob($f . "/*")) === 0) {
                                    rmdir($f);
                                } else {
                                    $innerFolderfiles = glob($f . '/*');
                                    foreach ($innerFolderfiles as $innerf) {
                                        if (is_file($innerf))
                                            unlink($innerf);
                                        if (is_dir($innerf)) {
                                            rmdir($innerf);
                                        }
                                    }
                                }
                                rmdir($f);
                            }
                        }
                        rmdir($file);
                    } // delete file
                }else{
                     if (is_file($file))
                        unlink($file);
                }
            }    
            
            
            $dfiles = glob(storageEfs('/uploads/food/*')); // get all file names			
            foreach ($dfiles as $file) {// iterate files
                if (is_dir($file)) {
                    if (count(glob($file . "/*")) === 0) {
                        rmdir($file);
                    } else {
                        //echo $file;

                        $Folderfiles = glob($file . '/*');
                        foreach ($Folderfiles as $f) {
                            if (is_file($f))
                                unlink($f);
                            if (is_dir($f)) {
                                if (count(glob($f . "/*")) === 0) {
                                    rmdir($f);
                                } else {
                                    $innerFolderfiles = glob($f . '/*');
                                    foreach ($innerFolderfiles as $innerf) {
                                        if (is_file($innerf))
                                            unlink($innerf);
                                        if (is_dir($innerf)) {
                                            rmdir($innerf);
                                        }
                                    }
                                }
                                rmdir($f);
                            }
                        }
                        rmdir($file);
                    } // delete file
                }else{
                     if (is_file($file))
                        unlink($file);
                }
            }
            
             $files = glob(public_path('/drivefiles/*')); // get all file names			
            foreach ($files as $file) {// iterate files
                if (is_dir($file)) {
                    if (count(glob($file . "/*")) === 0) {
                        rmdir($file);
                    } else {
                        //echo $file;

                        $Folderfiles = glob($file . '/*');
                        foreach ($Folderfiles as $f) {
                            if (is_file($f))
                                unlink($f);
                            if (is_dir($f)) {
                                if (count(glob($f . "/*")) === 0) {
                                    rmdir($f);
                                } else {
                                    $innerFolderfiles = glob($f . '/*');
                                    foreach ($innerFolderfiles as $innerf) {
                                        if (is_file($innerf))
                                            unlink($innerf);
                                        if (is_dir($innerf)) {
                                            rmdir($innerf);
                                        }
                                    }
                                }
                                rmdir($f);
                            }
                        }
                        rmdir($file);
                    }                     // delete file
                }else if (is_file($file))
                        unlink($file);
            }    
            
            $email=\App\Models\Backend\EmailContent::where("status","2");
            if($email->count() <= 6){
                $dfiles = glob(storageEfs('/Worksheet/*')); // get all file names			
            foreach ($dfiles as $file) {// iterate files
                if (is_dir($file)) {
                    if (count(glob($file . "/*")) === 0) {
                        rmdir($file);
                    } else {
                        //echo $file;

                        $Folderfiles = glob($file . '/*');
                        foreach ($Folderfiles as $f) {
                            if (is_file($f))
                                unlink($f);
                            if (is_dir($f)) {
                                if (count(glob($f . "/*")) === 0) {
                                    rmdir($f);
                                } else {
                                    $innerFolderfiles = glob($f . '/*');
                                    foreach ($innerFolderfiles as $innerf) {
                                        if (is_file($innerf))
                                            unlink($innerf);
                                        if (is_dir($innerf)) {
                                            rmdir($innerf);
                                        }
                                    }
                                }
                                rmdir($f);
                            }
                        }
                        rmdir($file);
                    } // delete file
                }else{
                     if (is_file($file))
                        unlink($file);
                }
            }    
            }
      /*  } catch (Exception $e) {
            $cronName = "Remove File";
            $message = $e->getMessage();
            cronNotWorking($cronName,$message);
        }*/
    }

}

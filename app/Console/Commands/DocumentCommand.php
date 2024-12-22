<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Exception;
//use Illuminate\Support\Facades\Storage;

class DocumentCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'document:copy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'User Right';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $entityDocument = \App\Models\Backend\Entity::select("entity.code","d.*")->leftjoin("documents as d","d.entity_id","entity.id")->get();
            foreach($entityDocument as $entity){
            $data['entity_id'] = $entity->entity_id;
            $data['entity_code'] = $entity->code;
            $data['update_on'] = $entity->uploaded_on;
            $data['inputname'] = $entity->document;
            $data['location'] = "Entity";
            $data['module_id'] = "1";
            $data['type'] = $entity->document_type ==6 ? 1:2;
            $data['notes'] = $entity->document_desc;
            $data['uploaded_on'] = $entity->uploaded_on;
            $data['uploaded_by'] = $entity->uploaded_by;
            $documentUploaded = self::uploadEntityDocument($data);
            }     
        } catch (Exception $e) {
            app('log')->channel('resetsoftware')->error("Reset software failed : " . $e->getMessage());
        }
    }
    
    public static function uploadEntityDocument($data) {
    $commanFolder = '/uploads/documents/';
    $uploadPath = storage_path() . $commanFolder;
   
    //Check client code value
    if (isset($data['entity_code']) && $data['entity_code'] != '') {
        $mainFolder = $data['entity_code'];
    } else {// if client code not there that time document store in general 
        //$mainFolder = 'general';
        return 'Entity code missing';
    }
    $file = $data['inputname'];
    $sourcePath = storage_path() .'/uploads/uploads/'.$data['inputname'];
    if(File::exists(storage_path('/uploads/uploads/'.$data['inputname']))){
    $fileext = explode(".",$data['inputname']);
    //File Path
    $date = $data['uploaded_on'];
   // $fileName = rand(1, 2000000) . strtotime(strtotime($date)) . '.' . $fileext[1];
    //Create and check year directory 
    $month = date("m",strtotime($date));
    $year = date("Y",strtotime($date));
    $year1 = date('Y', strtotime('+1 years',strtotime($date)));
    $year2 = date('Y', strtotime('-1 years',strtotime($date)));
    if ($month >= 7) {
        $dir = $year . "-" . $year1;
        if (!is_dir($uploadPath . $dir)) {
            mkdir($uploadPath . $dir, 0777, true);
        }
    } else if ($month <= 6) {
        $dir = $year2 . "-" . $year;
        if (!is_dir($uploadPath . $dir)) {
            mkdir($uploadPath . $dir, 0777, true);
        }
    }

    $location = '';
    if (isset($data['location']) && $data['location'] != '')
        $location = $data['location'];
    else
        return 'Location not define';

    $uploadPath = $uploadPath . $dir . '/' . $mainFolder . '/' . $location . '/';
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0777, true);
    }

    // Document path
    $document_path = $commanFolder . $dir . '/' . $mainFolder . '/' . $location . '/';
    if (File::copy($sourcePath,$uploadPath.$file)) {
        $document['entity_id'] = $data['entity_id'];
        $document['module_id'] = $data['module_id'];
        $document['original_name'] = $file;
        $document['filename'] = $file;
        $document['type'] = $data['type'];
        $document['notes'] = $data['notes'];
        $document['documentpath'] = $document_path;
        $document['created_by'] = $data['uploaded_by'];
        $document['created_on'] = $data['uploaded_on'];
        $id = \App\Models\Backend\Document::insertGetId($document);
        return true;
    } else {
        return false;
    }
    }
}

}

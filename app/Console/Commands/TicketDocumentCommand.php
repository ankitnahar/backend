<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;
use Illuminate\Support\Facades\File;
//use Illuminate\Support\Facades\Storage;

class TicketDocumentCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ticketdocument:copy';

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
            $entityDocument = \App\Models\Backend\TicketDocument::select("e.code", "ticket_document.*")
                            ->leftjoin("ticket as t", "t.id", "ticket_document.ticket_id")
                            ->leftjoin("entity as e", "e.id", "t.entity_id")->get();
            foreach ($entityDocument as $entity) {
                $data['entity_id'] = $entity->entity_id;
                $data['entity_code'] = ($entity->code != '') ? $entity->code : '01BEFREE';
                $data['inputname'] = $entity->document_title;
                $data['location'] = "Ticket";
                $data['uploaded_on'] = $entity->created_on;
                $data['id'] = $entity->id;
                $documentUploaded = self::uploadEntityDocument($data);
            }
        } catch (Exception $e) {
            $cronName = "Ticket Doument";
            $message = $e->getMessage();
            cronNotWorking($cronName, $message);
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

        $fileext = explode(".", $data['inputname']);
        //File Path
        $date = $data['uploaded_on'];
        //$fileName = rand(1, 2000000) . strtotime(strtotime($date)) . '.' . $fileext[1];
        //Create and check year directory 
        $month = date("m", strtotime($date));
        $year = date("Y", strtotime($date));
        $year1 = date('Y', strtotime('+1 years', strtotime($date)));
        $year2 = date('Y', strtotime('-1 years', strtotime($date)));
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
        $sourcePath = storage_path() . '/uploads/uploads/' . $dir . '/' . $data['inputname'];
        if (File::exists(storage_path('/uploads/uploads/' . $dir . '/' . $data['inputname']))) {
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
            if (File::copy($sourcePath, $uploadPath . $file)) {
                $id = \App\Models\Backend\TicketDocument::where("id", $data['id'])
                        ->update(['document_path' => $document_path]);
                return true;
            } else {
                return false;
            }
        }
    }

}

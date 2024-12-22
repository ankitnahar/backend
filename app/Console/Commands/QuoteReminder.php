<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class QuoteReminder extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Quote:Reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reminder for quote';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $today = strtotime(date('Y-m-d'));
            $quoteMaster = \App\Models\Backend\QuoteMaster::where('stage_id', 5)->where('reminder_date', $today);
            $todayDate = date("Y-m-d");
            $reminderDate = date("Y-m-d", strtotime("+7 days", strtotime($todayDate)));
            if ($quoteMaster->count() > 0) {
                foreach ($quoteMaster->get() as $q) {
                    $quoteTemplate = \App\Models\Backend\QuoteEmailContent::where("quote_master_id", $q->id)->first();
                    $data['from'] = $quoteTemplate->from;
                    $data['to'] = $quoteTemplate->to;
                    $data['cc'] = $quoteTemplate->cc;
                    $data['bcc'] = $quoteTemplate->bcc;
                    $data['subject'] = $quoteTemplate->subject;
                    $data['content'] = $quoteTemplate->content;
                    $data['attachment'] = array($quoteTemplate->file_path);
                    storeMail('', $data);
                    $q->update(["reminder_date" => $reminderDate]);
                }
            }
        } catch (Exception $ex) {
            $cronName = "Quote Reminder";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}

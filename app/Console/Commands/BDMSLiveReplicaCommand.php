<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;
use DB;

class BDMSLiveReplicaCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'live:replica';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Live Replica';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            error_reporting(E_ALL);
            ini_set('upload_max_filesize', '-1');
            ini_set("memory_limit", "-1");
            ini_set('post_max_size', '-1');
            set_time_limit(0);
            $datestamp = date("Y-m-d-H-i-s");
            $fileN = "bdmsdb/bdms-2019-11-23-23-00-01.sql.gz";
            $NewfileName = "var/www/html/replica/dbbackup/bdms-" . $fileN . ".sql.gz";
            //$NewFile = 
            $connection = \Illuminate\Support\Facades\Storage::disk('ftp');
            if ($connection->exists($fileN)) {
            
            //if ($copy) {
                 $fileName = $connection->get($fileN[0]);
                 //\Illuminate\Support\Facades\Storage::disk('ftp')->put($fileName,$NewfileName);

                 $copy = $connection->put($NewfileName, $fileName);
                 //$copy = $connection->writeStream($NewfileName, $connection->readStream($fileName));
				 chmod($NewfileName, 0777);
                echo $copy;exit;
                define('HOSTNAME', '192.168.3.39');
                define('DBUSER', 'admin');
                define('DBPASS', 'Befree@stg');
                define('DBDATABASE', 'bdms');
                define('DESTINATIONFILE', '');
                $command = "zcat " . $NewfileName . " | sed -e 's/DEFINER[ ]*=[ ]*[^*]*\*/\*/' | sed -e 's/DEFINER[ ]*=[ ]*[^*]*TRIGGER/TRIGGER/' | sed -e 's/DEFINER[ ]*=[ ]*[^*]*PROCEDURE/PROCEDURE/' | sed -e 's/DEFINER[ ]*=[ ]*[^*]*FUNCTION/FUNCTION/' | mysql -h " . HOSTNAME . " -u " . DBUSER . " -p" . DBPASS . " " . DBDATABASE;
                exec($command, $output);

                // After DB Dump done update dummy database from file update-dump.text
                $file = fopen("/var/www/html/replica/dbbackup/update-dump.txt", "r");
                
                $connectionDb = mysqli_connect(HOSTNAME, DBUSER, DBPASS, DBDATABASE);
                if ($connectiondb) {
                    echo "<p>************************<br>";
                    echo "Table Update Initiated<br>";
                    while (!feof($file)) {
                        echo $line = fgets($file);
                        echo "<br>";
                        DB::select($line);
                    }
                    fclose($file);
                    // Unlink or Delete Files which is temporary file downloaded
                   // unlink($destination . $NewfileName);
                }
            }
        } catch (Exception $ex) {
            $cronName = "BDMS live Replica";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}

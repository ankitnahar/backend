<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class ShareFileClass extends Model {

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function authShareFile() {

        $hostname = "befree.sharefile.com";
        $username = "clients@befree.com.au";
        $password = "kj4e 2nco ifju w6le";
        $client_id = "29iQkXAGi9NFx7EJRSE0MMM5SUbE97ua";
        $client_secret = "emHCz1jdqH4cEmcrZcB8xp6S5egcOVJgROH6f50xnOQB0HTq";

        $token = self::authenticate($hostname, $client_id, $client_secret, $username, $password);
        return $token;
        if ($token) {
            return $this->get_root($token, TRUE);
        }
    }

    public function authenticate($hostname, $client_id, $client_secret, $username, $password) {
        $uri = "https://" . $hostname . "/oauth/token";
        //echo "POST " . $uri . "\n";

        $body_data = array("grant_type" => "password", "client_id" => $client_id, "client_secret" => $client_secret,
            "username" => $username, "password" => $password);
        $data = http_build_query($body_data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/x-www-form-urlencoded'));

        $curl_response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error_number = curl_errno($ch);
        $curl_error = curl_error($ch);

        // echo $curl_response . "\n"; // output entire response
        //echo $http_code . "\n"; // output http status code

        curl_close($ch);
        $token = NULL;
        if ($http_code == 200) {
            $token = json_decode($curl_response);
            //print_r($token); // print entire token object
        }
        return $token;
    }

    public function get_authorization_header($token) {
        return array("Authorization: Bearer " . $token->access_token);
    }

    public function get_hostname($token) {
        return $token->subdomain . ".sf-api.com";
    }

    /**
     * Get the root level Item for the provided user. To retrieve Children the $expand=Children
     * parameter can be added.
     *
     * @param string $token - json token acquired from authenticate function
     * @param boolean $get_children - retrieve Children Items if True, default is FALSE
     */
    public function get_root($token, $get_children = FALSE) {
        $uri = "https://" . $this->get_hostname($token) . "/sf/v3/Items";
        if ($get_children == TRUE) {
            $uri .= "?\$expand=Children";
        }
        //echo "GET " . $uri . "\n";

        $headers = $this->get_authorization_header($token);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $curl_response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error_number = curl_errno($ch);
        $curl_error = curl_error($ch);

        //echo $curl_response."\n"; // output entire response
        // echo $http_code . "\n"; // output http status code

        curl_close($ch);

        $root = json_decode($curl_response);
        //print_r($root); // print entire json response
        // echo $root->Id . " " . $root->CreationDate . " " . $root->Name . "\n";
        if (property_exists($root, "Children")) {
            foreach ($root->Children as $child) {
                echo $child->Id . " " . $child->CreationDate . " " . $child->Name . "\n";
            }
        }
    }

    /**
     * Gets a single Item by Id.
     *
     * @param string $token - json token acquired from authenticate function
     * @param unknown $item_id - an item id
     */
    function get_item_by_id($token, $item_id) {
        $uri = "https://" . $this->get_hostname($token) . "/sf/v3/Items(" . $item_id . ")";
        //echo "GET " . $uri . "\n";

        $headers = $this->get_authorization_header($token);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $curl_response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error_number = curl_errno($ch);
        $curl_error = curl_error($ch);

        //echo $curl_response."\n"; // output entire response
        echo $http_code . "\n"; // output http status code

        curl_close($ch);

        $root = json_decode($curl_response);
        //print_r($root); // print entire json response
        echo $root->Id . " " . $root->CreationDate . " " . $root->Name . "\n";
    }

    /**
     * Get a folder using some of the common query parameters that are available. This will
     * add the expand, select parameters. The following are used:
     *
     * expand=Children to get any Children of the folder
     * select=Id,Name,Children/Id,Children/Name,Children/CreationDate to get the Id, Name of the folder
     * and the Id, Name, CreationDate of any Children
     *
     * @param string $token - json token acquired from authenticate function
     * @param string $item_id - a folder id
     */
    function get_folder_with_query_parameters($token, $item_id, $folderType = Null) {
        $uri = "https://" . $this->get_hostname($token) . "/sf/v3/Items(" . $item_id . ")?\$expand=Children&\$select=Id,Name,Children/Id,Children/Name,Children/CreationDate";
        //echo "GET " . $uri . "\n";

        $headers = $this->get_authorization_header($token);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $curl_response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error_number = curl_errno($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);
        if ($folderType == Null) {
           return  $root = json_decode($curl_response);
            if (property_exists($root, "Children")) {
                foreach ($root->Children as $child) {
                    $childernData[] = $child;
                }
                return $childernData;
            }
        } else {
            if ($folderType == 'month') {
                if (date("m") >= 7) {
                    $dir = date("Y") . "-" . date('Y', strtotime('+1 years'));
                } else if (date("m") <= 6) {
                    $dir = date('Y', strtotime('-1 years')) . "-" . date("Y");
                }
                $childernData = array();
                $root = json_decode($curl_response);
                if (property_exists($root, "Children")) {
                    foreach ($root->Children as $child) {
                        if ($child->Name == $dir) {
                            $childernData = $child;
                        }
                    }
                    return $childernData;
                }
            } else {
                $root = json_decode($curl_response);
                if (property_exists($root, "Children")) {
                    foreach ($root->Children as $child) {
                        if ($child->Name == $folderType) {
                            $childernData = $child;
                        }
                    }
                    return $childernData;
                }
            }
        }
    }

    /**
     * Create a new folder in the given parent folder.
     *
     * @param string $token - json token acquired from authenticate function
     * @param string $parent_id - the parent folder in which to create the new folder
     * @param string $name - the folder name
     * @param string $description - the folder description
     */
    public function create_folder($token, $parent_id, $name, $description) {
        $uri = "https://" . $this->get_hostname($token) . "/sf/v3/Items(" . $parent_id . ")/Folder";
        //echo "POST " . $uri . "\n";

        $folder = array("Name" => $name, "Description" => $description);
        $data = json_encode($folder);

        $headers = $this->get_authorization_header($token);
        $headers[] = "Content-Type: application/json";
        //print_r($headers);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $curl_response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error_number = curl_errno($ch);
        $curl_error = curl_error($ch);

        echo $curl_response . "\n"; // output entire response
        //echo $http_code . "\n"; // output http status code

        curl_close($ch);

        if ($http_code == 200) {
            $item = json_decode($curl_response);
            return $item->Id;
        }
    }

    /**
     * Update the name and description of an Item.
     *
     * @param string $token - json token acquired from authenticate function
     * @param string $item_id - the id of the item to update
     * @param string $name - the item name
     * @param string $description - the item description
     */
    function update_item($token, $item_id, $name, $description) {
        $uri = "https://" . get_hostname($token) . "/sf/v3/Items(" . $item_id . ")";
        echo "PATCH " . $uri . "\n";

        $item = array("Name" => $name, "Description" => $description);
        $data = json_encode($item);

        $headers = get_authorization_header($token);
        $headers[] = "Content-Type: application/json";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $curl_response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error_number = curl_errno($ch);
        $curl_error = curl_error($ch);

        //echo $curl_response."\n"; // output entire response
        echo $http_code . "\n"; // output http status code

        curl_close($ch);

        if ($http_code == 200) {
            $updated_item = json_decode($curl_response);
            print_r($updated_item); // print entire new item object
            echo "Updated Folder: " . $updated_item->Id . "\n";
        }
    }

    /**
     * Delete an Item by Id.
     *
     * @param string $token - json token acquired from authenticate function
     * @param string $item_id - the id of the item to delete
     */
    function delete_item($token, $item_id) {
        $uri = "https://" . $this->get_hostname($token) . "/sf/v3/Items(" . $item_id . ")";
        //echo "DELETE " . $uri . "\n";

        $headers = $this->get_authorization_header($token);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $curl_response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error_number = curl_errno($ch);
        $curl_error = curl_error($ch);
        echo $curl_response;
        //echo $http_code . "\n";

        curl_close($ch);

        if ($http_code == 204) {
            echo "Deleted Item\n";
        }
    }

    /**
     * Downloads a single Item. If downloading a folder the local_path name should end in .zip.
     *
     * @param string $token - json token acquired from authenticate function
     * @param string $item_id - the id of the item to download
     * @param string $local_path - where to download the item to, like "c:\\path\\to\\the.file"
     */
    function download_item($token, $item_id, $local_path) {
        $uri = "https://" . $this->get_hostname($token) . "/sf/v3/Items(" . $item_id . ")/Download";
        echo "GET " . $uri . "\n";

        $fp = fopen($local_path, 'w');

        $headers = $this->get_authorization_header($token);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $curl_response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error_number = curl_errno($ch);
        $curl_error = curl_error($ch);

        echo $http_code . "\n";

        curl_close($ch);
        fclose($fp);
    }

    /**
     * Uploads a File using the Standard upload method with a multipart/form mime encoded POST.
     * 
     * @param string $token - json token acquired from authenticate function
     * @param string $folder_id - where to upload the file
     * @param string $local_path - the full path of the file to upload, like "c:\\path\\to\\file.name"
     */
    function upload_file($token, $folder_id, $local_path) {
        // echo $local_path;exit;
        $uri = "https://" . $this->get_hostname($token) . "/sf/v3/Items(" . $folder_id . ")/Upload";
        //echo "GET " . $uri . "\n";

        $headers = $this->get_authorization_header($token);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);


        $curl_response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error_number = curl_errno($ch);
        $curl_error = curl_error($ch);
        //echo $curl_response."\n"; // output entire response
        $upload_config = json_decode($curl_response);
//echo $http_code;
        if ($http_code == 200) {
            $post["File1"] = new \CurlFile($local_path);
            curl_setopt($ch, CURLOPT_URL, $upload_config->ChunkUri);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            //showArray($post["File1"]);
            $curl_response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error_number = curl_errno($ch);
            $curl_error = curl_error($ch);
            echo $curl_response . "\n";

            if ($http_code == 200) {
                $item = json_decode($curl_response);
                print_r($item); // print entire new item object           
            }
        }
        curl_close($ch);
    }

    /**
     * Get the Client users in the Account.
     * 
     * @param string $token - json token acquired from authenticate function
     */
    function get_clients($token) {
        $uri = "https://" . get_hostname($token) . "/sf/v3/Accounts/GetClients";
        echo "GET " . $uri . "\n";

        $headers = get_authorization_header($token);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $curl_response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error_number = curl_errno($ch);
        $curl_error = curl_error($ch);

        //echo $curl_response."\n"; // output entire response
        echo $http_code . "\n"; // output http status code

        curl_close($ch);

        $clients = json_decode($curl_response);
        //print_r($response); // print entire json response
        if ($clients->value != NULL) {
            foreach ($clients->value as $client) {
                echo $client->Id . " " . $client->Email . "\n";
            }
        }
    }

    /**
     * Create a Client user in the Account.
     *
     * @param string $token - json token acquired from authenticate function
     * @param string $email - email address of the new user
     * @param string $firstname - firsty name of the new user
     * @param string $lastname - last name of the new user
     * @param string $company - company of the new user
     * @param string $clientpassword - password of the new user
     * @param boolean $canresetpassword - user preference to allow user to reset password
     * @param boolean $canviewmysettings - user preference to all user to view 'My Settings'
     */
    function create_client($token, $email, $firstname, $lastname, $company, $clientpassword, $canresetpassword, $canviewmysettings) {

        $uri = "https://" . get_hostname($token) . "/sf/v3/Users";
        echo "POST " . $uri . "\n";

        $client = array("Email" => $email, "FirstName" => $firstname, "LastName" => $lastname, "Company" => $company,
            "Password" => $clientpassword,
            "Preferences" => array("CanResetPassword" => $canresetpassword, "CanViewMySettings" => $canviewmysettings));
        $data = json_encode($client);

        $headers = get_authorization_header($token);
        $headers[] = "Content-Type: application/json";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $curl_response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error_number = curl_errno($ch);
        $curl_error = curl_error($ch);

        //echo $curl_response."\n"; // output entire response
        // echo "http_code = " . $http_code . "\n"; // output http status code

        curl_close($ch);

        if ($http_code == 200) {
            $client = json_decode($curl_response);
            //print_r($client); // print entire new item object
            echo "Created Client: " . $client->Id . "\n";
        }
    }

}

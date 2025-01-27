<?php

/**
 * Get the configuration path.
 *
 * @param  string $path
 * @return string
 */
//function config_path($path = '') {
//    return app()->basePath() . '/config' . ($path ? '/' . $path : $path);
//}

/**
 * Require multiple files at once in routing
 *
 * This is a general function to require multiple files
 *
 * @param  arrray  $files
 * @return void
 */
function requireMultiRoutes($files, $router) {
    foreach ($files as $file)
        require $file;
}

function storageEfs($path = '') {
    return rtrim(app()->basePath('efsstorage' . $path), '/');
}

function differenceTwodate($date1, $date2) {
    $date1 = new DateTime($date1);
    $date2 = new DateTime($date2);
    $interval = $date1->diff($date2);
    return $interval->y . " years, " . $interval->m . " months, " . $interval->d . " days ";
}

/**
 * Create a json response
 *
 *
 * @param  int       $status     Http response code
 * @param  string    $message    The message need to be sent
 * @param  array     $payload    The data being sent in response
 * @param  array     $pager      This is optional
 * @return void
 */
function createResponse($status, $message, $payload, $pager = "") {
    return response()->json(['status' => $status,
                'message' => $message,
                'payload' => $payload,
                'pager' => $pager
                    ], $status);
}

/**
 * Filter requested fields from the array of fields
 *
 *
 * @param  array                     $fields
 * @param  Illuminate\Http\Request   $request
 * @return array
 */
function filterFields($fields, $request) {
    $data = [];
    if (!is_array($request)) {
        foreach ($fields as $field)
            if ($request->has($field) || $request->get($field) === "") {
                if ($field == 'password') {
                    $data[$field] = $password = Illuminate\Support\Facades\Crypt::encrypt($request->get('password'));
                } else {
                    $data[$field] = $request->get($field);
                }
            }
    } else {
        foreach ($fields as $field)
            if (isset($request[$field]))
                $data[$field] = $request[$field];
    }
    return $data;
}

/* Created by: Jayesh Shingrakhiya
 * Created on: April 24, 2018
 * Purpose: Sort function for print array in pre tag format
 */

function showArray($data) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}

/* Created by: Jayesh Shingrakhiya
 * Created on: April 25, 2018
 * Purpose: download excel file
 */

function exportExcelsheet($data, $filename, $type, $topcell = 'A1', $freeRow = null, $mergeCol = null) {
    return app('excel')->create($filename, function($excel) use ($data, $topcell) {
                $excel->sheet('Sheet1', function($sheet) use ($data, $topcell) {
                    $sheet->cell($topcell, function($cell) {
                        $cell->setFontColor('#ffffff');
                        $cell->setBackground('#0c436c');
                    });

                    $sheet->setFreeze();
                    $sheet->getAllowedStyles();
                    $sheet->fromArray($data, null, 'A1', false, false);
                });
            })->export($type, ['Access-Control-Allow-Origin' => '*']);
}

function saveExcelFile($data, $filename, $type, $topcell = 'A1', $freeRow = null, $mergeCol = null) {
    $path = storageEfs() . '/uploads/food/';
    app('excel')->create($filename, function($excel) use ($data, $topcell) {
        $excel->sheet('Sheet1', function($sheet) use ($data, $topcell) {
            $sheet->cell($topcell, function($cell) {
                $cell->setFontColor('#ffffff');
                $cell->setBackground('#0c436c');
            });

            $sheet->setFreeze();
            $sheet->getAllowedStyles();
            $sheet->fromArray($data, null, 'A1', false, false);
        });
    })->store($type, $path, ['Access-Control-Allow-Origin' => '*']);
}

/* Created by: Jayesh Shingrakhiya
 * Created on: April 25, 2018
 * Purpose: fomating date
 */

function dateFormat($date) {
    if ($date != '' && $date != '0000-00-00 00:00:00' && $date != '0000-00-00')
        return date('d-m-Y', strtotime($date));
    else {
        return '-';
    }
}

/*
 * Created by pankaj
 * Created on : 26-04-2018
 * login user
 */

function loginUser() {
    return $loginUser = app('auth')->guard()->id();
}

/*
 * Created by pankaj
 * login user
 */

function getLoggedinUser() {
    return $loginUser = app('auth')->guard();
}

/*
 * Created by pankaj
 * login user user hierarchy detail
 */

function getLoginUserHierarchy() {
    return App\Models\Backend\UserHierarchy::where("user_id", app('auth')->guard()->id())->first();
}

/**
 * Store Mail
 * Created by : Jayesh / Pankaj
 * @param  Illuminate\Http\Request  $request
 * @param  data                      array
 * @return mail insert or not (0,1) format
 */
function storeMail($request, $data) {
    //Check data does not blank
    if (is_array($data) && !empty($data)) {
        //check to does not blank
        if (isset($data['to']) && $data['to'] != '') {
            $to = str_replace(",,", ",", $data['to']);
            $to = rtrim($to, ',');
        } else {
            return 'To does not blank';
        }
        // check from does not blank 
        if (isset($data['from']) && $data['from'] != '') {
            $from = $data['from'];
        } else {
            $from = "noreply-bdms@befree.com.au";
        }
        // check content does not blank 
        if (isset($data['content']) && $data['content'] != '' && (!isset($data['withoutheaderfooter']))) {
            $header = config('mail.common.header');
            $footer = config('mail.common.footer');
            $content = $header . $data['content'] . $footer;
        } else if (isset($data['withoutheaderfooter']) && $data['withoutheaderfooter'] == 1) {
            $content = $data['content'];
        } else {
            return 'Content does not blank';
        }
        $cc = $bcc = '';
        if (isset($data['cc']) && $data['cc'] != '') {
            $cc = str_replace(",,", ",", $data['cc']);
            $cc = rtrim($cc, ',');
            $cc = str_replace(",clients2@befree.com.au", "", $cc);
            $cc = str_replace("clients2@befree.com.au,", "", $cc);
        }
        if (isset($data['bcc']) && $data['bcc'] != '') {
            $bcc = str_replace(",,", ",", $data['bcc']);
            $bcc = rtrim($bcc, ',');
            $bcc = str_replace(",clients2@befree.com.au", "", $bcc);
            $bcc = str_replace("clients2@befree.com.au,", "", $bcc);
        }
        $subject = (isset($data['subject']) && $data['subject'] != '') ? $data['subject'] : '';
        $fromName = (isset($data['from_name']) && $data['from_name'] != '') ? $data['from_name'] : 'Befree noreply';

        $uploadAttachment = array();
        $attachment = '';
        if (isset($data['attachment']) && is_array($data['attachment']) && !empty($data['attachment'])) {
            $attachment = json_encode($data['attachment']);
        }
        // Remove first & last comma with leading/trailng white spaces.
        $to = preg_replace('/^[,\s]+|[\s,]+$/', '', $to);
        $cc = preg_replace('/^[,\s]+|[\s,]+$/', '', $cc);
        $bcc = preg_replace('/^[,\s]+|[\s,]+$/', '', $bcc);

        // Remove white spaces between the two commas.
        $to = preg_replace('/\s+/', '', $to);
        $cc = preg_replace('/\s+/', '', $cc);
        $bcc = preg_replace('/\s+/', '', $bcc);

        //Store Mail
        if ($storeMail = \App\Models\Backend\EmailContent::create([
                    'to_email' => strtolower(str_replace(' ', '', $to)),
                    'from_email' => str_replace(' ', '', $from),
                    'from_name' => $fromName,
                    'cc_email' => strtolower(str_replace(' ', '', $cc)),
                    'bcc_email' => strtolower(str_replace(' ', '', $bcc)),
                    'subject' => $subject,
                    'content' => $content,
                    'attachment' => $attachment,
                    'created_on' => date('Y-m-d H:i:s'),
                    'created_by' => app('auth')->id()
                ])) {
            return true;
        }
        return false;
    }
}

/**
 * Store Mail
 * Created by : Pankaj
 * @param  Illuminate\Http\Request  $request
 * @param  data                      array
 * @return mail insert or not (0,1) format
 */
function cronStoreMail($data) {
    //Check data does not blank
    if (is_array($data) && !empty($data)) {
        //check to does not blank
        if (isset($data['to']) && $data['to'] != '') {
            $to = str_replace(",clients2@befree.com.au", "", $data['to']);
        } else {
            return 'To does not blank';
        }
        // check from does not blank 
        if (isset($data['from']) && $data['from'] != '') {
            $from = $data['from'];
        } else {
            $from = "noreply-bdms@befree.com.au";
        }
        if (isset($data['cc']) && $data['cc'] != '') {
            $cc = str_replace(",clients2@befree.com.au", "", $data['cc']);
        }
        if (isset($data['bcc']) && $data['bcc'] != '') {
            $bcc = str_replace(",clients2@befree.com.au", "", $data['bcc']);
        }
        // check content does not blank 
        if (isset($data['content']) && $data['content'] != '') {
            $header = config('mail.common.header');
            $footer = config('mail.common.footer');
            $content = $header . $data['content'] . $footer;
        } else {
            return 'Content does not blank';
        }

        $cc = (isset($data['cc']) && $data['cc'] != '') ? $data['cc'] : '';
        $bcc = (isset($data['bcc']) && $data['bcc'] != '') ? $data['bcc'] : '';
        $subject = (isset($data['subject']) && $data['subject'] != '') ? $data['subject'] : '';
        $fromName = (isset($data['from_name']) && $data['from_name'] != '') ? $data['from_name'] : 'Befree noreply';


        $attachment = '';
        if (isset($data['attachment']) && is_array($data['attachment']) && !empty($data['attachment'])) {
            $attachment = json_encode($data['attachment']);
        }
        //Store Mail
        if ($storeMail = \App\Models\Backend\EmailContent::create([
                    'to_email' => strtolower(trim($to)),
                    'from_email' => trim($from),
                    'from_name' => $fromName,
                    'cc_email' => strtolower(trim($cc)),
                    'bcc_email' => strtolower(trim($bcc)),
                    'subject' => $subject,
                    'content' => $content,
                    'attachment' => $attachment,
                    'created_on' => date('Y-m-d H:i:s'),
                    'created_by' => app('auth')->id()
                ])) {
            return true;
        }
        return false;
    }
}

/**
 * Upload attachment
 * Created by : Jayesh / Pankaj
 * @param  Illuminate\Http\Request  $request
 * @param  data                     array
 * @return array (file name,file path)
 * Last modified by Jayesh Shingrakhiya 
 */
function uploadDocument($request, $data) {
    $commanFolder = '/uploads/documents/';
    $uploadPath = storage_path() . $commanFolder;

    //Check client code value
    if (isset($data['entity_code']) && $data['entity_code'] != '') {
        $mainFolder = $data['entity_code'];
    } else {// if client code not there that time document store in general 
        //$mainFolder = 'general';
        return 'Entity code missing';
    }

    $file = $request->file($data['inputname']);
    //File Path
    $fileName = rand(1, 2000000) . strtotime(date('Y-m-d H:i:s')) . '.' . $file->getClientOriginalExtension();
    //Create and check year directory 
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
    if ($file->move($uploadPath, $fileName)) {
        $document['entity_id'] = $data['entity_id'];
        $document['module_id'] = $data['module_id'];
        $document['original_name'] = $file->getClientOriginalName();
        $document['filename'] = $fileName;
        $document['type'] = $request->get('type');
        $document['notes'] = $request->get('notes');
        $document['documentpath'] = $document_path;
        $document['created_by'] = app('auth')->guard()->id();
        $document['created_on'] = date('Y-m-d H:i:s');
        $id = \App\Models\Backend\Document::insertGetId($document);
        return true;
    } else {
        return false;
    }
}

function public_path($path = null) {
    return rtrim(app()->basePath('public/' . $path), '/');
}

/* Created by: Pankaj
 * Created on: Jun 28, 2018
 * Purpose: Globle upload image function for whole BDMS
 * $param: $request, image field name and user id
 */

function uploadUserImage($request, $imageName, $id) {
    $commanFolder = '/uploads/user/' . $id;
    $uploadPath = public_path() . $commanFolder;
    $file = $request->file($imageName);
    //File Path
    $fileName = rand(1, 2000000) . strtotime(date('Y-m-d H:i:s')) . '.' . $file->getClientOriginalExtension();

    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0777, true);
    }
    if ($file->move($uploadPath, $fileName)) {
        $fileName150 = 'thumb150X150' . '.' . $file->getClientOriginalExtension();
        $img = Intervention\Image\Facades\Image::make($uploadPath . '/' . $fileName);
        $img->resize(150, 150, function ($constraint) {
            $constraint->aspectRatio();
        })->save($uploadPath . '/' . $fileName150);

        //user thumb 32
        $fileName32 = 'thumb32X32' . '.' . $file->getClientOriginalExtension();
        $img = Intervention\Image\Facades\Image::make($uploadPath . '/' . $fileName);
        $img->resize(32, 32, function ($constraint) {
            $constraint->aspectRatio();
        })->save($uploadPath . '/' . $fileName32);

        return $commanFolder . '/' . $fileName;
    } else {
        return false;
    }
}

/* Created by: Jayesh Shingrakhiya
 * Created on: May 15, 2018
 * Purpose: Globle function for whole BDMS
 * $param: $data is array of all search fields, $dbObject is array of DB obj
 */

function search($dbObject, $data, $alias = NULL) {
    $search = json_decode($data, true);
    if (isset($search['compare']) && !empty($search['compare'])) {
        foreach ($search['compare'] as $keyCom => $valueCom) {
            if ($keyCom == 'equal') {
                foreach ($valueCom as $keyEqual => $valueEqual) {
                    if (isset($alias[$keyEqual]))
                        $keyEqual = $alias[$keyEqual] . '.' . $keyEqual;

                    $dbObject = $dbObject->where($keyEqual, $valueEqual);
                }
            }
            if ($keyCom == 'equaldate') {
                foreach ($valueCom as $keyEqualdate => $valueEqualdate) {
                    if (isset($alias[$keyEqualdate]))
                        $keyEqualdate = $alias[$keyEqualdate] . '.' . $keyEqualdate;

                    $dbObject = $dbObject->whereRaw("DATE_FORMAT(" . $keyEqualdate . ", '%Y-%m-%d') ='" . $valueEqualdate . "'");
                }
            }
            if ($keyCom == 'notequal') {
                foreach ($valueCom as $keyNotequal => $valueNotequal) {


                    if (isset($alias[$keyNotequal]))
                        $keyNotequal = $alias[$keyNotequal] . '.' . $keyNotequal;

                    $dbObject = $dbObject->where($keyNotequal, '!=', $valueNotequal);
                }
            }
            if ($keyCom == 'lessthan') {
                foreach ($valueCom as $keyLessthan => $valueLessthan) {

                    if (isset($alias[$keyLessthan]))
                        $keyLessthan = $alias[$keyLessthan] . '.' . $keyLessthan;

                    $dbObject = $dbObject->whereRaw("DATE_FORMAT(" . $keyLessthan . ", '%Y-%m-%d') <'" . $valueLessthan . "'");
                }
            }
            if ($keyCom == 'lessthanval') {
                foreach ($valueCom as $keyLessthanVal => $valueLessthanVal) {

                    if (isset($alias[$keyLessthan]))
                        $keyLessthanVal = $alias[$keyLessthanVal] . '.' . $keyLessthanVal;

                    $dbObject = $dbObject->whereRaw($keyLessthanVal . " < " . $valueLessthanVal);
                }
            }
            if ($keyCom == 'greaterthan') {
                foreach ($valueCom as $keyGreatethan => $valueGreatethan) {

                    if (isset($alias[$keyGreatethan]))
                        $keyGreatethan = $alias[$keyGreatethan] . '.' . $keyGreatethan;

                    $dbObject = $dbObject->whereRaw("DATE_FORMAT(" . $keyGreatethan . ", '%Y-%m-%d') >'" . $valueGreatethan . "'");
                }
            }
            if ($keyCom == 'greaterthanval') {
                foreach ($valueCom as $keyGreatethanVal => $valueGreatethanVal) {

                    if (isset($alias[$keyGreatethanVal]))
                        $keyGreatethanVal = $alias[$keyGreatethanVal] . '.' . $keyGreatethanVal;

                    $dbObject = $dbObject->whereRaw($keyGreatethanVal . " > " . $valueGreatethanVal);
                }
            }
            if ($keyCom == 'lessthanequal') {
                foreach ($valueCom as $keyLessthanequal => $valueLessthaneqaul) {

                    if (isset($alias[$keyLessthanequal]))
                        $keyLessthanequal = $alias[$keyLessthanequal] . '.' . $keyLessthanequal;

                    $dbObject = $dbObject->whereRaw("DATE_FORMAT(" . $keyLessthanequal . ", '%Y-%m-%d') <= '" . $valueLessthaneqaul . "'");
                }
            }
            if ($keyCom == 'lessthanequalval') {
                foreach ($valueCom as $keyLessthanequalval => $valueLessthaneqaulval) {

                    if (isset($alias[$keyLessthanequalval]))
                        $keyLessthanequalval = $alias[$keyLessthanequalval] . '.' . $keyLessthanequalval;

                    $dbObject = $dbObject->whereRaw($keyLessthanequalval . " <= " . $valueLessthaneqaulval);
                }
            }
            if ($keyCom == 'greaterthanequal') {
                foreach ($valueCom as $keyGreatethanequal => $valueGreatethanequal) {

                    if (isset($alias[$keyGreatethanequal]))
                        $keyGreatethanequal = $alias[$keyGreatethanequal] . '.' . $keyGreatethanequal;

                    $dbObject = $dbObject->whereRaw("DATE_FORMAT(" . $keyGreatethanequal . ", '%Y-%m-%d') >= '" . $valueGreatethanequal . "'");
                }
            }
            if ($keyCom == 'greaterthanequalval') {
                foreach ($valueCom as $keyGreatethanequalval => $valueGreatethanequalval) {

                    if (isset($alias[$keyGreatethanequalval]))
                        $keyGreatethanequalval = $alias[$keyGreatethanequalval] . '.' . $keyGreatethanequalval;

                    $dbObject = $dbObject->whereRaw($keyGreatethanequalval . " >= " . $valueGreatethanequalval);
                }
            }
            if ($keyCom == 'like') {
                foreach ($valueCom as $keyLike => $valueLike) {

                    if (isset($alias[$keyLike]))
                        $keyLike = $alias[$keyLike] . '.' . $keyLike;

                    $dbObject = $dbObject->where($keyLike, 'like', "%$valueLike%");
                }
            }
            if ($keyCom == 'startwith') {
                foreach ($valueCom as $keyStartWith => $valueStartWith) {

                    if (isset($alias[$keyStartWith]))
                        $keyStartWith = $alias[$keyStartWith] . '.' . $keyStartWith;

                    $dbObject = $dbObject->where($keyStartWith, 'like', "$valueStartWith%");
                }
            }
            if ($keyCom == 'notlike') {
                foreach ($valueCom as $keyNotlike => $valueNotlike) {

                    if (isset($alias[$keyNotlike]))
                        $keyNotlike = $alias[$keyNotlike] . '.' . $keyNotlike;

                    $dbObject = $dbObject->where($keyNotlike, 'not like', "%$valueNotlike%");
                }
            }
        }
    }

    if (isset($search['or']) && !empty($search['or'])) {
        foreach ($search['or'] as $keyOr => $valueOr) {
            $orCondition = '';
            if ($keyOr == 'equal') {
                foreach ($valueOr as $keysOrEqual => $valuesOrEqual) {
                    foreach ($valuesOrEqual as $keySingleequal => $valueSingleequal) {
                        if (isset($alias[$keySingleequal]))
                            $keySingleequal = $alias[$keySingleequal] . '.' . $keySingleequal;

//                        if ($orCondition == '')
//                            $orCondition .= ' (' . $keySingleequal . '=' . $valueSingleequal . '"';
//                        else
//                            $orCondition .= ' OR ' . $keySingleequal . '=' . $valueSingleequal . '")';

                        if ($orCondition == '')
                            $orCondition .= ' (' . $keySingleequal . '=' . $valueSingleequal;
                        else
                            $orCondition .= ' OR ' . $keySingleequal . '=' . $valueSingleequal . ')';
                    }
                    $dbObject = $dbObject->whereRaw($orCondition);
                }
            }

            if ($keyOr == 'notequal') {
                foreach ($valueOr as $keyOrNotequal => $valueOrNotequal) {
                    foreach ($valueOrNotequal as $keySinglenotequal => $valueSinglenotequal) {
                        if (isset($alias[$keySinglenotequal]))
                            $keySinglenotequal = $alias[$keySinglenotequal] . '.' . $keySinglenotequal;

                        if ($orCondition == '')
                            $orCondition .= ' (' . $keySinglenotequal . '!=' . $valueSinglenotequal . '"';
                        else
                            $orCondition .= ' OR ' . $keySinglenotequal . '!=' . $valueSinglenotequal . '")';
                    }
                    $dbObject = $dbObject->whereRaw($orCondition);
                }
            }

            if ($keyOr == 'like') {
                foreach ($valueOr as $keysLikeOr => $valuesLikeOr) {
                    foreach ($valuesLikeOr as $keySinglelike => $valueSinglelike) {
                        if (isset($alias[$keySinglelike]))
                            $keySinglelike = $alias[$keySinglelike] . '.' . $keySinglelike;

                        if ($orCondition == '')
                            $orCondition .= ' (`' . $keySinglelike . '` like "%' . $valueSinglelike . '%"';
                        else
                            $orCondition .= ' OR `' . $keySinglelike . '` like "%' . $valueSinglelike . '%"';
                    }
                    $orCondition .= ")";
                }
                $dbObject = $dbObject->whereRaw($orCondition);
            }
        }
    }

    if (isset($search['in']) && !empty($search['in'])) {
        foreach ($search['in'] as $keyIn => $valueIn) {

            $values = explode(',', $valueIn);
            if (isset($alias[$keyIn]))
                $keyIn = $alias[$keyIn] . '.' . $keyIn;

            $dbObject = $dbObject->whereIn($keyIn, $values);
        }
    }

    if (isset($search['notin']) && !empty($search['notin'])) {
        foreach ($search['notin'] as $keyNotin => $valueNotin) {

            $values = explode(',', $valueNotin);
            if (isset($alias[$keyNotin]))
                $keyIn = $alias[$keyNotin] . '.' . $keyNotin;

            $dbObject = $dbObject->whereNotIn($keyNotin, $values);
        }
    }

    if (isset($search['dateformat']) && !empty($search['dateformat'])) {
        foreach ($search['dateformat'] as $keyDateformat => $valueDateformat) {
            if ($keyDateformat == 'year')
                foreach ($valueDateformat as $keyYear => $valueYear) {

                    if (isset($alias[$keyYear]))
                        $keyYear = $alias[$keyYear] . '.' . $keyYear;

                    $dbObject = $dbObject->whereRaw("DAT.E_FORMAT(" . $keyYear . ", '%Y') = '" . $valueYear . "'");
                }

            if ($keyDateformat == 'month')
                foreach ($valueDateformat as $keyMonth => $valueMonth) {

                    if (isset($alias[$keyMonth]))
                        $keyMonth = $alias[$keyMonth] . '.' . $keyMonth;

                    $dbObject = $dbObject->whereRaw("DATE_FORMAT(" . $keyMonth . ", '%m') = '" . $valueMonth . "'");
                }

            if ($keyDateformat == 'yearmonth')
                foreach ($valueDateformat as $keyYearMonth => $valueYearMonth) {

                    if (isset($alias[$keyYearMonth]))
                        $keyYearMonth = $alias[$keyYearMonth] . '.' . $keyYearMonth;

                    $month = $valueYearMonth . '-25';
                    $startDate = date('Y-m-26', strtotime("-1 month", strtotime($month)));
                    $endDate = date('Y-m-25', strtotime($valueYearMonth));
                    $dbObject = $dbObject->whereRaw($keyYearMonth . '>= "' . $startDate . '"  and ' . $keyYearMonth . '<="' . $endDate . '"');
                }
        }
    }

    if (isset($search['findinset']) && !empty($search['findinset'])) {
        foreach ($search['findinset'] as $keyFindinset => $valueFindinset) {

            if (isset($alias[$keyFindinset]))
                $keyFindinset = $alias[$keyFindinset] . '.' . $keyFindinset;

            $queryFindinset = "(";
            foreach ($valueFindinset as $value)
                $queryFindinset .= 'FIND_IN_SET(' . $value . ',' . $keyFindinset . ') OR ';

            $queryFindinset = rtrim($queryFindinset, 'OR ');
            $queryFindinset .= ")";
            $dbObject = $dbObject->whereRaw($queryFindinset);
        }
    }
    return $dbObject;
}

/* Created by: Pankaj
 * Created on: 08-10-2018
 * Purpose: Globle function for whole BDMS Report
 * $param: $data is array of all search fields, $dbObject is array of DB obj
 */

function searchReport($dbObject, $data, $alias = NULL, $col = NULL, $jsonColName = NULL) {
    $searchList = json_decode($data, true);
    foreach ($searchList as $search) {
        $typeDate = 0;
        $key = $search['fieldname'];
        $value = $search['value'];
        $clientName = array("entity_name", "trading_name", "billing_name");
        if (in_array($key, $clientName)) {
            if (isset($alias[$key]))
                $key = $alias[$key] . '.' . "id";
        }
        if ($key == 'ticket_code') {
            $key = "code";
        }
        if ($key == 'created_on' || $key == 'send_date') {
            $typeDate = 1;
            if (isset($alias[$key]))
                $key = $alias[$key] . '.' . $key;
        }
        if ($search['condition'] == 'equal') {
            if (isset($col) && isset($col[$key])) {
                $key = "JSON_EXTRACT('entity.' .$jsonColName, '$." . $col[$key] . "') = '" . $value . "'";
                $dbObject = $dbObject->whereRaw($key);
            } else if ($typeDate == 0) {
                if (isset($alias[$key]))
                    $key = $alias[$key] . '.' . $key;
                $dbObject = $dbObject->where($key, $value);
            }else {
                $dbObject = $dbObject->whereDate($key, $value);
            }
        }
        if ($search['condition'] == 'notequal') {
            if (isset($col) && isset($col[$key])) {
                $key = "JSON_EXTRACT($jsonColName, '$." . $col[$key] . "') != '" . $value . "'";
                $dbObject = $dbObject->whereRaw($key);
            } else if ($typeDate == 0) {
                if (isset($alias[$key]))
                    $key = $alias[$key] . '.' . $key;
                $dbObject = $dbObject->where($key, "!=", $value);
            }else {
                $dbObject = $dbObject->whereDate($key, "!=", $value);
            }
        }
        if ($search['condition'] == 'lessthanval') {
            if (isset($col) && isset($col[$key])) {
                $key = "json_unquote(JSON_EXTRACT($jsonColName, '$." . $col[$key] . "')) < '" . $value . "'";
                $dbObject = $dbObject->whereRaw($key);
            } else {
                if (isset($alias[$key]))
                    $key = $alias[$key] . '.' . $key;
                $dbObject = $dbObject->where($key, "<", $value);
            }
        }
        if ($search['condition'] == 'greaterthanval') {
            if (isset($col) && isset($col[$key])) {
                $key = "json_unquote(JSON_EXTRACT($jsonColName, '$." . $col[$key] . "')) > '" . $value . "'";
                $dbObject = $dbObject->whereRaw($key);
            } else {
                if (isset($alias[$key]))
                    $key = $alias[$key] . '.' . $key;
                $dbObject = $dbObject->where($key, ">", $value);
            }
        }
        if ($search['condition'] == 'lessthanequalval') {
            if (isset($col) && isset($col[$key])) {
                $key = "json_unquote(JSON_EXTRACT($jsonColName, '$." . $col[$key] . "')) <= '" . $value . "'";
                $dbObject = $dbObject->whereRaw($key);
            } else {
                if (isset($alias[$key]))
                    $key = $alias[$key] . '.' . $key;
                $dbObject = $dbObject->where($key, "<=", $value);
            }
        }
        if ($search['condition'] == 'greaterthanequalval') {
            if (isset($col) && isset($col[$key])) {
                $key = "json_unquote(JSON_EXTRACT($jsonColName, '$." . $col[$key] . "')) >= '" . $value . "'";
                $dbObject = $dbObject->whereRaw($key);
            } else {
                if (isset($alias[$key]))
                    $key = $alias[$key] . '.' . $key;
                $dbObject = $dbObject->where($key, ">=", $value);
            }
        } if ($search['condition'] == 'lessthan') {
            if (isset($col) && isset($col[$key])) {
                $key = "JSON_EXTRACT($jsonColName, '$." . $col[$key] . "') < '" . $value . "'";
                $dbObject = $dbObject->whereRaw($key);
            } else {
                if (isset($alias[$key]))
                    $key = $alias[$key] . '.' . $key;
                $dbObject = $dbObject->whereRaw("DATE_FORMAT(" . $key . ", '%Y-%m-%d') < '" . $value . "'");
            }
        }
        if ($search['condition'] == 'greaterthan') {
            if (isset($col) && isset($col[$key])) {
                $key = "JSON_EXTRACT($jsonColName, '$." . $col[$key] . "') > '" . $value . "'";
                $dbObject = $dbObject->whereRaw($key);
            } else {
                if (isset($alias[$key]))
                    $key = $alias[$key] . '.' . $key;
                $dbObject = $dbObject->whereRaw("DATE_FORMAT(" . $key . ", '%Y-%m-%d') > '" . $value . "'");
            }
        }
        if ($search['condition'] == 'lessthanequal') {
            if (isset($col) && isset($col[$key])) {
                $key = "JSON_EXTRACT($jsonColName, '$." . $col[$key] . "') <= '" . $value . "'";
                $dbObject = $dbObject->whereRaw($key);
            } else {
                if (isset($alias[$key]))
                    $key = $alias[$key] . '.' . $key;
                $dbObject = $dbObject->whereRaw("DATE_FORMAT(" . $key . ", '%Y-%m-%d') <='" . $value . "'");
            }
        }
        if ($search['condition'] == 'greaterthanequal') {
            if (isset($col) && isset($col[$key])) {
                $key = "JSON_EXTRACT($jsonColName, '$." . $col[$key] . "') >= '" . $value . "'";
                $dbObject = $dbObject->whereRaw($key);
            } else {
                if (isset($alias[$key]))
                    $key = $alias[$key] . '.' . $key;
                $dbObject = $dbObject->whereRaw("DATE_FORMAT(" . $key . ", '%Y-%m-%d') >= '" . $value . "'");
            }
        }
        if ($search['condition'] == 'like') {
            if (isset($col) && isset($col[$key])) {
                $key = "JSON_EXTRACT($jsonColName, '$." . $col[$key] . "') like \"%" . $value . "%\"";
                $dbObject = $dbObject->whereRaw($key);
            } else {
                if (isset($alias[$key]))
                    $key = $alias[$key] . '.' . $key;
                $dbObject = $dbObject->where($key, 'like', "%$value%");
            }
        }
        if ($search['condition'] == 'startwith') {
            if (isset($col) && isset($col[$key])) {
                $key = "json_unquote(JSON_EXTRACT($jsonColName, '$." . $col[$key] . "')) like \"" . $value . "%\"";
                $dbObject = $dbObject->whereRaw($key);
            } else {
                if (isset($alias[$key]))
                    $key = $alias[$key] . '.' . $key;
                $dbObject = $dbObject->where($key, 'like', "$value%");
            }
        }
        if ($search['condition'] == 'notlike') {
            if (isset($col) && isset($col[$key])) {
                $key = "JSON_EXTRACT($jsonColName, '$." . $col[$key] . "') not like \"%" . $value . "%\"";
                $dbObject = $dbObject->whereRaw($key);
            } else {
                if (isset($alias[$key]))
                    $key = $alias[$key] . '.' . $key;
                $dbObject = $dbObject->where($key, 'not like', "%$value%");
            }
        }
        if ($search['condition'] == 'in') {
            $values = explode(',', $value);
            if (isset($col) && isset($col[$key])) {
                $inValues = "'" . implode("', '", $values) . "'";
                $key = "json_unquote(JSON_EXTRACT($jsonColName, '$." . $col[$key] . "')) IN ($inValues)";
                $dbObject = $dbObject->whereRaw($key);
            } else {
                if (isset($alias[$key]))
                    $key = $alias[$key] . '.' . $key;

                if (in_array($key, $clientName))
                    $key = 'e.id';

                $dbObject = $dbObject->whereIn($key, $values);
            }
        }
        if ($search['condition'] == 'notin') {
            $values = explode(',', $value);
            if (isset($col) && isset($col[$key])) {
                $key = "json_unquote(JSON_EXTRACT($jsonColName, '$." . $col[$key] . "')) Not IN ($inValues)";
                $dbObject = $dbObject->whereRaw($key);
            } else {
                if (isset($alias[$key]))
                    $key = $alias[$key] . '.' . $key;
                $dbObject = $dbObject->whereNotIn($key, $values);
            }
        }
    }
    return $dbObject;
}

/*
 * Created by - Pankaj,Jayesh
 * check client allocation user wise
 * 
 *  @param  int   $user_id  
 */

function checkUserClientAllocation($user_id) {
    //get user designation id
    $user = App\Models\Backend\UserHierarchy::where("user_id", $user_id)->first();
    if ($user->designation_id != config('constant.SUPERADMIN')) {
        // Get Allocation From Entity Allocation
        $entityAllocation = \App\Models\Backend\EntityAllocation::select("entity_id", "id")->whereRaw("JSON_SEARCH(allocation_json, 'all', '$user_id') IS NOT NULL")->groupBy("entity_id")->get()->pluck("entity_id", "id")->toArray();

        // Get Allocation From Entity Other Allocation
        $entityAllocationOther = App\Models\Backend\EntityAllocationOther::select('entity_id', "id")->WhereRaw("FIND_IN_SET($user_id,other)")->get()->pluck("entity_id", "id")->toArray();
        return $final_entity = array_unique(array_merge($entityAllocationOther, $entityAllocation));
    }
    //for superadmin return 1
    return 1;
}

/*
 * Created by - Pankaj
 * all user hierarchy list
 * 
 */

function getUserHierarchyDetail($user_id = '-1') {
    $userHierarchy  = \App\Models\Backend\UserHierarchy::where("user_id",$user_id)->count();
    $UserArray = array();
    if($userHierarchy > 0){
    $userHierarchy = DB::select("CALL get_user_hierarchy($user_id)");
    foreach ($userHierarchy as $row) {
        $designation_name = strtolower(str_replace(' ', '_', $row->designation_name));
        $UserArray[$row->id][$designation_name] = array("id" => $row->user_id, "username" => $row->userfullname);
    }
    return $UserArray;
    }
    return $UserArray;
}

function getHistoryUserHierarchyDetail($user_id = '-1') {
    
   $userHierarchy  = \App\Models\Backend\UserHierarchy::where("user_id",$user_id);
    $UserArray = array();
    if($userHierarchy->count() > 0){
    $i = 0;
    foreach ($userHierarchy->get() as $row) {
        $UserArray[$i] = $row->designation_name . "-" . $row->userfullname;
        $i++;
    }
    if (!empty($UserArray)) {
        $UserArray = array_reverse($UserArray);
        $UserValue = implode(",", $UserArray);
    }
    //showArray($UserValue);exit;
    return $UserValue;
    }else{
    return $UserArray;
    }
}

/*
 * Created by - Pankaj
 * user hierarchy list user id wise
 * 
 */

function getUserDetails($user_id = '-1') {
    $userHierarchy  = \App\Models\Backend\UserHierarchy::where("user_id",$user_id)->count();
    $UserArray = array();
    if($userHierarchy > 0){
    $userHierarchy = DB::select("CALL get_user_hierarchy($user_id)");
    foreach ($userHierarchy as $row) {
        $UserArray[$row->id][$row->designation_id] = $row->user_id;
    }
    return $UserArray;
    }
    return $UserArray;
}

function getUserHierarchyDetails($user_id = '-1') {
    $userHierarchy  = \App\Models\Backend\UserHierarchy::where("user_id",$user_id)->count();
    $UserArray = array();
    if($userHierarchy > 0){
    $userHierarchy = DB::select("CALL get_user_hierarchy($user_id)");
    $manager = $tl = '';
    foreach ($userHierarchy as $row) {
        if ($row->designation_id == 9) {
            $manager = $row->user_id;
        }
        if ($row->designation_id == 60) {
            $tl = $row->user_id;
        }
        $UserArray[] = $row->designation_name . '=>' . $row->userfullname;
    }
    return array("userArray" => $UserArray, "manager" => $manager, "tl" => $tl);
    }
    return array("userArray" => '', "manager" => '', "tl" => '');
}

/*
 * Created by: Jayesh Shingrakhiya
 * Arrange all data with user hirerachy
 */

function arrangeData($data) {
    $userHirerachy = getUserHierarchyDetail();
    foreach ($data as $key => $value) {
        $value->assignee = isset($userHirerachy[$value->user_id]) ? $userHirerachy[$value->user_id] : array();
    }
    return $data;    
}

/*
 * Created by: Jayesh Shingrakhiya
 * Convert text camal case to normal text
 */

function convertcamalecasetonormalcase($array) {
    $arrangeArray = array();
    foreach ($array AS $input => $expected) {
        $output = preg_replace(array('/(?<=[^A-Z])([A-Z])/', '/(?<=[^0-9])([0-9])/'), ' $0', $input);
        $output = ucwords($output);
        $arrangeArray[$expected] = $output;
    }
    return $arrangeArray;
}

/*
 * Created by: Jayesh Shingrakhiya
 * Generate unique entity code
 */

function generateClientCode($entity_name, $entity_id = null) {
    // build client code
    $clientName = preg_replace('/[^a-zA-Z0-9]/', '_', $entity_name);
    $ClientnameArray = explode("_", $clientName);

    $wordsCounter = count($ClientnameArray);
    $defaultCounter = 0;
    $Clientcode = "";

    While ($defaultCounter < $wordsCounter) {
        $wordLgth = 0;
        $word = $ClientnameArray[$defaultCounter];
        $wordLgth = strlen($ClientnameArray[$defaultCounter]);
        if ($wordsCounter == 1) {
            if (strlen($Clientcode) < 8) {
                if ($wordLgth >= 8) {
                    $Clientcode .= substr($word, 0, 8);
                } else {
                    $Clientcode .= substr($word, 0, $wordLgth);
                }
            }
        } else {
            if (strlen($Clientcode) < 8 && ($word != '')) {
                if ($wordLgth >= 3) {
                    if (strlen($Clientcode) == 0) {
                        $Clientcode .= substr($word, 0, 3);
                    } else {
                        $len = 8 - strlen($Clientcode);
                        $Clientcode .= substr($word, 0, $len);
                    }
                } else {
                    $Clientcode .= substr($word, 0, $wordLgth);
                }
            }
        }

        $defaultCounter++;
        if ($defaultCounter == $wordsCounter) {
            if (strlen($Clientcode) < 8)
                $Clientcode = str_pad($Clientcode, 8, "0", STR_PAD_LEFT);
        }

        // check if client code is unique or not
        if (strlen($Clientcode) == 8) {
            $entityCode = new App\Models\Backend\Entity;
            if ($entity_id != '')
                $entityCode->Where('id', '<>', $entity_id);

            $Codeexist = $entityCode->where('code', $Clientcode)->count();
            $referenceCodeExist = \App\Models\Backend\QuoteMaster::where('reference_code', $Clientcode)->count();
            if ($Codeexist == 1 || $referenceCodeExist == 1) {
                $strName = str_replace(' ', '', $entity_name);
                $seed = str_split($strName);
                shuffle($seed);
                $Clientcode = '';
                $codeLength = (count($seed) >= 8) ? 8 : count($seed);
                foreach (array_rand($seed, $codeLength) as $k) {
                    $Clientcode .= $seed[$k];
                }
                $Clientcode = str_pad($Clientcode, 8, "0", STR_PAD_LEFT);
                break;
            } else {
                break;
            }
        } else {
            $word = "";
            $wordLgth = 0;
        }
    }
    $Clientcode = preg_replace('/[^a-zA-Z0-9]/', 'o', $Clientcode);
    return strtoupper($Clientcode);
}

/*
 * Created by: Jayesh Shingrakhiya
 * Function will be use for remove file with directory.
 */

function removeDirWithFiles($dirPath) {
    if (!is_dir($dirPath)) {
        throw new InvalidArgumentException("$dirPath must be a directory");
    }
    if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
        $dirPath .= '/';
    }
    $files = glob($dirPath . '*', GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file)) {
            removeDirWithFiles($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dirPath);
}

/*
 * created By Pankaj
 * Date : 23-07-2018
 * for get designation wise all entity allocation detail  
 */

function entityAllocation($designation_id) {
    $entityAllocation = DB::select("CALL get_entity_allocation($designation_id)");
    foreach ($entityAllocation as $row) {
        $entityAllocationArr[$row->entity_id . " - " . $row->service_id] = $row->allocation;
    }
    return $entityAllocationArr;
}

function entityAllocationId($designation_id) {
    $entityAllocation = DB::select("CALL get_entity_allocation_id($designation_id)");
    foreach ($entityAllocation as $row) {
        $entityAllocationArr[$row->entity_id . " - " . $row->service_id] = $row->allocation;
    }
    return $entityAllocationArr;
}

/*
 * Created By: Jayesh Shingrakhiya
 * Created On: 03-08-2018
 * To get entity allocation data based on service allocation
 */

function entityAllocationdata($entity_id, $service_id = NULL) {
    $entity = App\Models\Backend\EntityAllocation::where('entity_id', $entity_id);
    if (is_array($service_id))
        return $entity->whereIn('service_id', $service_id)->get()->toArray();
    if ($service_id != NULL)
        return $entity->where('service_id', $service_id)->first();
    else
        return $entity->get()->toArray();
}

function entityAllocationFeedback($entity_id, $service_id = NULL) {
    $entity = App\Models\Backend\EntityAllocation::where('entity_id', $entity_id);
    if (is_array($service_id))
        return $entity->whereIn('service_id', $service_id)->get()->toArray();
    if ($service_id != NULL)
        return $entity->where('service_id', $service_id)->get()->toArray();
    else
        return $entity->get()->toArray();
}

/*
 * created By Pankaj
 * Date : 23-07-2018
 * for get default_worksheet_period 
 */

function default_worksheet_period() {
    $default_period[] = date("01-m-Y", strtotime("-3 months"));
    $default_period[] = date("t-m-Y", strtotime("+3 months"));
    return $default_period;
}

/*
 * created By Pankaj
 * Date : 22-08-2018
 * for get default_worksheet_period 
 */

function stringsearch($src, $srch) {
    $flag = strstr($src, $srch);
    return $flag;
}

function replaceString($srch, $rep, $src) {
    $cont = str_replace($srch, $rep, $src);
    return $cont;
}

/*
 * created By Pankaj
 * Date : 24-08-2018
 * for get default_worksheet_period 
 */

function checkUserTeamRight($checkOtherRight = 0, $type = null) {

    $userId = getLoginUserHierarchy();
    $teamIds = $userId->team_id;
    $otherIds = array();
    if ($checkOtherRight == 1) {
        $otherIds = explode(",", $userId->other_right);
    }
    if (!empty($otherIds)) {
        $teamIds = explode(",", $userId->team_id);
        $teamIds = array_merge($teamIds, $otherIds);
        if ($type == 'Array') {
            return $teamIds;
        } else {
            return $teamIds = implode(",", $teamIds);
        }
    }
}

/*
 * Created by - Pankaj
 * check export and download right
 * 
 *  @param  int   $user_id 
 *  @param  char   $tab_unique_name
 *  @param  char   $privileges export,download  
 */

function checkButtonRights($tab_id, $button_name) {
    $user = getLoginUserHierarchy();
    if ($user->designation_id != config('constant.SUPERADMIN')) {
        $tabButtonId = \App\Models\Backend\Button::select('id')
                ->where('tab_id', $tab_id)
                ->where("button_name", $button_name);
        // check if such a tab button exists or not
        if ($tabButtonId->count() == 0) {
            return false;
        }
        $tabButtonId = $tabButtonId->first();
        $userPrivilege = \App\Models\Backend\UserTabRight::where('user_id', $user->user_id)
                ->where('tab_id', $tab_id)
                ->whereRaw("FIND_IN_SET($tabButtonId->id,other_right)");

        if ($userPrivilege->count() == 0) {
            return false;
        } else
            return true;
    }
    return true;
}

/*
 * Created by: Jayesh Shingrakhiya
 * Created on: Oct 23, 2018
 * Purpose: Will use global level where need drop down
 *  @param  char   $table 
 *  @param  char   $colunm
 *  @param  json   $condition
 *  @param  char   $sortBy
 *  @param  char   $sortOrder
 */

function dropDown($table, $colunm, $condition, $sortBy, $sortOrder, $groupBy = null) {
    $colunm = explode(',', $colunm);
    $dropDown = app('db')->table($table)->select($colunm);
    if ($condition != '') {
        $dropDown = search($dropDown, $condition);
    }

    if ($table == 'entity') {
        $user_id = app('auth')->guard()->id();
        $entityId = checkUserClientAllocation($user_id);
        if ($entityId != 1) {
            $entityIds = implode(",", $entityId);
            $dropDown = $dropDown->whereRaw('id IN (' . $entityIds . ')');
        }
    }

    $dropDown->orderBy($sortOrder, $sortBy);
    if ($groupBy != null) {
        $dropDown->groupBy($groupBy);
    }

    return $dropDown->get()->toArray();
}

/*
 * Created by: Jayesh Shingrakhiya
 * Created on: Dec 24, 2018
 * Purpose: Get worklogs from jira
 *  @param  char   $table 
 *  @param  char   $colunm
 *  @param  json   $condition
 *  @param  char   $sortBy
 *  @param  char   $sortOrder
 */

function getWorklogs($date, $startAt = 0, $maxResults = 100) {

    $fields = http_build_query(array(
        'jql' => 'worklogDate = ' . $date,
        "fields" => "worklog",
        "startAt" => $startAt,
        "maxResults" => $maxResults,
    ));
    $host = "https://befree.atlassian.net/rest/api/2/search/?" . $fields;

    $username = "it@befree.com.au";
    $password = "zYMNGr2wuOoQmcrIeXZRFC1D";

    $headers = array(
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($username . ":" . $password)
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $host);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $reseponse = curl_exec($ch);
    curl_close($ch);
    return json_decode($reseponse);
}

function getIssueWorklogs($key) {

    $host = "https://befree.atlassian.net/rest/api/2/issue/" . $key . "/worklog";

    $username = "it@befree.com.au";
    $password = "zYMNGr2wuOoQmcrIeXZRFC1D";

    $headers = array(
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($username . ":" . $password)
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $host);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $reseponse = curl_exec($ch);
    curl_close($ch);

    return json_decode($reseponse);
}

/*
 * Created by: Jayesh Shingrakhiya
 * Created on: Dec 28, 2018
 * Reason: To get today is sunday or not
 */

function todayisSundayOrHoliday($today, $shiftId) {
    $isSunday = date('l', strtotime($today));
    $isHoliday = '';
    if ($isSunday == "Sunday") {
        $isHoliday = "Sunday";
    } else {
        $holiday = \App\Models\Backend\HrHoliday::where('date', $today)->leftjoin('hr_holiday_detail as hhd', 'hhd.hr_holiday_id', '=', 'hr_holiday.id')->where('shift_id', $shiftId)->count();
        if ($holiday >= 1) {
            $isHoliday = "Holiday";
        }
    }
    return $isHoliday;
}

/*
 * Created by: Jayesh Shingrakhiya
 * Created on: Jan 02, 2018
 * Reason: To get user total working time
 */

//function getWorkingTime($userId, $date) {
//    $userInOut = \App\Models\Backend\HrUserInOuttime::where('user_id', $userId)->where('date', $date)->orderBy('punch_time', 'ASC')->get()->toArray();
//    $InOut = array();
//    foreach ($userInOut as $key => $value)
//        $InOut[$value['punch_type']][] = $value['punch_time'];
//
//    $totalIn = !empty($InOut[1]) ? count($InOut[1]) : 0;
//    $totalOut = !empty($InOut[0]) ? count($InOut[0]) : 0;
//
//    $inTime = !empty($InOut[1]) ? min($InOut[1]) : '00:00:00';
//    $outTime = !empty($InOut[0]) ? max($InOut[0]) : (($inTime != '00:00:00') ? date("H:i:s") : '00:00:00');
//
//    $inTimeWithDate = $date . ' ' . $inTime;
//    $outTimeWithDate = $date . ' ' . $outTime;
//
//    $inTimeWithDateStrtotime = strtotime($inTimeWithDate);
//    $outTimeWithDateStrtotime = strtotime($outTimeWithDate);
//    $diff = $outTimeWithDateStrtotime - $inTimeWithDateStrtotime;
//    $totalWorkingHour = date('H:i:s', $diff);
//
//    if ($totalIn == $totalOut) {
//        $totTime = $diffValue = 0;
//
//        for ($i = 0; $i < $totalIn; $i++) {
//            $tempIn = strtotime((string) $InOut[1][$i]);
//            $tempOut = strtotime((string) $InOut[0][$i]);
//            $diffValue = $tempOut - $tempIn;
//            $totTime = $totTime + $diffValue;
//        }
//
//        $actualWorkingTime = date('H:i:s', $totTime);
//        $breakTimeStrtotime = $diff - strtotime($actualWorkingTime);
//        $breakTime = date('H:i:s', $breakTimeStrtotime);
//    } else {
//        if ($inTime != '' && $outTime != '') {
//            $actualWorkingTime = $totalWorkingHour;
//            $breakTime = '00:00:00';
//        } else {
//            $actualWorkingTime = '00:00:00';
//            $breakTime = '00:00:00';
//        }
//    }
//
//    $punchIn = isset($userInOut[0]['punch_time']) ? $userInOut[0]['punch_time'] : '00:00:00';
//    $punchOut = $userInOut[count($userInOut) - 1]['punch_time'];
//    return array('punch_in' => $punchIn, 'punch_out' => $punchOut, 'working_time' => $actualWorkingTime, 'break_time' => $breakTime);
//}

/**
 * Modified By : Alok Shukla
 * Modified On: 24-04-2019
 * @param type $userId
 * @param type $date
 * @return type
 */
function getWorkingTime($userId, $date) {
    $userInOut = \App\Models\Backend\HrUserInOuttime::where('user_id', $userId)->where('date', $date)->orderBy('punch_time', 'ASC')->get()->toArray();
    $InOut = array();
    foreach ($userInOut as $key => $value)
        $InOut[$value['punch_type']][] = $value['punch_time'];

    $totalIn = !empty($InOut[1]) ? count($InOut[1]) : 0;
    $totalOut = !empty($InOut[0]) ? count($InOut[0]) : 0;

    $inTime = !empty($InOut[1]) ? min($InOut[1]) : '00:00:00';
    //$outTime = !empty($InOut[0]) ? max($InOut[0]) : (($inTime != '00:00:00') ? date("H:i:s") : '00:00:00');
    $outTime = !empty($InOut[0]) ? max($InOut[0]) : (($inTime != '00:00:00') ? '00:00:00' : '00:00:00');

    //Alok Started 
    $finalArray = array();
    $finalDataOddValues = array();
    $finalDataEvenValues = array();
    $actualWorkingTime = "00:00:00";
    $breakTime = "00:00:00";
    $lastoutTime = !empty($InOut[0]) ? max($InOut[0]) : '00:00:00';
    if ($userInOut) {
        $i = 0;
        foreach ($userInOut as $k => $inOutData) {
            if ($i > 0) {
                $start_time = new DateTime($userInOut[$i - 1]['punch_time']);
                $end_time = new DateTime($userInOut[$i]['punch_time']);
                $diff = $end_time->diff($start_time);
                $outhour = $diff->format('%H:%I:%S');
                array_push($finalArray, $outhour);
            } else {
                array_push($finalArray, '00:00:00');
            }
            $i++;
        }
        $finalDataOddValues = array_filter($finalArray, function ($input) {
            return $input & 1;
        }, ARRAY_FILTER_USE_KEY);
        $finalDataEvenValues = array_filter($finalArray, function ($input) {
            return !($input & 1);
        }, ARRAY_FILTER_USE_KEY);
    }

    $actualWorkingTime = calculatTimeWithHHMMSS($finalDataOddValues);
    $breakTime = calculatTimeWithHHMMSS($finalDataEvenValues);
    $punchIn = isset($userInOut[0]['punch_time']) ? $userInOut[0]['punch_time'] : '00:00:00';
//    $punchOut = $userInOut[count($userInOut) - 1]['punch_time'];
    return array('punch_in' => $punchIn, 'punch_out' => $lastoutTime, 'working_time' => $actualWorkingTime, 'break_time' => $breakTime);
}

/* Created by: Jayesh Shingrakhiya
 * Created on: March 19, 2019
 * Reason: Get financial Year
 */

function financialYear() {
    $financialYear = \App\Models\Backend\Constant::whereIn('constant_name', ['CURRENT_FINANCIAL_YEAR', 'PREVIOUS_FINANCIAL_YEAR'])->pluck('constant_value', 'constant_name')->toArray();
    $today = strtotime(date('Y-m-d'));
    $month = date('n');
    if ($month > 6) {
        $previousYear = strtotime(date('Y-06-30'));
    } else {
        $previousYear = strtotime(date('Y-06-30') . " -1 year");
    }
    $currentYear = strtotime(date('Y-07-01'));

    if ($today <= $previousYear)
        $year = $financialYear['PREVIOUS_FINANCIAL_YEAR'];
    else
        $year = $financialYear['CURRENT_FINANCIAL_YEAR'];

    return $year;
}

function urlEncrypting($argumentArray) {
    $url = array();
    foreach ($argumentArray as $key => $value) {
        $url[] = urlencode(base64_encode($key)) . '=' . urlencode(base64_encode($value));
    }
    return implode('&', $url);
}

/*
 * Created by: Jayesh Shingrakhiya
 * Created on: March 26, 2019
 * Reason find out user user designation user ids
 */

function getUserUpperDesignationEmployee($id = null, &$upperDesignationEmployeeIds = array()) {
    $userId = $id;
    if ($id == null) {
        $userId = app('auth')->guard()->id();
        $upperDesignationEmployeeIds[] = $userId;
    }

    $hierarchy = App\Models\Backend\UserHierarchy::select('parent_user_id')->where('user_id', $userId)->get();
    if ($hierarchy[0]->parent_user_id != 0) {
        $upperDesignationEmployeeIds[] = $hierarchy[0]->parent_user_id;
        getUserUpperDesignationEmployee($hierarchy[0]->parent_user_id, $upperDesignationEmployeeIds);
    } else {
        return $upperDesignationEmployeeIds;
    }
}

/*
 * Created by: Jayesh Shingrakhiya
 * Created on: March 26, 2019
 * Reason find out user user designation user ids
 */

function getUserDownDesignationEmployee($id = null, &$downDesignationEmployeeIds = array(), &$actual = array(), &$checkedUserIds = array()) {
    $userId = $id;
    if ($id == null) {
        $userId = app('auth')->guard()->id();
        $downDesignationEmployeeIds[] = $userId;
        $actual = App\Models\Backend\UserHierarchy::where('parent_user_id', $userId)->pluck('user_id', 'user_id')->toArray();
    }

    $hierarchy = App\Models\Backend\UserHierarchy::select('user_id', 'parent_user_id')->where('parent_user_id', $userId)->get();
    if (count($hierarchy) > 0) {
        if ($hierarchy[0]->parent_user_id == $userId) {

            foreach ($hierarchy as $key => $value) {
                if (in_array($value->user_id, $actual))
                    $checkedUserIds[] = $value->user_id;

                $downDesignationEmployeeIds[] = $value->user_id;
                getUserDownDesignationEmployee($value->user_id, $downDesignationEmployeeIds, $actual, $checkedUserIds);
            }
        }
    }
    return $downDesignationEmployeeIds;
}

/**
 * Calculate Time With Hour, Minute, Seconds
 * @param type $times
 * @return type
 */
function calculatTimeWithHHMMSS($times) {
    $seconds = 0;
    foreach ($times as $time) {
        list($hour, $minute, $second) = explode(':', $time);
        $seconds += $hour * 3600;
        $seconds += $minute * 60;
        $seconds += $second;
    }
    $hours = floor($seconds / 3600);
    $seconds -= $hours * 3600;
    $minutes = floor($seconds / 60);
    $seconds -= $minutes * 60;
    // return "{$hours}:{$minutes}:{$seconds}";
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function getSQL($builder) {
    $sql = $builder->toSql();
    foreach ($builder->getBindings() as $binding) {
        $value = is_numeric($binding) ? $binding : "'" . $binding . "'";
        $sql = preg_replace('/\?/', $value, $sql, 1);
    }
    return $sql;
}

function autoAssignAllEntityUser($entity_id) {
    $userTabRight = \App\Models\Backend\UserTabRight::leftjoin("user as u", "u.id", "user_tab_right.user_id")
                    ->select(app('db')->raw('GROUP_CONCAT(user_tab_right.user_id) as user_id'))
                    ->whereRaw('FIND_IN_SET(42, user_tab_right.other_right)')
                    ->where("u.is_active", "1")
                    ->where('user_tab_right.tab_id', 18)->get();

    \App\Models\Backend\EntityAllocationOther::insert(['entity_id' => $entity_id, 'other' => $userTabRight[0]->user_id]);
}

function cronNotWorking($cronName, $message) {
    $data['to'] = 'bdmsdeveloper@befree.com.au';
    $data['subject'] = $cronName . ' cron not run dated: ' . date('d-m-Y H:i:s');
    $data['content'] = '<h3 style="font-family:sans-serif;">Hello Team,</h3><p style="font-family:sans-serif;">Update remark previous day cron does not execute due to below mentioned exception.</p><p style="font-family:sans-serif;">' . $message . '</p>';
    storeMail('', $data);
}

function numberFormat($amount) {
    return number_format($amount, 2);
}

function sumTotal($num1, $num2) {
    $num1[] = $num2;
    return $num1;
}

function divisionHead($quoteMaster) {
    $divisionHead = array();
    $divisionHead['stage_id'] = 2;
    $divisionHead['userfullname'] = '';
    $divisionHead['email'] = '';
    if ($quoteMaster->is_new_entity == 2) {
        $entityAllocationDetail = \App\Models\Backend\EntityAllocation::where('entity_id', $quoteMaster->entity_id)->get();
        if (!empty($entityAllocationDetail) && isset($entityAllocationDetail[0]->allocation_json)) {
            $decodeJson = \GuzzleHttp\json_decode($entityAllocationDetail[0]->allocation_json, true);
            if (!empty($decodeJson) && isset($decodeJson[15])) {
                $userDetail = \App\Models\User::select('userfullname', 'email')->find($decodeJson[15]);
                if (isset($userDetail)) {
                    $divisionHead['userfullname'] = isset($userDetail->userfullname) ? $userDetail->userfullname : '';
                    $divisionHead['email'] = isset($userDetail->email) ? $userDetail->email : '';
                }
            }
        }
    }

    if ($quoteMaster->is_new_entity == 1) {
        $userDetail = \App\Models\Backend\UserHierarchy::select('userfullname', 'email')->leftjoin('user as u', 'u.id', 'user_id')->where('designation_id', 15)->whereRaw('FIND_IN_SET(6, team_id)');
        if ($userDetail->count() > 0) {
            $userDetail = $userDetail->get();
            $divisionHead['userfullname'] = $userDetail[0]->userfullname;
            $divisionHead['email'] = $userDetail[0]->email;
        }
    }
    return $divisionHead;
}

function generateStrongPassword($length = 9, $add_dashes = false, $available_sets = 'luds') {
    $sets = array();
    if (strpos($available_sets, 'l') !== false)
        $sets[] = 'abcdefghjkmnpqrstuvwxyz';
    if (strpos($available_sets, 'u') !== false)
        $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
    if (strpos($available_sets, 'd') !== false)
        $sets[] = '23456789';
    if (strpos($available_sets, 's') !== false)
        $sets[] = '!@#$%&*?';

    $all = '';
    $password = '';
    foreach ($sets as $set) {
        $password .= $set[array_rand(str_split($set))];
        $all .= $set;
    }

    $all = str_split($all);
    for ($i = 0; $i < $length - count($sets); $i++)
        $password .= $all[array_rand($all)];

    $password = str_shuffle($password);

    if (!$add_dashes)
        return $password;

    $dash_len = floor(sqrt($length));
    $dash_str = '';
    while (strlen($password) > $dash_len) {
        $dash_str .= substr($password, 0, $dash_len) . '-';
        $password = substr($password, $dash_len);
    }
    $dash_str .= $password;
    return $dash_str;
}

function signatureTemplate($templateId, $entityId, $serviceId = 1) {
    $allocation = \App\Models\Backend\EntityAllocation::where("entity_id", $entityId)
                    ->where("service_id", $serviceId)->first();
    $decodeAllocation = \GuzzleHttp\json_decode($allocation->allocation_json, true);
    $userId = \App\Models\Backend\Entity::where('id', $entityId)->select("user_signature")->first();
    $tam = $tl = '';
    if (isset($decodeAllocation[60]) && $decodeAllocation[60] != '')
        $tl = $decodeAllocation[60];
    //$signatureTemplate = \App\Models\Backend\SignatureTemplate::where("id", $templateId)->first();
    if ($userId->user_signature > 0) {
        $signatureData = App\Models\Backend\Signature::where('user_id', $userId->user_signature)->where("template_id", $templateId);
        if ($signatureData->count() > 0) {
            $signatureData = $signatureData->first();
            $signatureContent = $signatureData->signature;
            return $signatureContent;
        } else if ($tl != '') {
            $signatureData = App\Models\Backend\Signature::where('user_id', $tl)->where("template_id", $templateId);
            if ($signatureData->count() > 0) {
                $signatureData = $signatureData->first();
                $signatureContent = $signatureData->signature;
                return $signatureContent;
            } else {
                return 'SIGNATURE';
            }
        }
    } else if ($tl != '') {
        $signatureData = App\Models\Backend\Signature::where('user_id', $tl)->where("template_id", $templateId);
        if ($signatureData->count() > 0) {
            $signatureData = $signatureData->first();
            $signatureContent = $signatureData->signature;
            return $signatureContent;
        } else {
            return 'SIGNATURE';
        }
    } else {
        return 'SIGNATURE';
    }
}

function signatureUser($templateId, $userId) {
    $signatureData = App\Models\Backend\Signature::where('user_id', $userId)->where("template_id", $templateId);
    if ($userId != '' && $signatureData->count() > 0) {
        $signatureData = $signatureData->first();
        $signatureContent = $signatureData->signature;
        return $signatureContent;
    } else {
        return 'SIGNATURE';
    }
}

function checkSandwich($hrDetailId, $userId, $date) {
    $hrIds = array();
    $oldHrDetail = App\Models\Backend\HrDetail::where("user_id", $userId)->where("hr_final_remark", "3")
            ->whereRaw("date < '" . $date . "'")
            ->orderBy("date", "desc");
    if ($oldHrDetail->count() > 0) {
        $oldHrDetail = $oldHrDetail->first();
        $fdate = $oldHrDetail->date;
        showArray($oldHrDetail);
        $tdate = $date;
        echo $date_join = date_create($fdate);
        echo $date_today = date_create($tdate);
        echo $days = $date_today->diff($date_join)->format("%a");

        if ($days > 2) {
            $hdetail = App\Models\Backend\HrDetail::where("user_id", $userId)
                            ->whereRaw("date > '" . $fdate . "' && date < '" . $tdate . "'")->get();
            foreach ($hdetail as $h) {
                $isSundayOrHoliday = todayisSundayOrHoliday($h->date, $h->shift_id);
                if ($isSundayOrHoliday != '') {
                    $hrIds[] = $h->id;
                }
            }
            if (!empty($hrIds)) {
                $hrIds = implode(",", $hrIds);
                App\Models\Backend\HrDetail::whereRaw("id IN ($hrIds)")->update(["status" => "6", "final_remark" => "3", "hr_final_remark" => 3]);
            }
        }
    }
}

function getSaturday($startDt, $endDt, $weekNum) {
    $startDt = strtotime($startDt);
    $endDt = strtotime($endDt);
    $dateSun = array();
    do {
        if (date("w", $startDt) != $weekNum) {
            $startDt += (24 * 3600); // add 1 day
        }
    } while (date("w", $startDt) != $weekNum);
    while ($startDt <= $endDt) {
        $dateSun[] = date('d-m-Y', $startDt);
        $startDt += (7 * 24 * 3600); // add 7 days
    }
    return($dateSun);
}

function monthsBetween($startDate, $endDate) {
    $retval = "";

    // Assume YYYY-mm-dd - as is common MYSQL format
    $splitStart = explode('-', $startDate);
    $splitEnd = explode('-', $endDate);

    if (is_array($splitStart) && is_array($splitEnd)) {
        $difYears = $splitEnd[0] - $splitStart[0];
        $difMonths = $splitEnd[1] - $splitStart[1];
        $difDays = $splitEnd[2] - $splitStart[2];

        $retval = ($difDays > 0) ? $difMonths : $difMonths - 1;
        $retval += $difYears * 12;
    }
    return $retval;
}

function convertXeroDate($date) {
    $countData = strlen($date);
    if($countData == 26){
    $timeStamp = substr($date, 6, 10);
    }else{
        $timeStamp = substr($date, 6, 9);
    }
    $dt = new \DateTime('@' . $timeStamp);                // Create PHP DateTime object from unix timestamp
    return $dt->format('Y-m-d');
}

function encryptTFN($message, $key) {
    $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($message, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    $finalMessage = $iv . $encrypted;
    return base64_encode($finalMessage);
}

function decryptTFN($encryptedMessage, $key) {
    $encryptedMessage = base64_decode($encryptedMessage);
    $ivSize = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($encryptedMessage, 0, $ivSize);
    $message = substr($encryptedMessage, $ivSize);
    $decrypted = openssl_decrypt($message, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return $decrypted;
}

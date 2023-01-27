<?php
require_once('config.inc.php');
require_once('utils/getIP.php');
require_once('php-boilerplate/libraries/Request.php');

use Libraries\Request;

define('DB_VERSION', '0.2');

//TODO: Add URL column to the table or URL identifier and a separate URLs table

$connect_db = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

if (!$connect_db) {
    die('Could not connect: ' . mysqli_error($connect_db));
} else {
    if(!mysqli_query($connect_db, "SELECT 1 FROM visitorsdata LIMIT 1")) {
        $sql = "CREATE TABLE visitorsdata (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            siteid INT(6) UNSIGNED,
            url VARCHAR(255),
            time TIMESTAMP,
            data TEXT,
            ip VARCHAR(30),
            carbon_intensity TEXT
        );";
        $result = mysqli_query($connect_db, $sql);
        if (!$result) {
            die('Could not create table: ' . mysqli_error($connect_db));
        }
    };
    
        $sql = "CREATE TABLE IF NOT EXISTS urlsdata (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            url VARCHAR(255)
        );";
        $result = mysqli_query($connect_db, $sql);
        if (!$result) {
            die('Could not create table urlsdata: ' . mysqli_error($connect_db));
        }
    
}

// if the endpoint is visited count the visit
if (isset($_GET['appendvisit'])) {
    // visit data
    $json_data = json_decode(file_get_contents("php://input"), TRUE);
    $visit_data = serialize($json_data);
    $visit_IP = explode(',', getIPAddress());
    $siteid = $json_data['siteid'];
    $tracked_url = $json_data['url'];
    // CURL IP Data
    // Expected result:
    // $carbon_data = array(
    //     "country_name"=> "United Kingdom",
    //     "country_code_iso_2" => "GB",
    //     "country_code_iso_3"=> "GBR",
    //     "carbon_intensity_type" => "avg",
    //     "carbon_intensity" => "0.0",
    //     "generation_from_fossil" => "0.0",
    //     "year" => "2020",
    //     "checked_ip" => "127.0.0.1"
    // );

    $carbon_curl = curl_init();

    $url = 'https://api.thegreenwebfoundation.org/api/v3/ip-to-co2intensity/' . $visit_IP[0];
    $headers = array('Accept: application/json', 'Content-Type: application/json');
    $response = Request::get($url, '', $headers);
    
    // curl_setopt_array($carbon_curl, array(
    //     CURLOPT_RETURNTRANSFER => true,
    //     CURLOPT_CUSTOMREQUEST => 'GET',
    //     //CURLOPT_HEADER => false,
    //     CURLOPT_URL => $url,
    //     //CURLOPT_HTTPHEADER => array('Accept: application/json', 'Content-Type: application/json')
    // ));

    // $response = curl_exec($carbon_curl);
    $co2result = $response ? serialize($response) : $url;

    curl_close($carbon_curl);
    
    if($visit_data) {
        $sql = "INSERT INTO visitorsdata (time, data, ip, carbon_intensity, siteid, url) VALUES (NOW(), '$visit_data', '$visit_IP[0]', '$co2result', '$siteid', '$tracked_url')";
        $result = mysqli_query($connect_db, $sql);
        if (!$result) {
            die('Could not enter data: ' . mysqli_error($connect_db));
        }
        $output = array('message' => "Entered data successfully", 'carbon' => $co2result);
    } else {
        $output = $_POST;
    }
}

else if (isset($_GET['getvisits']) && isset($_GET['siteid'])) {
    // get all visits
    $siteid = $_GET['siteid'];
    $sql = "SELECT * FROM visitorsdata WHERE siteid = '$siteid' LIMIT 1000";
    $result = mysqli_query($connect_db, $sql);
    if (!$result) {
        die('Could not get data: ' . mysqli_error($connect_db));
    } else {
        $output = array();
        while ($row = mysqli_fetch_assoc($result)) {
            //check serialized data
           

            $output[] = array(
                'id'    => $row['id'],
                'time' => $row['time'],
                'data' => unserialize($row['data']),
                'ip' => $row['ip'],
                'carbon_intensity' => $row['carbon_intensity'] ? unserialize($row['carbon_intensity']) : false
            );
        }
    }
} else {
    $output = "No parameter specified";
}

header('Content-type: application/json; charset=utf-8');
echo json_encode($output);
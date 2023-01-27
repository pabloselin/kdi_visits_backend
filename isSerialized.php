<?php 
if(!function_exists('isSerialized')){
    function isSerialized($string){
        $tempString = '';
        $array = @unserialize($string);
        if(is_array($array)){
            foreach ($array as $k=>$i){
                //do something with data
                $tempString .= $k . $i['something'];
            }
        } else {
            $tempString = $string;
        }
        return $itemString;
    }
}
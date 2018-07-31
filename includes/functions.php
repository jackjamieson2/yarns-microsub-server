<?php
/**
 * Utility functions
 *
 * @author Jack Jamieson
 *
 */


function test(){
    $data = [];
    $data[] = "1";
    $data[] = "2";
    $data[] = "3";
    $data[] = "4";

    foreach ($data as $i=>$item){
        $data[$i] = $data[$i] . " success";
    }

    return $data;
}

function encode_array($data){
    if (is_array($data)){
        foreach ($data as $key=>$item){
            if (is_array($item)){
                //encode the child array as well
                $data[$key] = encode_array($item);
            } else {
                //encode the item
                $data[$key] = htmlspecialchars($item);
            }
        }
    }
    return $data;
}


function decode_array($data){
    if (is_array($data)){
        foreach ($data as $key=>$item){
            if (is_array($item)){
                //encode the child array as well
                $data[$key] = decode_array($item);
            } else {
                //encode the item
                $data[$key] = htmlspecialchars_decode($item);

                // * Some feeds (e.g. thestar.com) have html entities in the description, in which case they could be
                // decoded.  But others (e.g.  cbc.ca) do not.  Think about what to do here.
            }
        }
    }
    return $data;
}
<?php
/**
 * Utility functions
 *
 * @author Jack Jamieson
 *
 */


function test(){
    /*

    $channel = 'test';
    $num_posts = 100;

    //Get all the posts of type yarns_microsub_post

    $args = array(
        'post_type' => 'yarns_microsub_post',
        'post_status' => 'publish',
        'yarns_microsub_post_channel' => $channel,
        'posts_per_page' => $num_posts
    );
    $query = new WP_Query($args);

    // notes for paging: https://stackoverflow.com/questions/10827671/how-to-get-posts-greater-than-x-id-using-get-posts
    $ids = []; // store a list of post ids returned by the query
    $timeline_items = [];
    while ($query->have_posts()) {
        $query->the_post();

        $id = get_the_ID();

        $item = get_post_meta($id,'yarns_microsub_json',true);

    }


    wp_reset_query();

    if ($timeline_items) {
        $timeline['items'] = $timeline_items;
        return $timeline;
    }


*/
}

function encode_array($data){
    if (is_array($data)){
        foreach ($data as $key=>$item){
            if (is_array($item)){
                //encode the child array as well
                $data[$key] = encode_array($item);
            } else {
                // only encode [html] items
                if (strtolower($key) == 'html'){
                    // Some rss feeds have pre-encoded html. Decode those.
                    if (!strpos($item, '<') && strpos($item, '&lt;')>=0){
                        $item = html_entity_decode($item);
                    }
                    // Add slashes to prevent double quotes from mucking things up
                    $data[$key] = addslashes($item);


                    $data[$key] = $item;

                   // $data[$key] = htmlspecialchars($item, ENT_COMPAT, "UTF-8");
                }
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
                if (strtolower($key) == 'html'){
                    $data[$key] = stripcslashes($item);
                    //$item = "test";
                    //$item = html_entity_decode($item,ENT_COMPAT);
                    //$data[$key] = htmlspecialchars_decode($item, ENT_COMPAT);

                }
                //encode the item
                //$data[$key] = htmlspecialchars_decode($item);

                // * Some feeds (e.g. thestar.com) have html entities in the description, in which case they could be
                // decoded.  But others (e.g.  cbc.ca) do not.  Think about what to do here.
            }
        }
    }
    return $data;
}
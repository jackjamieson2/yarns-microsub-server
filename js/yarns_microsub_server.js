(function($) {

    /**
     * Remove links from items that will use javascript instead.
     */
    $(document).ready(function () {
        $( '.yarns-feed-unfollow' ).removeAttr( 'href' );
        $( '.yarns-feed-preview' ).removeAttr( 'href' );
    });


    /**
     * Click add new channel
     */
    $( "body" ).on( "click", "#yarns-channel-add", function() {
        button = $(this);

        if ( $('#yarns-new-channel-name').is(":hidden") ) {
            // show the channel name input
            $( '#yarns-new-channel-name' ).val('');
            $( '#yarns-new-channel-name' ).show();
            $( this) .text('Add');

        } else {
            // create channel with entered name
            start_loading(button);
            channel = $( '#yarns-new-channel-name').val();
            if ( channel != '') {
                $.ajax({
                    url : yarns_microsub_server_ajax.ajax_url,
                    type : 'post',
                    data : {
                        action : 'add_channel',
                        channel: channel,
                    },
                    success : function( response ) {
                        //done_loading(button);

                        console.log("success");
                        done_loading(button);
                        button.text('+ Add channel');
                        $( '#yarns-new-channel-name' ).hide();

                        $('#yarns-channels').html(response);
                    }
                });

            } else {
                // Error, the channel must have a name
            }

        }






    });

    /**
     * Delete a channel
     */
    $( "body" ).on( "click", "#yarns-channel-delete", function() {
        if (confirm("Do you want to delete this channel? You will lose all of its subscriptions")) {
            console.log("deleting channel checkpoint 1");
            button = $(this);
            start_loading(button);
            var uid =  $(this).data('uid');
            var channel = $('#yarns-option-heading').text();
            $.ajax({
                url: yarns_microsub_server_ajax.ajax_url,
                type: 'post',
                data: {
                    action: 'delete_channel',
                    uid: uid,
                },
                success: function (response) {
                    //done_loading(button);

                    console.log("success");
                    $('#yarns-channel-options').text("Deleted channel: " + channel);

                    $('#yarns-channels').html(response);
                    done_loading(button);

                }
            });
        }
    });

    /**
     * Rename a channel (update)
     */
    // Show the input box to rename a channel
    $( "body" ).on( "click", "#yarns-channel-update", function() {
       $(this).css('visibility', 'hidden');
       $('#yarns-option-heading').css('visibility', 'hidden');

       $('#yarns-channel-update-options').show();
    });

    // Save the new channel name

    $( "body" ).on( "click", "#yarns-channel-update-save", function() {
        button = $(this);

        var uid =  $(this).data('uid');
        var old_channel = $('#yarns-option-heading').text();
        var channel = $('#yarns-channel-update-input').val().trim();

        if (channel != '' && channel != old_channel ) {
            //update to use the new channel name

            start_loading(button);


            $.ajax({
                url: yarns_microsub_server_ajax.ajax_url,
                type: 'post',
                data: {
                    action: 'update_channel',
                    uid: uid,
                    channel: channel,
                },
                success: function (response) {
                    $('#yarns-channels').html(response);

                    $('#yarns-option-heading').text(channel);
                    $('#yarns-option-breadcrumbs span').text(channel);
                    $('#yarns-channel-update').css('visibility', 'visible');
                    $('#yarns-option-heading').css('visibility', 'visible');
                    $('#yarns-channel-update-options').hide();
                    done_loading(button);
                }
            });

        } else {
            //revert to old name
            $('#yarns-channel-update-input').val(old_channel);

            $('#yarns-channel-update').css('visibility', 'visible');
            $('#yarns-option-heading').css('visibility', 'visible');

            $('#yarns-channel-update-options').hide();

        }

    });


    /**
     * Save filters button
     *
     */
    $( "body" ).on( "click", ".yarns-channel-filters-save", function() {
        console.log('Clicked refresh button');

        button = $(this);
        start_loading(button);

        var parent = $(this).parent($('.yarns-channel'));


        //console.log(parent);
        //console.log(parent.data('test'));
        var uid =  $(this).data('uid');


        console.log(uid);

        var options = [];

        parent.find('label').each(function(index){
            if ($(this).find('input').prop("checked")==true){
                //console.log($(this).text());
                options.push( $(this).text());
            }

        });

        $.ajax({
            url : yarns_microsub_server_ajax.ajax_url,
            type : 'post',
            data : {
                action : 'save_filters',
                uid: uid,
                options: options,
            },
            success : function( response ) {
                done_loading(button);
                console.log("success");

            }
        });
    });


    /**
     * Manage subscriptions
     */

    /**
     * Search for feeds
     *
     */
    $( "body" ).on( "click", "#yarns-channel-find-feeds", function() {
        query = $('#yarns-URL-input').val();
        console.log("Searching for " + query);
        button = $(this);
        start_loading(button);

        $.ajax({
            url : yarns_microsub_server_ajax.ajax_url,
            type : 'post',
            data : {
                action : 'find_feeds',
                query: query,
            },
            success : function( response ) {
                done_loading(button);
                console.log("success");
                $('#yarns-feed-picker-list').html(response);

                $('#yarns-feed-picker-list input').first().trigger("click");

            }
        });

    });

    /**
     * Display a preview
     */
    $( "body" ).on( "click", "#yarns-channel-preview-feed", function() {
        url = $('input[name=yarns-feed-picker]:checked').val();

        button = $(this);
        start_loading(button);

        $.ajax({
            url : yarns_microsub_server_ajax.ajax_url,
            type : 'post',
            data : {
                action : 'preview_feed',
                url: url,
            },
            success : function( response ) {
                done_loading(button);
                $('#yarns-preview-container').html(response);
                $('#yarns-preview-url').text(url);

                // Change all links to open in new tab
                $('#yarns-preview-container').find('a').each(function( index ) {
                    $(this).attr('target','_blank');
                });

                $('#yarns-preview-outer-container').show();

            }
        });

    });

    /**
     * Close the preview
     */
    $( "body" ).on( "click", "#yarns-preview-close .close", function() {
        $('#yarns-preview-outer-container').hide();
    });




    /**
     * Follow a feed
     *
     */
    $( "body" ).on( "click", "#yarns-channel-add-feed", function() {
        url = $('input[name=yarns-feed-picker]:checked').val();
        uid = $('#yarns-options-uid').text();
        console.log(uid);
        console.log(url);

        button = $(this);
        start_loading(button);

        $.ajax({
            url : yarns_microsub_server_ajax.ajax_url,
            type : 'post',
            data : {
                action : 'follow_feed',
                uid: uid,
                url: url,
            },
            success : function( response ) {
                done_loading(button);
                console.log("success");
                $html_content = response.find('#yarns_follow_html');
                $message = response.find('#yarns_follow_message');
                $('#yarns-following-list').html(response);

            }
        });

    });

    /**
     * Unfollow a feed
     *
     */
    $( "body" ).on( "click", ".yarns-feed-unfollow", function() {
        console.log("clicked unfollow");
        //url = $(this).parent('.column-url').text()
        url = $(this).data('url');
        uid = $('#yarns-options-uid').text();
        console.log(uid);
        console.log(url);

        button = $(this);
        start_loading(button);

        $.ajax({
            url : yarns_microsub_server_ajax.ajax_url,
            type : 'post',
            data : {
                action : 'unfollow_feed',
                uid: uid,
                url: url,
            },
            success : function( response ) {
                done_loading(button);
                console.log("success");
                $('#yarns-following-list').html(response);
            }
        });
    });



    /**
     * Update feed url when radio button is clicked
     */

    $("body").on("click","input[name=yarns-feed-picker]", function() {
        //$('#yarns-channel-add-feed').show();
        //$('#yarns-channel-add-feed').data('url', $(this).val() );
        console.log($(this).val());
    });


    /**
     * DEBUGGING COMMANDS
     */
    $("body").on("click","#yarns_delete_posts", function() {
        if (confirm("This will delete content from all channels. ")) {
            button = $(this);
            start_loading(button);
            $.ajax({
                url: yarns_microsub_server_ajax.ajax_url,
                type: 'post',
                data: {
                    action: 'delete_posts'
                },
                success: function (response) {
                    done_loading(button);
                    alert("Deleted all posts.");
                }
            });
        }

    });

    $("body").on("click","#yarns_force_poll", function() {
        button = $(this);
        start_loading(button);
        $.ajax({
            url: yarns_microsub_server_ajax.ajax_url,
            type: 'post',
            data: {
                action: 'force_poll'
            },
            success: function (response) {
                done_loading(button);
                alert("Done polling");
            }
        });
    });



    function start_loading(target) {
        target.append('<span class="yarns-loading"></span>');
    }


    function done_loading(target) {
        target.find($('.yarns-loading')).remove();
    }


    /**
     *
     */



})(jQuery);
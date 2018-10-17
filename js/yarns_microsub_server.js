(function($) {

    /**
     *
     * @@todo: Add remove option for each feed
     * @@todo: 'Add feed' button for each channel.  Search->Preview->Follow (same workflow as alltogethernow.io)
     * @@todo:
     */

    /**
     * Select a channel
     */
    $( "body" ).on( "click", ".yarns-channel", function() {


        $('.yarns-channel').removeClass('selected');
        $(this).addClass('selected');

        $('#yarns-channel-options').empty();
        button = $(this);
        start_loading(button);

        var uid =  $(this).data('uid');

        $.ajax({
            url : yarns_microsub_server_ajax.ajax_url,
            type : 'post',
            data : {
                action : 'get_options',
                uid: uid,
            },
            success : function( response ) {
                //done_loading(button);

                console.log("success");
                done_loading(button);

                $('#yarns-channel-options').html(response);
            }
        });

    });




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
                $('#yarns-following-list').html(response);

            }
        });

    });

    /**
     * UNfollow a feed
     *
     */
    $( "body" ).on( "click", ".yarns-unfollow", function() {
        console.log("clicked unfollow");
        url = $(this).parent('li').find('a').text();

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



    function start_loading(target) {
        console.log("loading...");

        target.append('<div class="yarns-loading"></span>');
        //target.append('<img class="yarns-loading"></img>');

        //{ $(this).html('<img src="icon-loading.gif" />'); // A GIF Image of Your choice

        //target.addClass('yarns-loading');
        //target.append('<div class="yarns-loading"></div>');
    }


    function done_loading(target) {
        target.find($('.yarns-loading')).remove();

        //target.append('<div class="yarns-loading"></div>');
    }


    /**
     *
     */



})(jQuery);
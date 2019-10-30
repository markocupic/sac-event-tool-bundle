window.addEvent('domready', function() {

    var urlParams = new URLSearchParams(window.location.search);

    if(urlParams.has('act') && urlParams.get('table')){
        if(urlParams.get('act') === 'overrideAll' || urlParams.get('act') === 'editAll') {
            var strTable = urlParams.get('table');
            console.log(strTable);
            var nodeList = document.querySelectorAll('.tl_checkbox_container input[name="all_fields[]"]');
            for (i = 0; i < nodeList.length; ++i) {
                console.log(nodeList[i].id);
            }


            new Request.JSON({
                url: window.location.href,
                onSuccess: function (json, txt) {
                    if (json['status'] === 'success') {

                        console.log(json['navbar']);

                        var foo = "<p>Some text</p>";
                        foo.inject($('body'), 'top');
                    }
                }
            }).post({
                'action': 'loadEditAllFromSession',
                'REQUEST_TOKEN': Contao.request_token
            });


        }
    }


});

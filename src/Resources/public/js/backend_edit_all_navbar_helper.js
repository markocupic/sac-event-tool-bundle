/**
 * When using the editAll or overrideAll mode in the Contao backend
 * selected checkboxes can be save in the users session
 *
 * @type {{sessionData: null, initialize: EditAllNavbarHelper.initialize, getSessionData: EditAllNavbarHelper.getSessionData, saveSessionData: EditAllNavbarHelper.saveSessionData}}
 */
var EditAllNavbarHelper = {
    /**
     * sessionData
     */
    sessionData: null,

    /**
     * initialize
     */
    initialize: function () {
        var self = this;
        new Request.JSON({
            url: window.location.href,
            onSuccess: function (json, txt) {
                if (json['status'] === 'success') {
                    $$('body').appendHTML(json['navbar']);
                    $('editAllNavbarHelperGetSettings').addEvent('click', function () {
                        self.getSessionData();
                    });
                    $('editAllNavbarHelperSaveSettings').addEvent('click', function () {
                        self.saveSessionData();
                    });
                }
            }
        }).post({
            'action': 'editAllNavbarHandler',
            'subaction': 'loadNavbar',
            'REQUEST_TOKEN': Contao.request_token
        });
    },

    /**
     * get session data
     */
    getSessionData: function () {
        var self = this;
        new Request.JSON({
            url: window.location.href,
            onSuccess: function (json, txt) {
                if (json['status'] === 'success') {
                    self.sessionData = json['sessionData'];

                    // uncheck all checkboxes
                    var nodeList = document.querySelectorAll('.tl_checkbox_container input[name="all_fields[]"]');
                    var checkedItems = [];
                    for (i = 0; i < nodeList.length; ++i) {
                        nodeList[i].checked = false;
                    }

                    // Check checkboxes from session
                    if (self.sessionData.length) {
                        for (i = 0; i < self.sessionData.length; ++i) {
                            $(self.sessionData[i]).checked = true;
                        }
                    }
                }
            }
        }).post({
            'action': 'editAllNavbarHandler',
            'subaction': 'getSessionData',
            'REQUEST_TOKEN': Contao.request_token
        });
    },

    /**
     * save checkbox settings to tl_user.session
     */
    saveSessionData: function () {

        var nodeList = document.querySelectorAll('.tl_checkbox_container input[name="all_fields[]"]');
        var checkedItems = [];
        for (i = 0; i < nodeList.length; ++i) {
            if (nodeList[i].checked) {
                checkedItems.push(nodeList[i].id);
            }
        }

        var self = this;
        new Request.JSON({
            url: window.location.href,
            onSuccess: function (json, txt) {
                if (json['status'] === 'success') {
                    self.sessionData = json['sessionData'];
                }
            }
        }).post({
            'action': 'editAllNavbarHandler',
            'subaction': 'saveSessionData',
            'checkedItems': checkedItems,
            'REQUEST_TOKEN': Contao.request_token
        });
    }

}


window.addEvent('domready', function () {

    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('act') && urlParams.get('table')) {
        if (urlParams.get('act') === 'overrideAll' || urlParams.get('act') === 'editAll') {
            EditAllNavbarHelper.initialize();
        }
    }

});

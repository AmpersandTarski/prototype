angular.module('AmpersandApp')
.service('NavigationBarService', function(Restangular, $localStorage, $sessionStorage, $timeout, NotificationService){
    let navbar = {
        home: null, // home/start page, can be set in project.yaml (default: '#/prototype/welcome')
        top: [],
        new: [],
        refresh: [],
        role: [],
        ext: []
    };
    let defaultSettings = {
        notify_showSignals: true,
        notify_showInfos: true,
        notify_showSuccesses: true,
        notify_autoHideSuccesses: true,
        notify_showErrors: true,
        notify_showWarnings: true,
        notify_showInvariants: true,
        autoSave: true
    };
    let observerCallables = [];

    let notifyObservers = function(){
        angular.forEach(observerCallables, function(callable){
            callable();
        });
    };

    let service = {
        navbar : navbar,
        defaultSettings : defaultSettings,

        addObserverCallable : function(callable){
            observerCallables.push(callable);
        },

        refreshNavBar : function(){
            return Restangular
            .one('app/navbar')
            .get()
            .then(function(data){
                data = data.plain();

                // Content of navbar
                menus = treeify(data.navs, 'id', 'parent', 'children');
                navbar.home = data.home;
                navbar.top = menus.find(function(menu){
                    return menu.id === 'MainMenu'
                }).children;
                navbar.new = data.new;
                navbar.refresh = data.refresh;
                navbar.role = data.role;
                navbar.ext = data.ext;

                // Content for session storage
                $sessionStorage.session = data.session;
                $sessionStorage.sessionRoles = data.sessionRoles;
                $sessionStorage.sessionVars = data.sessionVars;
                
                // Save default settings
                service.defaultSettings = data.defaultSettings;
                service.initializeSettings();
                
                // Update notifications
                NotificationService.updateNotifications(data.notifications);

                notifyObservers();
            }, function(error){
                service.initializeSettings();
            });
        },

        initializeSettings : function(){
            let resetRequired = false;

            // Check for undefined settings
            angular.forEach(service.defaultSettings, function(value, index, obj){
                if($localStorage[index] === undefined) {
                    resetRequired = true;
                }
            });

            if(resetRequired) service.resetSettingsToDefault();
        },

        resetSettingsToDefault : function(){
            // all off
            angular.forEach(service.defaultSettings, function(value, index, obj){
                $localStorage[index] = false;
            });
            
            $timeout(function() {
                // Reset to default
                $localStorage.$reset(service.defaultSettings);
            }, 500);
        }
    };

    /**
     * Creates a tree from flat list of elements with parent specified.
     * If no parent specified, the element is considered a root node
     * The function returns a list of root nodes
     * 'id', 'parent' and 'children' object labels can be set
     * 
     * @param {Array} list 
     * @param {string} idAttr 
     * @param {string} parentAttr 
     * @param {string} childrenAttr 
     * @returns {Array}
     */
    function treeify(list, idAttr, parentAttr, childrenAttr) {
        if (!idAttr) idAttr = 'id';
        if (!parentAttr) parentAttr = 'parent';
        if (!childrenAttr) childrenAttr = 'children';
        var treeList = [];
        var lookup = {};
        list.forEach(function(obj) {
            lookup[obj[idAttr]] = obj;
            obj[childrenAttr] = [];
        });
        list.forEach(function(obj) {
            if (obj[parentAttr] != null) {
                if (lookup[obj[parentAttr]] === undefined) { // error when parent element is not defined in list
                    console.error('Parent element is undefined: ', obj[parentAttr]);
                } else {
                    lookup[obj[parentAttr]][childrenAttr].push(obj);
                    obj[parentAttr] = lookup[obj[parentAttr]]; // replace parent id with reference to actual parent element
                }
            } else {
                treeList.push(obj);
            }
        });
        return treeList;
    }
    
    return service;
});

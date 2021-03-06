angular.module('AmpersandApp')
.service('NotificationService', function($localStorage, $sessionStorage, $timeout, Restangular){
    // Initialize notifications container
    let notifications = {
        'signals' : [],
        'invariants' : [],
        'infos' : [],
        'successes' : [],
        'warnings' : [],
        'errors' : []
    };
    
    let NotificationService = {
        notifications : notifications,
        
        // Function to get notifications again
        getNotifications : function(){
            return Restangular
            .one('app/notifications')
            .get()
            .then(
                function(data){
                    NotificationService.updateNotifications(data);
                },
                function(){
                    NotificationService.addError('Something went wrong while getting notifications');
                }
            );
        },

        checkAllRules : function(){
            return Restangular
            .one('admin/ruleengine/evaluate/all')
            .get()
            .then(
                function(data){
                    NotificationService.addSuccess('Evaluated all rules.');
                    NotificationService.updateNotifications(data);
                },function(){
                    NotificationService.addError('Something went wrong while evaluating all rules');
                }
            );
        },
        
        // Function to update notifications after api response
        updateNotifications : function(data){
            if(data === undefined) return;
            
            // Overwrite
            notifications.signals = data.signals;
            notifications.invariants = data.invariants;
            
            // Merge
            notifications.infos = notifications.infos.concat(data.infos);
            notifications.successes = notifications.successes.concat(data.successes);
            notifications.warnings = notifications.warnings.concat(data.warnings);
            notifications.errors = notifications.errors.concat(data.errors);
            
            if($localStorage.notify_autoHideSuccesses){
                $timeout(function() {
                    notifications.successes = [];
                }, 3000);
            }
        },

        clearNotifications : function () {
            notifications.signals = [];
            notifications.invariants = [];
            notifications.infos = [];
            notifications.successes = [];
            notifications.warnings = [];
            notifications.errors = [];
        },
        
        addSuccess : function(message){
            notifications.successes.push({
                'message' : message,
                'count' : 1
            });
            
            // TODO: move timeout function here for auto hide successes
        },
        
        addError : function(message, code, persistent, details){
            code = typeof code !== undefined ? code : null;
            persistent = typeof persistent !== undefined ? persistent : false;
            details = typeof details !== undefined ? details : false;
            
            let alreadyExists = false;
            let arr = notifications.errors;
            for (let i = 0; i < arr.length; i++) {
                if (arr[i].message == message) {
                    arr[i].count += 1;
                    arr[i].code = code;
                    arr[i].persistent = persistent;
                    arr[i].details = details;
                    alreadyExists = true;
                }
            }
            if(!alreadyExists) notifications.errors.push({
                'message' : message,
                'code' : code,
                'count' : 1,
                'persistent' : persistent,
                'details' : details
            });
        },
        
        addWarning : function(message){
            let alreadyExists = false;
            let arr = notifications.warnings;
            for (var i = 0; i < arr.length; i++) {
                if (arr[i].message == message) {
                    arr[i].count += 1;
                    alreadyExists = true;
                }
            }
            if(!alreadyExists) notifications.warnings.push({
                'message' : message,
                'count' : 1
            });
        },
        
        addInfo : function(message){
            let alreadyExists = false;
            let arr = notifications.infos;
            for (var i = 0; i < arr.length; i++) {
                if (arr[i].message == message) {
                    arr[i].count += 1;
                    alreadyExists = true;
                }
            }
            if(!alreadyExists) notifications.infos.push({
                'message' : message,
                'count' : 1
            });
        }
    };
    
    return NotificationService;
});
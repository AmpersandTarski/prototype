angular.module('AmpersandApp')
.service('RoleService', function($sessionStorage, Restangular){
    
    /*
     * Available roles are registered in $sessionStorage.sessionRoles
     * A role has the following attributes: id, label, active
     */
    
    return {
        selectRole : function(roleId){
            this.toggleRole(roleId, true);
        },
        
        selectRoleByLabel : function (roleLabel){
            angular.forEach($sessionStorage.sessionRoles, function(role) {
                if(role.label == roleLabel) return this.selectRole(role.id);
            });
        },
        
        toggleRole : function(roleId, set){
            angular.forEach($sessionStorage.sessionRoles, function(role) {
                if (role.id == roleId) {
                    if(set === undefined) role.active = !role.active;
                    else role.active = set;
                }
            });
        },
        
        getActiveRoleIds : function(){
            var roleIds = [];
            angular.forEach($sessionStorage.sessionRoles, function(role) {
                if (role.active === true) {
                    roleIds.push(role.id);
                }
            });
            return roleIds;
        },
        
        deactivateAllRoles : function(){
            angular.forEach($sessionStorage.sessionRoles, function(role) {
                role.active = false;
            });
        },
        
        setActiveRoles : function(){
            return Restangular.all('app/roles').patch($sessionStorage.sessionRoles);
        }
    };
});

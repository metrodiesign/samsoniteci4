/**
 * File : addUser.js
 *
 * This file contain the validation of add user form
 *
 * Using validation plugin : jquery.validate.js
 *
 * @author Kishor Mali
 */

$(document).ready(function(){

	var addUserForm = $("#addtrack");

	var validator = addUserForm.validate({

		rules:{

			searchText :{ required : true }
		},
		messages:{

			searchText :{ required : "This searchText is required" }
		}
	});
});

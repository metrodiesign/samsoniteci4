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

	var addUserForm = $("#addContact");

	var validator = addUserForm.validate({

		rules:{

			fullname :{ required : true },
			email : { required : true, email : true },
			phone : { required : true},
			detail : { required : true}
		},
		messages:{
			fullname :{ required : "This field is required" },
			email : { required : "This field is required", email : "Please enter valid email address" },
			phone : {required : "This field is required", digits : "Please enter numbers only"},
			detail : { required : "This field is required" }
		}
	});
});

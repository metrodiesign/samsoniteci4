/**
 * File : editBranch.js
 *
 * This file contain the validation of edit Branch form
 *
 * Using validation plugin : jquery.validate.js
 *
 * @author Bunhan Poolumtan
 */

$(document).ready(function(){

	var addUserForm = $("#editBranch");

	var validator = addUserForm.validate({

		rules:{
			branch_type :{ required : true, selected : true},
			branch_name : { required : true }
		},
		messages:{
			branch_type :{ required : "This field is required", selected : "Please select atleast one option" },
			branch_name : { required : "This field is required" }
		}
	});
});

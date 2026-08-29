/**
 * File : addBranch.js
 *
 * This file contain the validation of add Branch form
 *
 * Using validation plugin : jquery.validate.js
 *
 * @author Bunhan Poolumtan
 */

$(document).ready(function(){

	var addUserForm = $("#addBranchtype");

	var validator = addUserForm.validate({

		rules:{
			branch_type_name : { required : true }
		},
		messages:{
			branch_type_name : { required : "This field is required" }
		}
	});
});

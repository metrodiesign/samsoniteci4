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

	var addUserForm = $("#addBook");

	var validator = addUserForm.validate({

		rules:{
			branch_id :{ required : true, selected : true},
			book_detail : { required : true }
		},
		messages:{
			branch_id :{ required : "This field is required", selected : "Please select atleast one option" },
			book_detail : { required : "This field is required" }
		}
	});
});

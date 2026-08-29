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

	var addUserForm = $("#addMenu");

	var validator = addUserForm.validate({

		rules:{
			name : { required : true },
			group_type :{ required : true, selected : true}

		},
		messages:{

			name : { required : "This field is required" },
			group_type :{ required : "This field is required", selected : "Please select atleast one option" }
		}
	});
});
